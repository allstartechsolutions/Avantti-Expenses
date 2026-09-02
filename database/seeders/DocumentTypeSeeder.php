<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * The starting set of vendor document types for this install's country.
 *
 * **Add-only.** Production holds documents filed under these rows, and the
 * Document Types screen lets an administrator rename, reorder and retire
 * them, so running this again must never overwrite what they did. Each
 * seeded row is found by its stable `key` — never by the name, which the
 * screen can change — and a row that exists is left exactly as it is. A type
 * an install does not want is retired on the screen, not deleted here.
 *
 * Rows seeded before the key existed are matched once by their original
 * name and given their key, so a renamed row is never duplicated afterwards.
 *
 * Names are stored as written and translated on display through `__()`; the
 * lang files carry every seeded name.
 */
class DocumentTypeSeeder extends Seeder
{
    public function run(?string $country = null): void
    {
        // The table's own migration seeds it before the key column exists on
        // a database built from scratch; 2026_09_02_130004 adds the column and
        // runs this again, claiming those rows by name.
        if (! Schema::hasColumn('document_types', 'key')) {
            foreach (self::typesFor($country ?? config('app.country', 'US')) as $type) {
                DocumentType::firstOrCreate(['name' => $type['name']], collect($type)->except('key')->all());
            }

            return;
        }

        // First, claim every row seeded before the key existed — from either
        // list, because a Brazilian install set up with the American list
        // holds American rows that need their keys too.
        foreach (array_merge(self::typesFor('US'), self::typesFor('BR')) as $type) {
            if (DocumentType::where('key', $type['key'])->exists()) {
                continue;
            }

            DocumentType::whereNull('key')->where('name', $type['name'])->first()?->update(['key' => $type['key']]);
        }

        // Then add what this country's list is still missing.
        foreach (self::typesFor($country ?? config('app.country', 'US')) as $type) {
            if (! DocumentType::where('key', $type['key'])->exists()) {
                DocumentType::create($type);
            }
        }
    }

    /**
     * Retire the *other* country's seeded types when nothing was ever filed
     * under them. A Brazilian install set up with the American list is left
     * with W9 and Workers Compensation on every picker otherwise. A type that
     * holds documents is kept active — retiring it would pull a document out
     * of the watch behind the owner's back — and the screen can retire it.
     *
     * Run **once**, from 2026_09_02_130006: it does not know about a type the
     * owner reactivated on purpose, so it must not run on every deploy.
     *
     * @return int  how many were retired
     */
    public static function retireForeignUnused(?string $country = null): int
    {
        $country = $country ?? config('app.country', 'US');
        $foreign = $country === 'BR' ? 'us.' : 'br.';

        return DocumentType::query()
            ->where('key', 'like', $foreign.'%')
            ->where('is_active', true)
            ->whereDoesntHave('documents')
            ->update(['is_active' => false]);
    }

    /**
     * @return array<int, array{key:string, name:string, description:string, requires_expiration:bool, sort_order:int}>
     */
    public static function typesFor(string $country): array
    {
        return $country === 'BR' ? self::brazil() : self::unitedStates();
    }

    protected static function unitedStates(): array
    {
        return [
            ['key' => 'us.w9', 'name' => 'W9', 'description' => 'IRS Form W-9 (Request for Taxpayer Identification Number)', 'requires_expiration' => false, 'sort_order' => 1],
            ['key' => 'us.general_liability_insurance', 'name' => 'General Liability Insurance', 'description' => 'Commercial General Liability (CGL) insurance certificate', 'requires_expiration' => true, 'sort_order' => 2],
            ['key' => 'us.workers_compensation_insurance', 'name' => 'Workers Compensation Insurance', 'description' => 'Workers compensation insurance certificate', 'requires_expiration' => true, 'sort_order' => 3],
            ['key' => 'us.coi', 'name' => 'Certificate of Insurance (COI)', 'description' => 'Certificate of Insurance document', 'requires_expiration' => true, 'sort_order' => 4],
            ['key' => 'us.business_license', 'name' => 'Business License', 'description' => 'Business license or registration', 'requires_expiration' => true, 'sort_order' => 5],
            ['key' => 'us.contractor_license', 'name' => 'Contractor License', 'description' => 'State or local contractor license', 'requires_expiration' => true, 'sort_order' => 6],
            ['key' => 'us.auto_insurance', 'name' => 'Auto Insurance', 'description' => 'Commercial auto insurance certificate', 'requires_expiration' => true, 'sort_order' => 7],
            ['key' => 'other', 'name' => 'Other', 'description' => 'Other documents', 'requires_expiration' => false, 'sort_order' => 99],
        ];
    }

    /**
     * The certidões a Brazilian contractor asks a subcontractor for before
     * and during a job — confirmed with the owner on 2 Sep 2026.
     */
    protected static function brazil(): array
    {
        return [
            ['key' => 'br.cnd_federal', 'name' => 'CND Federal', 'description' => 'Certidão Negativa de Débitos relativos a Tributos Federais e à Dívida Ativa da União', 'requires_expiration' => true, 'sort_order' => 1],
            ['key' => 'br.cnd_estadual', 'name' => 'CND Estadual', 'description' => 'Certidão Negativa de Débitos Estaduais', 'requires_expiration' => true, 'sort_order' => 2],
            ['key' => 'br.cnd_municipal', 'name' => 'CND Municipal', 'description' => 'Certidão Negativa de Débitos Municipais', 'requires_expiration' => true, 'sort_order' => 3],
            ['key' => 'br.fgts', 'name' => 'CRF do FGTS', 'description' => 'Certificado de Regularidade do FGTS', 'requires_expiration' => true, 'sort_order' => 4],
            ['key' => 'br.cndt', 'name' => 'CNDT', 'description' => 'Certidão Negativa de Débitos Trabalhistas', 'requires_expiration' => true, 'sort_order' => 5],
            ['key' => 'br.alvara_funcionamento', 'name' => 'Alvará de Funcionamento', 'description' => 'Alvará de localização e funcionamento emitido pelo município', 'requires_expiration' => true, 'sort_order' => 6],
            ['key' => 'br.contrato_social', 'name' => 'Contrato Social', 'description' => 'Contrato social consolidado ou requerimento de empresário', 'requires_expiration' => false, 'sort_order' => 7],
            ['key' => 'br.seguro_rc', 'name' => 'Apólice de Seguro RC', 'description' => 'Apólice de seguro de responsabilidade civil', 'requires_expiration' => true, 'sort_order' => 8],
            ['key' => 'br.pcmso_pgr', 'name' => 'PCMSO/PGR', 'description' => 'Programa de Controle Médico de Saúde Ocupacional e Programa de Gerenciamento de Riscos', 'requires_expiration' => true, 'sort_order' => 9],
            ['key' => 'other', 'name' => 'Other', 'description' => 'Other documents', 'requires_expiration' => false, 'sort_order' => 99],
        ];
    }
}
