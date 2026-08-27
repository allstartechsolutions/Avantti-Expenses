<?php

namespace App\Models\Concerns\Collaboration;

use App\Services\Collaboration\NumberSequenceService;

/**
 * Gives a document its number, from the sequence and never from a form.
 *
 * A model using this declares `documentType()` and, where its numbers carry
 * one, `numberTokens()` — the discipline on a BR RFI, so that SI-ARQ-014
 * renders.
 *
 * The number is taken on create, inside the service's transaction. It is
 * deliberately not taken on draft-save-then-publish: a draft that never
 * becomes a document would otherwise consume a number and leave the gap this
 * whole mechanism exists to avoid.
 */
trait HasSequentialNumber
{
    public static function bootHasSequentialNumber(): void
    {
        static::creating(function (self $model) {
            if (! $model->number) {
                $model->number = $model->issueNumber();
            }
        });
    }

    /** 'rfi' | 'approval' — the sequence this model draws from. */
    abstract public function documentType(): string;

    /**
     * Extra placeholders for the template.
     *
     * @return array<string, string>
     */
    public function numberTokens(): array
    {
        return [];
    }

    /** Take the next number. Consumes it — call once, on create. */
    public function issueNumber(): string
    {
        return app(NumberSequenceService::class)->next(
            $this->project ?? \App\Models\Project::findOrFail($this->project_id),
            $this->documentType(),
            $this->numberTokens(),
        );
    }
}
