<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use Illuminate\Support\Collection;

/**
 * How a payment batch is laid out: grouped by client, project, job site,
 * subcontractor or line status, and sorted by a column inside each group.
 * The batch screens, /contract-payments (through SortsContracts), their PDFs
 * and CSVs all ask this class, so paper comes out in the order the screen
 * showed.
 */
class PaymentBatchLayout
{
    public const GROUPS = ['client', 'project', 'job_site', 'subcontractor', 'status'];

    /** Columns the batch lines sort by (show screen, PDF, CSV). */
    public const LINE_SORTS = ['subcontractor', 'project', 'job_site', 'contract', 'amount', 'method', 'status'];

    /** The order lines have always come in: approved, pending, rejected. */
    public const STATUS_ORDER = ['approved' => 0, 'pending' => 1, 'rejected' => 2];

    /**
     * Grouping choices, translated. Status belongs to a batch line, so only
     * the screens that list lines offer it.
     */
    public static function groupOptions(bool $withStatus = true): array
    {
        $options = [
            'client' => __('Client'),
            'project' => __('Project'),
            'job_site' => __('Job Site'),
            'subcontractor' => __('Subcontractor'),
        ];

        if ($withStatus) {
            $options['status'] = __('Status');
        }

        return $options;
    }

    /** A grouping value from the browser, or '' when it is not one on offer. */
    public static function cleanGroup(mixed $groupBy, bool $withStatus = true): string
    {
        return is_string($groupBy) && array_key_exists($groupBy, self::groupOptions($withStatus)) ? $groupBy : '';
    }

    public static function cleanLineSort(mixed $field): string
    {
        return in_array($field, self::LINE_SORTS, true) ? $field : '';
    }

    public static function cleanDirection(mixed $direction): string
    {
        return $direction === 'desc' ? 'desc' : 'asc';
    }

    /**
     * Every line of the batch with what the screen, PDF and CSV print,
     * grouped and sorted.
     */
    public static function lines(PaymentBatch $batch, string $groupBy, string $sort, string $direction, bool $documentHealth = false): Collection
    {
        $items = $batch->items()
            ->with([
                'contract.project.client', 'contract.jobSite', 'contract.subcontractorEmployee',
                'scheduleItem', 'measurement',
                'contract.subcontractor' => fn ($q) => $documentHealth ? $q->withDocumentHealth() : $q,
            ])
            ->get();

        return self::arrange(
            self::sortLines($items, $sort, $direction),
            $groupBy,
            fn (PaymentBatchItem $item) => $item->contract,
            fn (PaymentBatchItem $item) => $item->status,
        )->map(fn (array $group) => $group + ['totals' => self::lineTotals($group['rows'])]);
    }

    /** Count and money of a set of batch lines, by status, in cents-exact sums. */
    public static function lineTotals(Collection $items): array
    {
        $sum = fn (?string $status = null) => ($status ? $items->where('status', $status) : $items)
            ->sum(fn ($item) => (int) $item->getRawOriginal('amount')) / 100.0;

        return [
            'count' => $items->count(),
            'total_amount' => $sum(),
            'approved_amount' => $sum('approved'),
            'pending_amount' => $sum('pending'),
            'rejected_amount' => $sum('rejected'),
        ];
    }

    /**
     * Batch lines in column order. With no column chosen they keep the order
     * they always had — by status — then project, lot and contract number.
     * Blank text (no subcontractor, Project General) goes last either way.
     */
    public static function sortLines(Collection $items, string $field, string $direction): Collection
    {
        $default = fn ($a, $b) => self::compareTuples(self::lineOrder($a), self::lineOrder($b));

        if (! in_array($field, self::LINE_SORTS, true)) {
            return $items->sort($default)->values();
        }

        $text = fn (PaymentBatchItem $item) => match ($field) {
            'subcontractor' => $item->contract->subcontractor?->company_name,
            'project' => $item->contract->project?->project_name,
            'job_site' => $item->contract->jobSite?->job_site_name,
            'contract' => $item->contract->contract_number,
            'method' => $item->payment_method ? __($item->getPaymentMethodLabel()) : null,
            default => null,
        };

        return $items->sort(function ($a, $b) use ($field, $direction, $default, $text) {
            if (in_array($field, ['amount', 'status'], true)) {
                $result = $field === 'amount'
                    ? $a->getRawOriginal('amount') <=> $b->getRawOriginal('amount')
                    : (self::STATUS_ORDER[$a->status] ?? 9) <=> (self::STATUS_ORDER[$b->status] ?? 9);
            } else {
                [$left, $right] = [(string) $text($a), (string) $text($b)];

                if (($left === '') !== ($right === '')) {
                    return $left === '' ? 1 : -1;
                }

                $result = strnatcasecmp($left, $right);
            }

            if ($result === 0) {
                return $default($a, $b);
            }

            return $direction === 'desc' ? -$result : $result;
        })->values();
    }

    /**
     * Rows in their groups, the groups in reading order. The rows must come
     * in already sorted: the order inside each group is kept. Ungrouped, the
     * result is one group with no label.
     *
     * @return Collection<int, array{key: string, label: ?string, sub: ?string, rows: Collection}>
     */
    public static function arrange(Collection $rows, string $groupBy, callable $contractOf, ?callable $statusOf = null): Collection
    {
        if (! in_array($groupBy, self::GROUPS, true) || ($groupBy === 'status' && ! $statusOf)) {
            return collect([['key' => 'all', 'label' => null, 'sub' => null, 'order' => [], 'rows' => $rows->values()]]);
        }

        $groups = [];

        foreach ($rows as $row) {
            $group = self::groupOf($groupBy, $contractOf($row), $statusOf ? $statusOf($row) : null);
            $groups[$group['key']] ??= $group + ['rows' => collect()];
            $groups[$group['key']]['rows']->push($row);
        }

        return collect($groups)
            ->sort(fn ($a, $b) => self::compareTuples($a['order'], $b['order']))
            ->values();
    }

    /**
     * The group a contract falls in: a key, its heading, the line under the
     * heading, and a tuple that orders the groups — blanks (no client,
     * Project General, no subcontractor) last. A lot is grouped within its
     * project, since lot names repeat from one project to the next.
     */
    protected static function groupOf(string $groupBy, Contract $contract, ?string $status): array
    {
        $project = $contract->project;
        $client = $project?->client;
        $subcontractor = $contract->subcontractor;

        return match ($groupBy) {
            'client' => [
                'key' => 'client-'.($client?->id ?? 0),
                'label' => $client?->company_name ?? __('No client'),
                'sub' => null,
                'order' => [$client ? 0 : 1, $client?->company_name ?? ''],
            ],
            'project' => [
                'key' => 'project-'.$contract->project_id,
                'label' => $project?->project_name ?? '—',
                'sub' => $client?->company_name,
                'order' => [$project?->project_name ?? '', (int) $contract->project_id],
            ],
            'job_site' => [
                'key' => 'site-'.$contract->project_id.'-'.($contract->job_site_id ?? 0),
                'label' => $contract->jobSite?->job_site_name ?? __('Project General'),
                'sub' => $project?->project_name,
                'order' => [
                    $project?->project_name ?? '', (int) $contract->project_id,
                    $contract->jobSite ? 0 : 1, $contract->jobSite?->job_site_name ?? '',
                ],
            ],
            'subcontractor' => [
                'key' => 'sub-'.($contract->subcontractor_id ?? 0),
                'label' => $subcontractor?->company_name ?? __('No subcontractor'),
                'sub' => null,
                'order' => [$subcontractor ? 0 : 1, $subcontractor?->company_name ?? ''],
            ],
            'status' => [
                'key' => 'status-'.$status,
                'label' => __(PaymentBatchItem::statusLabel($status)),
                'sub' => null,
                'order' => [self::STATUS_ORDER[$status] ?? 9],
            ],
        };
    }

    protected static function lineOrder(PaymentBatchItem $item): array
    {
        $contract = $item->contract;

        return [
            self::STATUS_ORDER[$item->status] ?? 9,
            $contract->project?->project_name ?? '',
            $contract->jobSite ? 0 : 1,
            $contract->jobSite?->job_site_name ?? '',
            $contract->contract_number ?? '',
            (int) $item->id,
        ];
    }

    /** Element by element: text in natural, case-blind order, numbers as numbers. */
    public static function compareTuples(array $a, array $b): int
    {
        foreach ($a as $index => $value) {
            $other = $b[$index] ?? null;
            $result = is_string($value) ? strnatcasecmp($value, (string) $other) : $value <=> $other;

            if ($result !== 0) {
                return $result;
            }
        }

        return 0;
    }
}
