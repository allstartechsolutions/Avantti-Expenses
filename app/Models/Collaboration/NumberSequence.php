<?php

namespace App\Models\Collaboration;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The counter behind a document number.
 *
 * Reading and writing this row is `NumberSequenceService`'s job, not a
 * caller's — the guarantee it offers only holds inside the transaction that
 * service opens. What lives here is the part that is about the record itself:
 * how the number is rendered, and what may still be changed.
 */
class NumberSequence extends Model
{
    protected $table = 'collaboration_number_sequences';

    protected $fillable = [
        'project_id',
        'document_type',
        'template',
        'start_value',
        'current_value',
        'locked',
    ];

    protected $casts = [
        'start_value' => 'integer',
        'current_value' => 'integer',
        'locked' => 'boolean',
    ];

    /** The default when a project has not been given one of its own. */
    public const DEFAULT_TEMPLATES = [
        'rfi' => '{prefix}-{seq:000}',
        'approval' => '{prefix}-{seq:000}',
    ];

    public const DEFAULT_PREFIXES = [
        'rfi' => 'RFI',
        'approval' => 'APR',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /*
    |---------------------------------------------------------------------------
    | What may still be changed
    |---------------------------------------------------------------------------
    */

    /**
     * A sequence is editable until it has issued its first number.
     *
     * After that, changing the template or the start value would renumber
     * nothing already sent and everything still to come, which is how two
     * documents end up sharing a number.
     */
    public function isEditable(): bool
    {
        return ! $this->locked;
    }

    /** The number this sequence will issue next, without issuing it. */
    public function peek(): int
    {
        return max($this->current_value + 1, $this->start_value);
    }

    /**
     * Render a number from this sequence's template.
     *
     * `{seq:000}` zero-pads to the width of the placeholder, so `{seq:0000}`
     * gives 0014. `{prefix}` and `{discipline}` come from the caller — the
     * discipline is the BR case, where a number reads SI-ARQ-014.
     */
    public function render(int $sequence, array $tokens = []): string
    {
        $tokens += ['prefix' => self::DEFAULT_PREFIXES[$this->document_type] ?? 'DOC'];

        $number = preg_replace_callback(
            '/\{seq(?::(0+))?\}/',
            fn ($m) => str_pad((string) $sequence, strlen($m[1] ?? '0'), '0', STR_PAD_LEFT),
            $this->template,
        );

        foreach ($tokens as $key => $value) {
            $number = str_replace('{'.$key.'}', (string) $value, $number);
        }

        // A token the caller did not supply leaves an empty segment behind —
        // an RFI with no discipline on a template that expects one would read
        // SI--014. Collapse the gap rather than print it.
        $number = preg_replace('/\{[a-z_]+\}/', '', $number);

        return trim(preg_replace('/-{2,}/', '-', $number), '-');
    }
}
