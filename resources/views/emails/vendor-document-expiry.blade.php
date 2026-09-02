
<x-email-shell :heading="__('Vendor Documents')">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        {{ __('These subcontractor documents have reached a reminder stage. Each vendor below links to its page, where the document can be renewed or archived.') }}
    </p>

    @foreach([['groups' => $expiredGroups, 'expired' => true], ['groups' => $expiringGroups, 'expired' => false]] as $section)
        @if($section['groups']->isNotEmpty())
            <p style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: {{ $section['expired'] ? '#b91c1c' : '#b45309' }};">
                {{ $section['expired'] ? __('Documents expired') : __('Documents expiring soon') }}
            </p>

            @foreach($section['groups'] as $group)
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: {{ $section['expired'] ? '#fef2f2' : '#fffbeb' }}; border-radius: 6px; border: 1px solid {{ $section['expired'] ? '#fecaca' : '#fde68a' }}; margin-bottom: 12px;">
                    <tr>
                        <td style="padding: 12px 16px; font-size: 14px;">
                            @if($group['vendor'])
                                <a href="{{ route('subcontractors.show', $group['vendor']->id) }}" style="color: #3F5189; font-weight: 600; text-decoration: none;">{{ $group['vendor']->name }}</a>
                            @else
                                <span style="color: #555; font-weight: 600;">{{ __('Unknown vendor') }}</span>
                            @endif
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 8px; font-size: 13px;">
                                @foreach($group['rows'] as $row)
                                    @php $document = $row['document']; $days = $row['days']; @endphp
                                    <tr>
                                        <td style="padding: 4px 0; color: #555; border-top: 1px solid rgba(0,0,0,0.05);">
                                            {{ __($document->documentType->name) }}
                                            <span style="color: #999;">&middot; {{ $document->file_name }}</span>
                                        </td>
                                        <td style="padding: 4px 0; color: #555; text-align: right; white-space: nowrap; border-top: 1px solid rgba(0,0,0,0.05);">
                                            {{ $document->expiration_date->appDate() }}
                                            <span style="color: {{ $section['expired'] ? '#b91c1c' : '#b45309' }};">
                                                &middot;
                                                @if($days === 0)
                                                    {{ __('Expires today') }}
                                                @elseif($days > 0)
                                                    {{ trans_choice('Expires in :count day|Expires in :count days', $days, ['count' => $days]) }}
                                                @else
                                                    {{ trans_choice('Expired :count day ago|Expired :count days ago', abs($days), ['count' => abs($days)]) }}
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
        @endif
    @endforeach

    <p style="margin: 18px 0 0; text-align: center;">
        <a href="{{ route('subcontractors.index', ['documents' => $expiredGroups->isNotEmpty() ? 'expired' : 'expiring_soon']) }}" style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 11px 22px; border-radius: 6px; font-size: 14px;">
            {{ __('Open the Subcontractors List') }}
        </a>
    </p>

    <p style="margin: 18px 0 0; font-size: 12px; color: #999; line-height: 1.5;">
        {{ __('You receive this because you were chosen for vendor document reminders in System Settings, or because you may upload and renew vendor documents. Each document is mentioned once per stage: 30, 15 and 7 days before its date, and the day after.') }}
    </p>
</x-email-shell>
