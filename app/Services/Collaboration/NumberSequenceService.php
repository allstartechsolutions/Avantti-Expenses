<?php

namespace App\Services\Collaboration;

use App\Models\Collaboration\NumberSequence;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues document numbers, one at a time, without gaps and without repeats.
 *
 * **Why this exists rather than `max(number) + 1`.** Every other module in this
 * codebase numbers its records by reading the highest one it can find and
 * adding one — `Meeting::nextNumber()` is the clearest. Two problems, both of
 * which matter more here than there:
 *
 *   1. Delete the newest record and the next one issued takes its number. For
 *      a document that has already been e-mailed to a projetista, two
 *      different documents then share a number.
 *   2. Two people creating at the same moment read the same maximum. The row
 *      lock in that query only covers rows the query matched, so on the very
 *      first record of a project — where it matches nothing — it locks
 *      nothing.
 *
 * Here the counter is a column. `lockForUpdate()` inside a transaction holds
 * that one row until commit, so a concurrent caller waits rather than reads a
 * stale value, and the counter only ever goes up.
 *
 * **Callers must not do the read themselves.** The guarantee holds only inside
 * the transaction this opens.
 */
class NumberSequenceService
{
    /**
     * Take the next number for a document type on a project.
     *
     * Creates the sequence on first use, and locks it from that moment: the
     * template and start value are settings, and a setting that changes after
     * the first document went out is a renumbering.
     *
     * @param  array<string, string>  $tokens  e.g. ['discipline' => 'ARQ']
     */
    public function next(Project $project, string $documentType, array $tokens = []): string
    {
        return DB::transaction(function () use ($project, $documentType, $tokens) {
            $sequence = $this->lockedSequence($project, $documentType);

            $value = max($sequence->current_value + 1, $sequence->start_value);

            $sequence->current_value = $value;
            $sequence->locked = true;
            $sequence->save();

            return $sequence->render($value, $tokens);
        });
    }

    /**
     * The sequence row for a project and type, created if it is not there.
     *
     * `lockForUpdate()` is what serialises concurrent callers. The insert is
     * `firstOrCreate` outside the lock and then re-read under it: two callers
     * racing to create the same sequence means one of them loses the unique
     * key, and losing it is fine — the row exists either way, and the lock is
     * taken on the re-read.
     */
    protected function lockedSequence(Project $project, string $documentType): NumberSequence
    {
        NumberSequence::firstOrCreate(
            ['project_id' => $project->id, 'document_type' => $documentType],
            [
                'template' => NumberSequence::DEFAULT_TEMPLATES[$documentType] ?? '{prefix}-{seq:000}',
                'start_value' => 1,
                'current_value' => 0,
                'locked' => false,
            ],
        );

        return NumberSequence::query()
            ->where('project_id', $project->id)
            ->where('document_type', $documentType)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * The sequence as a setting, for the screen that configures it.
     *
     * Does not lock and does not consume; `peek()` on the result says what the
     * next number would be.
     */
    public function sequenceFor(Project $project, string $documentType): NumberSequence
    {
        return NumberSequence::firstOrCreate(
            ['project_id' => $project->id, 'document_type' => $documentType],
            [
                'template' => NumberSequence::DEFAULT_TEMPLATES[$documentType] ?? '{prefix}-{seq:000}',
                'start_value' => 1,
                'current_value' => 0,
                'locked' => false,
            ],
        );
    }

    /**
     * Change the template or the start value.
     *
     * Refused once the sequence has issued anything, with a message that says
     * why rather than failing silently — the difference between "you may not"
     * and "nothing happened" is the whole of the user's experience here.
     *
     * @throws ValidationException
     */
    public function configure(NumberSequence $sequence, ?string $template = null, ?int $startValue = null): NumberSequence
    {
        if (! $sequence->isEditable()) {
            throw ValidationException::withMessages([
                'template' => __('collaboration.help.sequence_already_issued_format_starting', [
                    'number' => $sequence->render($sequence->current_value),
                ]),
            ]);
        }

        if ($template !== null) {
            if (! str_contains($template, '{seq')) {
                throw ValidationException::withMessages([
                    'template' => __('collaboration.help.format_must_include_every_document', ['token' => '{seq:000}']),
                ]);
            }

            $sequence->template = $template;
        }

        if ($startValue !== null) {
            if ($startValue < 1) {
                throw ValidationException::withMessages([
                    'start_value' => __('collaboration.message.starting_number_must_least'),
                ]);
            }

            $sequence->start_value = $startValue;
        }

        $sequence->save();

        return $sequence;
    }
}
