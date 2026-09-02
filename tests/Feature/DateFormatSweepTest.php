<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

/**
 * How this install writes a date is decided in one place.
 *
 * Before the sweep of 31 Aug 2026 every screen decided for itself, and a
 * Brazilian install showed `Aug 31, 2026` on 144 of them — US order and
 * English month names, which no locale setting fixes because `format()` never
 * translates. The macros in `AppServiceProvider::registerDateMacros()` are the
 * single answer now; this fails if a hardcoded one comes back.
 */
class DateFormatSweepTest extends TestCase
{
    /**
     * Formats a person reads, which must not be written out by hand.
     *
     * Machine formats are deliberately absent: `Y-m-d` fills a date input,
     * `Y-m` is a grouping key, `G` is an hour of the day. None of them are
     * read by a person and none may move when the country does.
     *
     * @return array<int, string>
     */
    protected function forbidden(): array
    {
        return [
            "format('M d, Y')", "format('M d, Y H:i')", "format('M d, Y h:i A')",
            "format('M d, Y g:i A')", "format('M d, Y - h:i A')", "format('M d')",
            "format('F d, Y')", "format('m/d/Y')", "format('m/d/Y g:i A')",
            "format('d/m/Y')", "format('d/m/Y H:i')", "format('d/m/Y - H:i')",
            "format('g:i A')", "format('l n/j/Y')",
        ];
    }

    /**
     * A Blade directive inside a component tag stops the tag compiling at all.
     *
     * `<x-ui.date-input @disabled($flag) …>` is not a component as far as
     * Blade is concerned: the tag is left in the output as literal text, the
     * browser makes nothing of the unknown element, and the field simply is
     * not there. It reached the expense form's payment section that way —
     * silently, because nothing errors. `:disabled="$flag"` is the form a
     * component takes.
     */
    public function test_no_component_tag_carries_a_blade_directive(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('resources/views')));

        foreach ($files as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            preg_match_all('/<x-[a-zA-Z0-9.\-]+\b[^>]*?\/?>/s', $contents, $tags);

            foreach ($tags[0] as $tag) {
                if (preg_match('/@(disabled|checked|readonly|required|selected|class|style)\(/', $tag)) {
                    $offenders[] = str_replace(base_path().'/', '', $file->getPathname())
                        .' — '.implode(' ', array_slice(preg_split('/\s+/', trim($tag)), 0, 3));
                }
            }
        }

        $this->assertSame([], $offenders, "Use :disabled=\"\$flag\" on a component, not @disabled(...) — the tag does not compile otherwise:\n".implode("\n", $offenders));
    }

    /** And no screen goes back to a native date input, which reads the browser's locale. */
    public function test_no_screen_uses_a_native_date_input(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('resources/views')));

        foreach ($files as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            // The component itself keeps one, hidden, for its picker.
            if (str_ends_with($file->getPathname(), 'ui/date-input.blade.php')) {
                continue;
            }

            if (str_contains(file_get_contents($file->getPathname()), 'type="date"')) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, "Use <x-ui.date-input> — a native date input renders in the browser's locale, not this install's:\n".implode("\n", $offenders));
    }

    public function test_no_screen_writes_a_date_format_by_hand(): void
    {
        $offenders = [];

        foreach ([base_path('app'), base_path('resources/views')] as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if ($file->isDir() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                // The provider is where the formats are allowed to be written.
                if (str_ends_with($file->getPathname(), 'AppServiceProvider.php')) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                foreach ($this->forbidden() as $needle) {
                    if (str_contains($contents, $needle)) {
                        $offenders[] = str_replace(base_path().'/', '', $file->getPathname())." — {$needle}";
                    }
                }

                // The same habit in its other costume: a country ternary handed
                // to format(). Eleven of these sat under the sweep until 2 Sep
                // 2026 because the format string was built, not written.
                if (preg_match("/->format\\(\\s*config\\('app\\.country'\\)/", $contents)) {
                    $offenders[] = str_replace(base_path().'/', '', $file->getPathname())." — format(config('app.country') … ? … : …)";
                }
            }
        }

        $this->assertSame([], $offenders, "Use ->appDate(), ->appDateTime(), ->appTime(), ->appDateLong() or ->appDateShort() instead:\n".implode("\n", $offenders));
    }

    public function test_the_macros_write_each_country_the_way_it_reads(): void
    {
        $moment = Carbon::parse('2026-08-31 14:30');

        config(['app.country' => 'BR']);
        $this->app->setLocale('pt_BR');

        // The month is a word, and the word is Portuguese.
        $this->assertSame('31 ago 2026', $moment->appDate());
        $this->assertSame('14:30', $moment->appTime());
        $this->assertSame('31 ago 2026 14:30', $moment->appDateTime());
        $this->assertSame('31 ago', $moment->appDateShort());
        $this->assertStringContainsString('agosto', $moment->appDateLong());

        // The numeric form exists for the date input and nothing else.
        $this->assertSame('31/08/2026', $moment->appDateNumeric());

        config(['app.country' => 'US']);
        $this->app->setLocale('en');

        $this->assertSame('Aug 31, 2026', $moment->appDate());
        $this->assertSame('2:30 PM', $moment->appTime());
        $this->assertSame('Aug 31, 2026 2:30 PM', $moment->appDateTime());
        $this->assertSame('Aug 31', $moment->appDateShort());
        $this->assertSame('August 31, 2026', $moment->appDateLong());
        $this->assertSame('08/31/2026', $moment->appDateNumeric());
    }
}
