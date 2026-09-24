<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use App\Support\SqlLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A journal post.
 *
 * The body is HTML, already filtered by App\Support\HtmlSanitizer before it
 * reached this table - see BlogService. Templates print it with {!! !!}, which
 * is only safe because of that.
 */
class BlogPost extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'published', 'archived'];

    protected $table = 'tbl_blog_posts';

    /**
     * slug and views_count are excluded: the first is settled by BlogService
     * (derived from the title, or from an operator's override, and always run
     * through the uniqueness check), the second is owned by the read counter.
     */
    protected $fillable = [
        'category_id',
        'title',
        'excerpt',
        'body',
        'takeaways',
        'cover_alt',
        'author_name',
        'read_minutes',
        'status',
        'is_featured',
        'is_indexable',
        'meta_title',
        'meta_description',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'takeaways' => 'array',
            'is_featured' => 'boolean',
            'is_indexable' => 'boolean',
            'read_minutes' => 'integer',
            'views_count' => 'integer',
            'category_id' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------ relations */

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    /* --------------------------------------------------------------- routing */

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /* -------------------------------------------------------------- helpers */

    public function coverUrl(): ?string
    {
        return $this->cover_path ? asset($this->cover_path) : null;
    }

    /**
     * What a screen reader and image search are told the cover shows. Falls
     * back to the title, which is a poor description but a great deal better
     * than an empty alt on the largest image on the page.
     */
    public function coverAlt(): string
    {
        return $this->cover_alt ?: $this->title;
    }

    /**
     * The public address of this post.
     */
    public function url(): string
    {
        return route('blog-details', $this->slug);
    }

    /**
     * The <title> and og:title. The operator's SEO title when they wrote one,
     * because that is the whole reason the field exists.
     */
    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function seoDescription(): string
    {
        return $this->meta_description ?: $this->excerptLabel(160);
    }

    /**
     * Whether a search engine should list this post at all. A draft is not
     * reachable in the first place; this is for the published ones an operator
     * wants kept out of the index.
     */
    public function isIndexable(): bool
    {
        return $this->is_indexable && $this->isPublished();
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && ($this->published_at === null || ! $this->published_at->isFuture());
    }

    /**
     * The category name, or a neutral word - a post with no category still
     * has to print something on its card.
     */
    public function categoryName(): string
    {
        return $this->category?->name ?? 'Journal';
    }

    public function readLabel(): string
    {
        return max(1, (int) $this->read_minutes).' min read';
    }

    public function publishedLabel(): string
    {
        return ($this->published_at ?? $this->created_at)?->format('j F Y') ?? '';
    }

    /**
     * The card blurb. Falls back to the opening of the body, so a post saved
     * without one is not a card with a hole in it.
     */
    public function excerptLabel(int $length = 160): string
    {
        if (filled($this->excerpt)) {
            return $this->excerpt;
        }

        return Str::limit(HtmlSanitizer::toText($this->body), $length);
    }

    /**
     * @return array<int, string>
     */
    public function takeawayList(): array
    {
        return array_values(array_filter(
            array_map(fn ($line) => trim((string) $line), $this->takeaways ?? []),
            fn (string $line) => $line !== '',
        ));
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->status === 'published' && $this->published_at?->isFuture() => 'Scheduled',
            default => ucfirst($this->status),
        };
    }

    public function statusBadge(): string
    {
        return match (true) {
            $this->status === 'published' && $this->published_at?->isFuture() => 'badge-warn',
            $this->status === 'published' => 'badge-active',
            $this->status === 'archived' => 'badge-archived',
            default => 'badge-draft',
        };
    }

    /**
     * Estimated reading time in whole minutes, never less than one.
     */
    public static function estimateReadMinutes(?string $body): int
    {
        $words = str_word_count(HtmlSanitizer::toText($body));
        $perMinute = max(1, (int) config('admin.blog.words_per_minute', 200));

        return max(1, (int) ceil($words / $perMinute));
    }

    /* --------------------------------------------------------------- scopes */

    /**
     * What a visitor is allowed to see. A future published_at is a scheduled
     * post, which is deliberately not reachable yet - including by its URL.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(function (Builder $q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Newest first, with a stable tiebreak so two posts published in the same
     * minute do not swap places between page loads.
     */
    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = SqlLike::contains($term);

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('body', 'like', $like);
        });
    }
}
