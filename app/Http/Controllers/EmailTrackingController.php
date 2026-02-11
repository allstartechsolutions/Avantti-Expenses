<?php

namespace App\Http\Controllers;

use App\Models\EstimateEmail;
use App\Models\InvoiceEmail;
use Illuminate\Http\Response;

class EmailTrackingController extends Controller
{
    public function track(string $token): Response
    {
        $email = EstimateEmail::where('tracking_token', $token)->first()
            ?? InvoiceEmail::where('tracking_token', $token)->first();

        if ($email && !$email->opened_at) {
            $email->update(['opened_at' => now()]);
        }

        // 1x1 transparent GIF
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen($pixel),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
