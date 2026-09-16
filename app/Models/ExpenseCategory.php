<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * A category for company (general) expenses — the ones that belong to no
 * project and therefore have no budget and no cost code.
 *
 * Each category carries a 4-digit `account_code` so the customer's
 * accounting software can match what this app exports. The code is typed in
 * or, left blank, generated unique. A category is never deleted while an
 * expense is filed under it: it is **retired**, which takes it off the
 * picker while every expense already filed keeps it.
 */
class ExpenseCategory extends Model
{
    protected $fillable = [
        'key',
        'name',
        'account_code',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** Categories still offered on the picker. A retired one keeps its expenses. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('account_code');
    }

    /** `6100 - Rent`, the way pickers, tables and exports print a category. */
    public function getDisplayLabel(): string
    {
        return $this->account_code.' - '.__($this->name);
    }

    /** Only for a category nothing was ever filed under. Anything else is retired instead. */
    public function canBeDeleted(): bool
    {
        return ! $this->expenses()->exists();
    }

    /**
     * A 4-digit code no other category holds. The unique index is the final
     * arbiter: a caller creating inside a transaction should catch
     * `UniqueConstraintViolationException` once and draw again.
     *
     * @throws RuntimeException when 25 draws all collided — the table is
     *                          effectively full, which no install will reach.
     */
    public static function generateAccountCode(): string
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $code = (string) random_int(1000, 9999);

            if (! static::where('account_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not find a free 4-digit account code.');
    }
}
