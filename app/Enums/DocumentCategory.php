<?php

namespace App\Enums;

/**
 * The fixed classification a repository document carries. Free tags cover
 * anything finer; this list exists so the repository can be filtered and
 * summarised consistently across every project.
 */
enum DocumentCategory: string
{
    case PLANS = 'plans';
    case PERMITS = 'permits';
    case CONTRACTS = 'contracts';
    case SUBMITTALS = 'submittals';
    case RFI = 'rfi';
    case SAFETY = 'safety';
    case PHOTOS = 'photos';
    case REPORTS = 'reports';
    case INVOICES = 'invoices';
    case CORRESPONDENCE = 'correspondence';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PLANS => 'Plans & Drawings',
            self::PERMITS => 'Permits & Licenses',
            self::CONTRACTS => 'Contracts',
            self::SUBMITTALS => 'Submittals & Shop Drawings',
            self::RFI => 'RFIs',
            self::SAFETY => 'Safety',
            self::PHOTOS => 'Photos',
            self::REPORTS => 'Reports',
            self::INVOICES => 'Invoices & Financial',
            self::CORRESPONDENCE => 'Correspondence',
            self::OTHER => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PLANS => 'blue',
            self::PERMITS => 'purple',
            self::CONTRACTS => 'indigo',
            self::SUBMITTALS => 'cyan',
            self::RFI => 'amber',
            self::SAFETY => 'red',
            self::PHOTOS => 'pink',
            self::REPORTS => 'green',
            self::INVOICES => 'emerald',
            self::CORRESPONDENCE => 'orange',
            self::OTHER => 'gray',
        };
    }

    /**
     * Heroicon outline path data, matching the icons used across the nav.
     */
    public function icon(): string
    {
        return match ($this) {
            self::PLANS => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
            self::PERMITS => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z',
            self::CONTRACTS => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            self::SUBMITTALS => 'M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4',
            self::RFI => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            self::SAFETY => 'M9 12l2 2 4-4M12 3l7 4v5c0 4.418-2.865 8.418-7 9.5C7.865 20.418 5 16.418 5 12V7l7-4z',
            self::PHOTOS => 'M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z M15 13a3 3 0 11-6 0 3 3 0 016 0z',
            self::REPORTS => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            self::INVOICES => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
            self::CORRESPONDENCE => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            self::OTHER => 'M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z',
        };
    }

    /**
     * @return array<string, string> value => untranslated label
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
