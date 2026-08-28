<?php

namespace App\Livewire\SystemSettings;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\NotificationSetting;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Which task e-mails this install sends, and when the weekly digest goes out.
 *
 * A trigger switched off here beats anything a person set on their profile —
 * an install that does not send overdue mail does not send it to anybody.
 */
class NotificationSettings extends Component
{
    use AuthorizesAbility;

    public int $digestDay = 1;
    public int $digestHour = 7;

    // Purchasing — how hard the reminders push.
    public int $awaitingDays = NotificationSetting::DEFAULT_AWAITING_DAYS;
    public int $awaitingMaxReminders = NotificationSetting::DEFAULT_AWAITING_REMINDERS;
    public int $stallDays = NotificationSetting::DEFAULT_STALL_DAYS;
    public int $stallMaxReminders = NotificationSetting::DEFAULT_STALL_REMINDERS;
    public int $dueLeadDays = NotificationSetting::DEFAULT_DUE_LEAD_DAYS;

    public function mount(): void
    {
        $this->authorizeAbility('settings.view');

        $this->digestDay = NotificationSetting::digestDay();
        $this->digestHour = NotificationSetting::digestHour();

        $this->awaitingDays = NotificationSetting::awaitingDays();
        $this->awaitingMaxReminders = NotificationSetting::awaitingMaxReminders();
        $this->stallDays = NotificationSetting::stallDays();
        $this->stallMaxReminders = NotificationSetting::stallMaxReminders();
        $this->dueLeadDays = NotificationSetting::dueLeadDays();
    }

    #[Computed]
    public function settings(): Collection
    {
        return NotificationSetting::query()->get()->keyBy('key');
    }

    public function toggle(string $key): void
    {
        $this->authorizeAbility('settings.edit');

        $setting = NotificationSetting::firstOrCreate(['key' => $key]);

        $setting->update([
            'is_enabled' => ! $setting->is_enabled,
            'updated_by' => auth()->id(),
        ]);

        unset($this->settings);

        session()->flash('message', $setting->is_enabled
            ? __(':name is on.', ['name' => NotificationSetting::label($key)])
            : __(':name is off. Nobody receives it.', ['name' => NotificationSetting::label($key)]));
    }

    public function saveDigestSchedule(): void
    {
        $this->authorizeAbility('settings.edit');

        $this->validate([
            'digestDay' => ['required', 'integer', 'min:1', 'max:7'],
            'digestHour' => ['required', 'integer', 'min:0', 'max:23'],
        ]);

        NotificationSetting::firstOrCreate(['key' => NotificationSetting::TASK_WEEKLY_DIGEST])
            ->update([
                'options' => ['day' => $this->digestDay, 'hour' => $this->digestHour],
                'updated_by' => auth()->id(),
            ]);

        unset($this->settings);

        session()->flash('message', __('The digest schedule was saved.'));
    }

    /**
     * The two purchasing reminders that carry a number.
     *
     * Saved together because they are one decision — how hard the system
     * pushes — rather than three unrelated fields.
     */
    public function saveProcurementOptions(): void
    {
        $this->authorizeAbility('settings.edit');

        $this->validate([
            'awaitingDays' => ['required', 'integer', 'min:1', 'max:90'],
            'awaitingMaxReminders' => ['required', 'integer', 'min:1', 'max:20'],
            'stallDays' => ['required', 'integer', 'min:1', 'max:90'],
            'stallMaxReminders' => ['required', 'integer', 'min:1', 'max:20'],
            'dueLeadDays' => ['required', 'integer', 'min:1', 'max:60'],
        ], [], [
            'awaitingDays' => __('days before chasing a decision'),
            'awaitingMaxReminders' => __('decision chases at most'),
            'stallDays' => __('days before the first nudge'),
            'stallMaxReminders' => __('nudges at most'),
            'dueLeadDays' => __('days of warning before responses are due'),
        ]);

        NotificationSetting::firstOrCreate(['key' => NotificationSetting::REQUISITION_AWAITING])
            ->update([
                'options' => ['days' => $this->awaitingDays, 'max_reminders' => $this->awaitingMaxReminders],
                'updated_by' => auth()->id(),
            ]);

        NotificationSetting::firstOrCreate(['key' => NotificationSetting::REQUISITION_STALLED])
            ->update([
                'options' => ['days' => $this->stallDays, 'max_reminders' => $this->stallMaxReminders],
                'updated_by' => auth()->id(),
            ]);

        NotificationSetting::firstOrCreate(['key' => NotificationSetting::QUOTATION_DUE_SOON])
            ->update([
                'options' => ['lead_days' => $this->dueLeadDays],
                'updated_by' => auth()->id(),
            ]);

        unset($this->settings);

        session()->flash('message', __('The reminder settings were saved.'));
    }

    public function days(): array
    {
        return [
            1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'),
            4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 7 => __('Sunday'),
        ];
    }

    public function render()
    {
        return view('livewire.system-settings.notification-settings');
    }
}
