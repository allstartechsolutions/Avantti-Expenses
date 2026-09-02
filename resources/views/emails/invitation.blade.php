<x-email-shell :heading="__('Access')">
    <p style="margin: 0 0 14px; font-size: 15px;">
        {{ $invitation->name ? __('Hello :name,', ['name' => $invitation->name]) : __('Hello,') }}
    </p>

    <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #555;">
        @if($invitedBy)
            {{ __(':who has given you access to :company.', ['who' => $invitedBy->name, 'company' => App\Services\Branding::name()]) }}
        @else
            {{ __('You have been given access to :company.', ['company' => App\Services\Branding::name()]) }}
        @endif
        {{ __('Choose a password and you are in.') }}
    </p>

    @if(count($places))
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef; margin-bottom: 18px;">
            <tr>
                <td style="padding: 14px 16px; font-size: 14px; color: #555;">
                    {{ trans_choice('{1} You have been added to:|[2,*] You have been added to:', count($places)) }}
                    <div style="margin-top: 6px; color: #3F5189; font-weight: 600;">{{ implode(' · ', $places) }}</div>
                </td>
            </tr>
        </table>
    @endif

    <table cellpadding="0" cellspacing="0" style="margin: 0 0 18px;">
        <tr>
            <td style="background-color: #3F5189; border-radius: 6px;">
                <a href="{{ $url }}" style="display: inline-block; padding: 12px 24px; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600;">
                    {{ __('Set up my account') }}
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 8px; font-size: 13px; color: #777;">
        {{ __('The link works once and stops working on :date.', ['date' => $invitation->expires_at?->appDate()]) }}
    </p>
    <p style="margin: 0; font-size: 13px; color: #777;">
        {{ __('If you were not expecting this, ignore it — no account is created until somebody uses the link.') }}
    </p>
</x-email-shell>
