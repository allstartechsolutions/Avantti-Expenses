<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estimate {{ $estimate->estimate_number }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f5f7; color: #333;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f5f7; padding: 30px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #3F5189; padding: 25px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: bold;">
                                {{ \App\Models\Company::first()?->name ?? config('app.name') }}
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 30px;">
                            <div style="font-size: 14px; line-height: 1.6; color: #555;">
                                {!! App\Support\RichText::sanitize($emailBody) !!}
                            </div>
                        </td>
                    </tr>

                    <!-- Estimate Summary -->
                    <tr>
                        <td style="padding: 0 30px 30px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h3 style="margin: 0 0 12px; color: #3F5189; font-size: 16px;">{{ __('Estimate Summary') }}</h3>
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding: 4px 0; font-size: 13px; color: #666;">{{ __('Estimate Number:') }}</td>
                                                <td style="padding: 4px 0; font-size: 13px; color: #333; text-align: right; font-weight: bold;">{{ $estimate->estimate_number }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; font-size: 13px; color: #666;">{{ __('Date:') }}</td>
                                                <td style="padding: 4px 0; font-size: 13px; color: #333; text-align: right;">{{ $estimate->estimate_date->format('M d, Y') }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; font-size: 13px; color: #666;">{{ __('Due Date:') }}</td>
                                                <td style="padding: 4px 0; font-size: 13px; color: #333; text-align: right;">{{ $estimate->due_date->format('M d, Y') }}</td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" style="padding: 8px 0 0;">
                                                    <div style="border-top: 1px solid #dee2e6;"></div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0 4px; font-size: 15px; color: #3F5189; font-weight: bold;">{{ __('Total:') }}</td>
                                                <td style="padding: 8px 0 4px; font-size: 15px; color: #3F5189; text-align: right; font-weight: bold;">${{ number_format($estimate->total_amount, 2) }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <p style="font-size: 12px; color: #999; margin-top: 15px; text-align: center;">
                                {{ __('The full estimate is attached as a PDF.') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0; font-size: 12px; color: #999;">
                                {{ \App\Models\Company::first()?->name ?? config('app.name') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    @if(!empty($trackingToken))
        <img src="{{ route('email.track', $trackingToken) }}" width="1" height="1" alt="" style="display:none;border:0;" />
    @endif
</body>
</html>
