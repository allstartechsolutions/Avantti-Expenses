<?php

namespace App\Services;

/**
 * Reads a browser's user-agent string into the three things a person
 * recognises: browser, operating system and kind of device. Deliberately
 * small — no package — and good enough for "Chrome on Windows" and "Safari
 * on iPhone"; anything unrecognised is left null rather than guessed.
 */
class UserAgent
{
    /** @return array{browser: ?string, platform: ?string, device: ?string} */
    public static function parse(?string $agent): array
    {
        $agent = (string) $agent;

        if ($agent === '') {
            return ['browser' => null, 'platform' => null, 'device' => null];
        }

        // Order matters: Edge and Opera say "Chrome", Chrome says "Safari".
        $browser = match (true) {
            (bool) preg_match('/Edg(e|A|iOS)?\//', $agent) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/', $agent) => 'Opera',
            (bool) preg_match('/SamsungBrowser\//', $agent) => 'Samsung Internet',
            (bool) preg_match('/Firefox\/|FxiOS\//', $agent) => 'Firefox',
            (bool) preg_match('/Chrome\/|CriOS\//', $agent) => 'Chrome',
            (bool) preg_match('/Safari\//', $agent) && str_contains($agent, 'Version/') => 'Safari',
            default => null,
        };

        $platform = match (true) {
            (bool) preg_match('/iPhone/', $agent) => 'iPhone',
            (bool) preg_match('/iPad/', $agent) => 'iPad',
            (bool) preg_match('/Android/', $agent) => 'Android',
            (bool) preg_match('/Windows/', $agent) => 'Windows',
            (bool) preg_match('/Mac OS X|Macintosh/', $agent) => 'macOS',
            (bool) preg_match('/CrOS/', $agent) => 'ChromeOS',
            (bool) preg_match('/Linux/', $agent) => 'Linux',
            default => null,
        };

        $device = match (true) {
            (bool) preg_match('/iPad|Tablet/', $agent) || ($platform === 'Android' && ! str_contains($agent, 'Mobile')) => 'tablet',
            (bool) preg_match('/Mobile|iPhone/', $agent) => 'mobile',
            $platform !== null => 'desktop',
            default => null,
        };

        return ['browser' => $browser, 'platform' => $platform, 'device' => $device];
    }

    /**
     * Same browser, same system, same network (the IPv4 /24 or the IPv6 /48):
     * what "a device we have seen before" means for the new-device alert.
     */
    public static function fingerprint(?string $browser, ?string $platform, ?string $ip): string
    {
        return sha1(implode('|', [$browser, $platform, self::network($ip)]));
    }

    public static function network(?string $ip): string
    {
        if (! $ip) {
            return '';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_slice(explode('.', $ip), 0, 3));
        }

        // IPv6 /48: the first six bytes of the address, whatever its written form
        // ("2001:db8::1" and "2001:db8:0:1::1" are the same network).
        $binary = @inet_pton($ip);

        return $binary !== false && strlen($binary) === 16 ? bin2hex(substr($binary, 0, 6)) : $ip;
    }
}
