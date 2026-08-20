@props(['heading' => null, 'accent' => '#3F5189'])

{{--
    The frame every task e-mail sits in. Written once so the four of them stay
    the same shape as each other and as the estimate and invoice mails.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $heading ?? config('app.name') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f5f7; color: #333;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f5f7; padding: 30px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background-color: {{ $accent }}; padding: 22px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: bold;">
                                {{ \App\Models\Company::first()?->name ?? config('app.name') }}
                            </h1>
                            @if($heading)
                                <p style="margin: 5px 0 0; color: rgba(255,255,255,0.85); font-size: 13px;">{{ $heading }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 28px 30px 10px;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 30px 24px; text-align: center; font-size: 11px; color: #999;">
                            {{ __('You are receiving this because you are on this task. You can turn these e-mails off in your profile.') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
