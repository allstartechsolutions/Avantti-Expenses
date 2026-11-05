<x-email-shell :heading="__('Security Alert')">
    <p style="margin: 0 0 14px; font-size: 15px;">{{ __('Hello :name,', ['name' => $recipient->name]) }}</p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        @if($kind === 'lockout')
            {{ __('An account was locked out after too many wrong passwords in a minute. If it was not the person themselves, somebody may be guessing the password.') }}
        @elseif($kind === 'spraying')
            {{ __('One address tried :count different accounts in the last 15 minutes. That usually means somebody is trying passwords against several people at once.', ['count' => $accounts]) }}
        @else
            {{ __('Somebody signed in from a browser or network this account has not used before. If the person does not recognise it, their password should be changed and the session ended.') }}
        @endif
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 13px; margin: 0 0 18px;">
        @foreach(array_filter([
            __('Account') => $event->user ? $event->user->name.' ('.$event->user->email.')' : $event->email,
            __('When') => $event->created_at->appDateTime(),
            __('IP address') => $event->ip_address,
            __('Device') => $event->getDeviceLabel(),
        ]) as $label => $value)
            <tr>
                <td style="padding: 6px 8px; border-bottom: 1px solid #eee; color: #777; width: 30%;">{{ $label }}</td>
                <td style="padding: 6px 8px; border-bottom: 1px solid #eee; color: #222;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin: 18px 0 0; text-align: center;">
        <a href="{{ route('login-activity.index', $kind === 'spraying' ? ['search' => $event->ip_address] : ['user' => $event->user_id]) }}" style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600;">
            {{ __('Open Login Activity') }}
        </a>
    </p>

    <p style="margin: 18px 0 0; font-size: 12px; color: #999; line-height: 1.5;">
        {{ __('You receive this because you may see Login Activity. Switch it off under Settings → Notifications.') }}
    </p>
</x-email-shell>
