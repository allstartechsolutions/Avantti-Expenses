<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * The starting set of company-expense categories for this install's country.
 *
 * **Add-only.** Production holds expenses filed under these rows, and the
 * Expense Categories screen lets an administrator rename, recode, reorder
 * and retire them, so running this again must never overwrite what they
 * did. Each seeded row is found by its stable `key` — never by the name or
 * the code, which the screen can change — and a row that exists is left
 * exactly as it is. A category an install does not want is retired on the
 * screen, not deleted here.
 *
 * The codes follow the usual chart-of-accounts convention of a 6xxx range
 * for operating overhead, shared between the two countries so a Brazilian
 * and an American install line up. Should an administrator have already
 * taken a seeded code for a category of their own, the seeded row gets a
 * generated code instead — their bookkeeping wins.
 *
 * Names are stored in English and translated on display through `__()`;
 * the lang files carry every seeded name and description.
 */
class ExpenseCategorySeeder extends Seeder
{
    public function run(?string $country = null): void
    {
        foreach (self::categoriesFor($country ?? config('app.country', 'US')) as $category) {
            if (ExpenseCategory::where('key', $category['key'])->exists()) {
                continue;
            }

            if (ExpenseCategory::where('account_code', $category['account_code'])->exists()) {
                $category['account_code'] = ExpenseCategory::generateAccountCode();
            }

            ExpenseCategory::create($category);
        }
    }

    /**
     * @return array<int, array{key:string, name:string, account_code:string, description:string, sort_order:int}>
     */
    public static function categoriesFor(string $country): array
    {
        $shared = self::shared();

        if ($country !== 'BR') {
            return $shared;
        }

        // Pró-labore — the owner's own remuneration — is a Brazilian line
        // with no American counterpart; it sits with the payroll codes.
        $brazil = [
            ['key' => 'br.pro_labore', 'name' => 'Pró-labore', 'account_code' => '6820', 'description' => 'Remuneration of the company partners', 'sort_order' => 16],
        ];

        $all = array_merge($shared, $brazil);
        usort($all, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $all;
    }

    protected static function shared(): array
    {
        return [
            ['key' => 'overhead.rent', 'name' => 'Rent', 'account_code' => '6100', 'description' => 'Office, yard and warehouse rent', 'sort_order' => 1],
            ['key' => 'overhead.utilities', 'name' => 'Utilities', 'account_code' => '6110', 'description' => 'Electricity, water and gas', 'sort_order' => 2],
            ['key' => 'overhead.phone_internet', 'name' => 'Phone and internet', 'account_code' => '6120', 'description' => 'Landlines, mobile plans and internet service', 'sort_order' => 3],
            ['key' => 'overhead.insurance', 'name' => 'Insurance', 'account_code' => '6200', 'description' => 'Liability, property and vehicle insurance premiums', 'sort_order' => 4],
            ['key' => 'overhead.vehicle_fuel', 'name' => 'Vehicle fuel', 'account_code' => '6300', 'description' => 'Fuel for company vehicles', 'sort_order' => 5],
            ['key' => 'overhead.vehicle_maintenance', 'name' => 'Vehicle maintenance', 'account_code' => '6310', 'description' => 'Servicing, tyres and repairs on company vehicles', 'sort_order' => 6],
            ['key' => 'overhead.equipment_maintenance', 'name' => 'Equipment maintenance', 'account_code' => '6400', 'description' => 'Servicing and repairs on machinery and tools', 'sort_order' => 7],
            ['key' => 'overhead.equipment_rental', 'name' => 'Equipment rental', 'account_code' => '6410', 'description' => 'Rented machinery and tools not charged to a project', 'sort_order' => 8],
            ['key' => 'overhead.office_supplies', 'name' => 'Office supplies', 'account_code' => '6500', 'description' => 'Stationery, printing and small office purchases', 'sort_order' => 9],
            ['key' => 'overhead.software', 'name' => 'Software and subscriptions', 'account_code' => '6510', 'description' => 'Software licences and online services', 'sort_order' => 10],
            ['key' => 'overhead.professional_fees', 'name' => 'Accounting and legal fees', 'account_code' => '6600', 'description' => 'Accountants, lawyers and other professional services', 'sort_order' => 11],
            ['key' => 'overhead.marketing', 'name' => 'Marketing', 'account_code' => '6700', 'description' => 'Advertising, website and promotional material', 'sort_order' => 12],
            ['key' => 'overhead.admin_payroll', 'name' => 'Administrative payroll', 'account_code' => '6800', 'description' => 'Salaries of office staff not charged to a project', 'sort_order' => 13],
            ['key' => 'overhead.payroll_taxes', 'name' => 'Payroll taxes', 'account_code' => '6810', 'description' => 'Employer contributions and payroll charges', 'sort_order' => 14],
            ['key' => 'overhead.bank_fees', 'name' => 'Bank fees', 'account_code' => '6900', 'description' => 'Account charges, card fees and interest', 'sort_order' => 17],
            ['key' => 'overhead.taxes_licences', 'name' => 'Taxes and licences', 'account_code' => '6910', 'description' => 'Business taxes, permits and licences', 'sort_order' => 18],
            ['key' => 'overhead.travel_meals', 'name' => 'Travel and meals', 'account_code' => '6950', 'description' => 'Travel, lodging and meals not charged to a project', 'sort_order' => 19],
            ['key' => 'overhead.other', 'name' => 'Other overhead', 'account_code' => '6990', 'description' => 'Anything else the company pays that belongs to no project', 'sort_order' => 99],
        ];
    }
}
