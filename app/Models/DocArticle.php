<?php

namespace App\Models;

use App\Support\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A guide written inside the app by this company.
 *
 * The shipped guides are files (config/documentation.php); these are the ones
 * an admin writes. DocumentationService presents both as one library.
 */
class DocArticle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug', 'title', 'category', 'summary', 'body',
        'is_published', 'position', 'created_by', 'updated_by',
    ];

    protected $attributes = [
        'category' => 'general',
        'is_published' => false,
        'position' => 0,
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'position' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $article) {
            $article->slug = $article->slug ?: static::uniqueSlug($article->title, $article->id);

            // Never trust editor output, on the way in or the way out.
            $article->body = RichText::sanitize($article->body);
        });
    }

    /** A readable, stable address that does not collide with a shipped guide. */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'guide';
        $slug = $base;
        $suffix = 2;

        $taken = fn (string $candidate) => static::withTrashed()
                ->where('slug', $candidate)
                ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
                ->exists()
            || array_key_exists($candidate, (array) config('documentation.guides', []));

        while ($taken($slug)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Images and attachments used by this guide, on the documents disk. */
    public function files(): MorphMany
    {
        return $this->morphMany(FileUpload::class, 'attachable');
    }

    public function safeBody(): string
    {
        return RichText::sanitize($this->body);
    }
}
