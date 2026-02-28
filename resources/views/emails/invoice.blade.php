<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
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
                                {!! $emailBody !!}
                            </div>
                            @if(app(\App\Services\CardPointeService::class)->isConfigured() && $invoice->getBalanceDue() > 0)
                                <div style="text-align: center; margin: 25px 0 15px;">
                                    <a href="{{ route('invoice.pay', $invoice->payment_token) }}"
                                       style="display: inline-block; background-color: #3F5189; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: bold; padding: 14px 40px; border-radius: 6px;">
                                        Pay Online
                                    </a>
                                </div>
                            @endif
                            <p style="font-size: 12px; color: #999; margin-top: 15px; text-align: center;">
                                The full invoice is attached as a PDF.
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
