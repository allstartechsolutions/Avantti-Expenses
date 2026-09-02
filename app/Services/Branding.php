<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Where every mark on the screen comes from.
 *
 * The product ships with its own icon and name (`config('app.logo_url')`,
 * `config('app.name')`). A customer may replace either on the Company
 * Information screen; anything they leave empty falls back to the product's,
 * so an install that uploads nothing looks exactly as it did before.
 *
 * This is read on the login page, before a session exists and on every single
 * request afterwards, so it must be cheap and it must never be the reason a
 * page fails: the row is cached and every read is wrapped — a database that is
 * unreachable, or a `companies` table that does not exist yet during an
 * install, yields the product defaults rather than an exception.
 */
class Branding
{
    /** Bumped when the shape of the cached array changes. */
    private const CACHE_KEY = 'branding.v1';

    /** Per-request memo, so a page with a header, a sidebar and a footer reads once. */
    private static ?array $memo = null;

    /**
     * The stored values, or an empty set when there is no company row yet.
     *
     * @return array{brand_name: ?string, app_icon: ?string, app_icon_dark: ?string, favicon: ?string, name: ?string, version: ?string}
     */
    public static function values(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $empty = [
            'brand_name' => null,
            'app_icon' => null,
            'app_icon_dark' => null,
            'favicon' => null,
            'name' => null,
            'version' => null,
        ];

        try {
            self::$memo = Cache::rememberForever(self::CACHE_KEY, function () use ($empty) {
                $company = Company::query()
                    ->select(['name', 'brand_name', 'app_icon', 'app_icon_dark', 'favicon', 'updated_at'])
                    ->first();

                if (! $company) {
                    return $empty;
                }

                return [
                    'brand_name' => $company->brand_name,
                    'app_icon' => $company->app_icon,
                    'app_icon_dark' => $company->app_icon_dark,
                    'favicon' => $company->favicon,
                    'name' => $company->name,
                    // Cache buster: a replaced favicon is otherwise pinned in the
                    // browser for weeks, which reads as "the upload did nothing".
                    'version' => $company->updated_at?->timestamp,
                ];
            });
        } catch (Throwable) {
            // No database, no table, no cache table — the login page still renders.
            self::$memo = $empty;
        }

        return self::$memo;
    }

    /**
     * The name shown beside the icon and in the browser tab: the short brand
     * name if one was given, else the company's own name, else the product's.
     */
    public static function name(): string
    {
        $values = self::values();

        return $values['brand_name'] ?: ($values['name'] ?: config('app.name'));
    }

    /** The square mark for the header, sidebar, login card and e-mails. */
    public static function iconUrl(): string
    {
        return self::url(self::values()['app_icon']) ?? config('app.logo_url');
    }

    /**
     * The dark-mode twin, or null when none was uploaded — the views need to
     * know the difference so they render one image instead of two.
     */
    public static function darkIconUrl(): ?string
    {
        return self::url(self::values()['app_icon_dark']);
    }

    /** The browser-tab icon. */
    public static function faviconUrl(): string
    {
        return self::url(self::values()['favicon']) ?? config('app.logo_url');
    }

    /** The `type` for the favicon link, which follows the file that was uploaded. */
    public static function faviconType(): string
    {
        $favicon = self::values()['favicon'];

        if ($favicon && str_ends_with(strtolower($favicon), '.ico')) {
            return 'image/x-icon';
        }

        return 'image/png';
    }

    /** True when this install has replaced the product's own icon. */
    public static function hasCustomIcon(): bool
    {
        return (bool) self::values()['app_icon'];
    }

    /**
     * Called whenever a company row is written. Clears both the stored cache
     * and this request's memo, so the screen that did the uploading shows the
     * new mark immediately rather than after the next deploy.
     */
    public static function forget(): void
    {
        self::$memo = null;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // A cache that cannot be cleared is not worth failing a save over.
        }
    }

    /** An absolute, cache-busted URL for a stored path, or null when unset or missing. */
    private static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $url = Storage::disk('public')->url($path);
        $version = self::values()['version'];

        return $version ? $url.'?v='.$version : $url;
    }
}
