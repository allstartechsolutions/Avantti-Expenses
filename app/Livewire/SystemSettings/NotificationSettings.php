<?php

namespace App\Livewire\SystemSettings;

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
    public int $digestDay = 1;
    public int $digestHour = 7;

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_admin, 403, 'Administrator access required.');

        $this->digestDay = NotificationSetting::digestDay();
        $this->digestHour = NotificationSetting::digestHour();
    }

    #[Computed]
    public function settings(): Collection
    {
        return NotificationSetting::query()->get()->keyBy('key');
    }

    public function toggle(string $key): void
    {
        abort_unless(auth()->user()?->is_admin, 403);

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
        abort_unless(auth()->user()?->is_admin, 403);

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
