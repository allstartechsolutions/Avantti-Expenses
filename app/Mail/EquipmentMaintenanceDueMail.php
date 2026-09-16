<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * "This equipment needs service" — one morning's worth, to one person.
 *
 * `$upcoming` rows are `['maintenance', 'stage']` (stage = days ahead);
 * `$due` and `$overdue` are the maintenances themselves. The template groups
 * all three by equipment so one machine with three services reads as one
 * block.
 */
class EquipmentMaintenanceDueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public Collection $upcoming,
        public Collection $due,
        public Collection $overdue,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->upcoming->count() + $this->due->count() + $this->overdue->count();

        return new Envelope(subject: $this->overdue->isNotEmpty() || $this->due->isNotEmpty()
            ? trans_choice(':count equipment maintenance is due|:count equipment maintenances need attention', $count, ['count' => $count])
            : trans_choice(':count equipment maintenance is coming due|:count equipment maintenances are coming due', $count, ['count' => $count]));
    }

    public function content(): Content
    {
        $rows = $this->overdue->map(fn ($m) => ['maintenance' => $m, 'state' => 'overdue'])
            ->concat($this->due->map(fn ($m) => ['maintenance' => $m, 'state' => 'due']))
            ->concat($this->upcoming->map(fn (array $row) => ['maintenance' => $row['maintenance'], 'state' => 'upcoming', 'stage' => $row['stage']]));

        $groups = $rows
            ->groupBy(fn (array $row) => $row['maintenance']->equipment_id)
            ->map(fn (Collection $rows) => ['equipment' => $rows->first()['maintenance']->equipment, 'rows' => $rows])
            ->sortBy(fn (array $group) => $group['equipment']?->name ?? '')
            ->values();

        return new Content(view: 'emails.equipment-maintenance-due', with: [
            'recipient' => $this->recipient,
            'groups' => $groups,
            'hasOverdue' => $this->overdue->isNotEmpty(),
        ]);
    }
}
