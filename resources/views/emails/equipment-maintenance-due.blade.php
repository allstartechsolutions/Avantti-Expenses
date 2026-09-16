<x-email-shell :heading="__('Equipment Maintenance')">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        {{ __('These pieces of equipment have maintenance coming due, due, or overdue. Each links to its page, where the work is started and completed.') }}
    </p>

    @foreach($groups as $group)
        @php $equipment = $group['equipment']; $worst = $group['rows']->pluck('state')->contains('overdue') ? 'overdue' : ($group['rows']->pluck('state')->contains('due') ? 'due' : 'upcoming'); @endphp
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: {{ $worst === 'overdue' ? '#fef2f2' : ($worst === 'due' ? '#fff7ed' : '#fffbeb') }}; border-radius: 6px; border: 1px solid {{ $worst === 'overdue' ? '#fecaca' : ($worst === 'due' ? '#fed7aa' : '#fde68a') }}; margin-bottom: 12px;">
            <tr>
                <td style="padding: 12px 16px; font-size: 14px;">
                    @if($equipment)
                        <a href="{{ route('equipment.show', ['equipment' => $equipment->id, 'tab' => 'maintenance']) }}" style="color: #3F5189; font-weight: 600; text-decoration: none;">{{ $equipment->name }}</a>
                        @if($equipment->asset_tag || $equipment->plate)<span style="color: #999;">&middot; {{ $equipment->asset_tag ?? $equipment->plate }}</span>@endif
                        @if($equipment->project)<span style="color: #999;">&middot; {{ $equipment->project->project_name }}@if($equipment->jobSite) / {{ $equipment->jobSite->job_site_name }}@endif</span>@endif
                    @else
                        <span style="color: #555; font-weight: 600;">{{ __('Unknown equipment') }}</span>
                    @endif
                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 8px; font-size: 13px;">
                        @foreach($group['rows'] as $row)
                            @php $m = $row['maintenance']; @endphp
                            <tr>
                                <td style="padding: 4px 0; color: #555; border-top: 1px solid rgba(0,0,0,0.05);">
                                    {{ $m->title }}
                                    <span style="color: #999;">&middot; {{ $m->getTypeLabel() }}</span>
                                </td>
                                <td style="padding: 4px 0; color: #555; text-align: right; white-space: nowrap; border-top: 1px solid rgba(0,0,0,0.05);">
                                    {{ $m->dueLabel() }}
                                    <span style="color: {{ $row['state'] === 'overdue' ? '#b91c1c' : ($row['state'] === 'due' ? '#c2410c' : '#b45309') }};">
                                        &middot;
                                        @if($row['state'] === 'overdue')
                                            {{ trans_choice('Overdue by :count day|Overdue by :count days', abs($m->daysUntilDue()), ['count' => abs($m->daysUntilDue())]) }}
                                        @elseif($row['state'] === 'due')
                                            {{ $m->isDueByMeter() ? __('Meter reached') : __('Due today') }}
                                        @elseif($m->daysUntilDue() !== null)
                                            {{ trans_choice('Due in :count day|Due in :count days', $m->daysUntilDue(), ['count' => $m->daysUntilDue()]) }}
                                        @else
                                            {{ __(':amount to go', ['amount' => $equipment?->formatMeter($m->meterRemaining()) ?? '']) }}
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>
    @endforeach

    <p style="margin: 18px 0 0; text-align: center;">
        <a href="{{ route('equipment.maintenance.index', ['view' => $hasOverdue ? 'overdue' : 'due_soon']) }}" style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
            {{ __('Open the Maintenance List') }}
        </a>
    </p>

    <p style="margin: 18px 0 0; font-size: 12px; color: #999; line-height: 1.5;">
        {{ __('You receive this because you were chosen for equipment maintenance reminders in System Settings, or because you may maintain equipment. Each maintenance is mentioned once per stage: 30 and 7 days before its date, the day it is due, and the day after.') }}
    </p>
</x-email-shell>
