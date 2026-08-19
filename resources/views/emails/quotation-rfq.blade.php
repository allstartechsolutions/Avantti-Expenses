<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Quotation Request') }} {{ $quotation->quotation_number }}</title>
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
                        </td>
                    </tr>

                    <!-- What is being asked for -->
                    <tr>
                        <td style="padding: 0 30px 20px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h3 style="margin: 0 0 12px; color: #3F5189; font-size: 16px;">{{ __('Request Summary') }}</h3>
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding: 4px 0; font-size: 13px; color: #666;">{{ __('Reference') }}</td>
                                                <td style="padding: 4px 0; font-size: 13px; color: #333; text-align: right; font-weight: bold;">{{ $quotation->quotation_number }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; font-size: 13px; color: #666;">{{ __('Type') }}</td>
                                                <td style="padding: 4px 0; font-size: 13px; color: #333; text-align: right;">{{ $quotation->getTypeLabel() }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; font-size: 13px; color: #666;">{{ __('Responses Due') }}</td>
                                                <td style="padding: 4px 0; font-size: 13px; color: #333; text-align: right;">
                                                    {{ $quotation->responses_due_at?->format('M d, Y') ?? __('As soon as possible') }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; font-size: 13px; color: #666;">{{ __('Items') }}</td>
                                                <td style="padding: 4px 0; font-size: 13px; color: #333; text-align: right;">{{ $quotation->items->count() }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- The scope, so it is readable without opening the PDF -->
                    <tr>
                        <td style="padding: 0 30px 20px;">
                            <h3 style="margin: 0 0 10px; color: #3F5189; font-size: 16px;">{{ __('Scope') }}</h3>
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                                <tr>
                                    <th align="left" style="padding: 6px; font-size: 12px; color: #666; border-bottom: 1px solid #e9ecef;">{{ __('Item') }}</th>
                                    <th align="right" style="padding: 6px; font-size: 12px; color: #666; border-bottom: 1px solid #e9ecef;">{{ __('Qty') }}</th>
                                    <th align="left" style="padding: 6px; font-size: 12px; color: #666; border-bottom: 1px solid #e9ecef;">{{ __('Unit') }}</th>
                                </tr>
                                @foreach($quotation->items as $item)
                                    <tr>
                                        <td style="padding: 6px; font-size: 13px; color: #333; border-bottom: 1px solid #f1f3f5;">
                                            {{ $item->item_name }}
                                            @if($item->description)
                                                <div style="font-size: 11px; color: #888;">{{ $item->description }}</div>
                                            @endif
                                        </td>
                                        <td align="right" style="padding: 6px; font-size: 13px; color: #333; border-bottom: 1px solid #f1f3f5;">
                                            {{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}
                                        </td>
                                        <td style="padding: 6px; font-size: 13px; color: #333; border-bottom: 1px solid #f1f3f5;">{{ $item->unit ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </table>
                            <p style="margin: 12px 0 0; font-size: 12px; color: #888;">
                                {{ __('The attached PDF has the same list with columns for your prices.') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0; font-size: 12px; color: #888;">
                                {{ __('Reply to this e-mail with your proposal attached.') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
