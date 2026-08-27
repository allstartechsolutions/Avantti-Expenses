<?php

namespace Database\Seeders;

use App\Models\Collaboration\ResponseCode;
use Illuminate\Database\Seeder;

/**
 * The coded answers a reviewer may give, for both markets.
 *
 * Both sets are seeded even though an installation serves one country: they
 * are ten rows, and having the mapping in the table is what makes it obvious
 * that `code` is presentation and `canonical` is meaning. Which set is offered
 * is decided from `config('app.country')` by `ResponseCode::offered()`.
 *
 * Safe to run repeatedly — rows are matched on their natural key.
 */
class CollaborationResponseCodeSeeder extends Seeder
{
    /**
     * canonical => [code, translation key, closes the cycle?]
     *
     * `approved_as_noted` closes: the reviewer has accepted it, with remarks
     * to be carried into the work. `revise_resubmit` does not — it opens the
     * next revision. `rejected` closes the revision, not the approval: what
     * follows a rejection is a new submission, which is a new cycle.
     * `for_record_only` is not a decision at all, so it closes.
     */
    protected const CODES = [
        ResponseCode::APPROVED => [
            'us' => ['A', 'collaboration.response.approved_us'],
            'br' => ['A', 'collaboration.response.approved_br'],
            'closes' => true,
        ],
        ResponseCode::APPROVED_AS_NOTED => [
            'us' => ['B', 'collaboration.response.approved_noted_us'],
            'br' => ['B', 'collaboration.response.approved_noted_br'],
            'closes' => true,
        ],
        ResponseCode::REVISE_RESUBMIT => [
            'us' => ['C', 'collaboration.response.revise_us'],
            'br' => ['C', 'collaboration.response.revise_br'],
            'closes' => false,
        ],
        ResponseCode::REJECTED => [
            'us' => ['D', 'collaboration.response.rejected_us'],
            'br' => ['D', 'collaboration.response.rejected_br'],
            'closes' => true,
        ],
        ResponseCode::FOR_RECORD_ONLY => [
            'us' => ['E', 'collaboration.response.record_only_us'],
            'br' => ['E', 'collaboration.response.record_only_br'],
            'closes' => true,
        ],
    ];

    /**
     * RFIs are answered in prose, not with a code, so only approvals get these.
     * The constant is here rather than inline so that a second document type
     * needing codes is one line.
     */
    protected const DOCUMENT_TYPES = ['approval'];

    public function run(): void
    {
        foreach (self::DOCUMENT_TYPES as $documentType) {
            $sort = 0;

            foreach (self::CODES as $canonical => $definition) {
                $sort += 10;

                foreach (['us', 'br'] as $market) {
                    [$code, $labelKey] = $definition[$market];

                    ResponseCode::updateOrCreate(
                        [
                            'project_id' => null,
                            'market' => $market,
                            'document_type' => $documentType,
                            'canonical' => $canonical,
                        ],
                        [
                            'code' => $code,
                            'label_key' => $labelKey,
                            'closes_cycle' => $definition['closes'],
                            'sort' => $sort,
                        ],
                    );
                }
            }
        }
    }
}
