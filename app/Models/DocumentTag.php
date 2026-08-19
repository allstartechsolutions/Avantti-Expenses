<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A free-form document tag, shared across the install so the same word means
 * the same thing on every project.
 */
class DocumentTag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
        'created_by',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $tag) {
            $tag->slug = Str::slug($tag->name);
        });
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Find an existing tag by slug or create it — tags are typed, not picked
     * from a maintained list, so the same word must never make two rows.
     */
    public static function findOrCreateByName(string $name, ?int $userId = null): self
    {
        $slug = Str::slug($name);

        return static::firstOrCreate(
            ['slug' => $slug],
            ['name' => trim($name), 'created_by' => $userId]
        );
    }
}
