<?php

namespace App\Models\Concerns;

/**
 * Adds a formatted_phone accessor that formats the model's phone
 * attribute for display based on the configured country.
 */
trait HasFormattedPhone
{
    public function getFormattedPhoneAttribute(): ?string
    {
        return static::formatPhone($this->phone);
    }

    /**
     * Format a phone number for display. In US mode, 10-digit numbers
     * (or 11 digits with a leading 1) become (XXX) XXX-XXXX; anything
     * else is returned as typed.
     */
    public static function formatPhone(?string $phone): ?string
    {
        if (!$phone || config('app.country') !== 'US') {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10) {
            return $phone;
        }

        return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
    }
}
