<?php

namespace App\Services\Blog;

use App\Exceptions\Catalogue\ResourceInUseException;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Services\Catalogue\SlugGenerator;
use App\Support\HtmlSanitizer;
use App\Support\PublicUpload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Every read and write of the journal.
 *
 * The rule worth stating: a post body is sanitised here, once, on the way in.
 * Nothing downstream filters it again, so nothing may write to tbl_blog_posts
 * without coming through this class.
 */
class BlogService
{
    private const UPLOAD_BUCKET = 'blog';

    /* ============================================================== posts == */

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatePosts(array $filters): LengthAwarePaginator
    {
        return BlogPost::query()
            ->with('category:id,name')
            ->search($filters['search'] ?? null)
            ->when(($filters['status'] ?? '') !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(($filters['category_id'] ?? '') !== '', function (Builder $query) use ($filters) {
                $filters['category_id'] === 'none'
                    ? $query->whereNull('category_id')
                    : $query->where('category_id', (int) $filters['category_id']);
            })
            // Drafts have no published_at, so the panel sorts on what it
            // always has: when the row was last touched.
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPost(array $data, ?UploadedFile $cover = null): BlogPost
    {
        return DB::transaction(function () use ($data, $cover) {
            $requested = $this->requestedSlug($data);

            $post = new BlogPost($this->preparePost($data));

            // An operator's own slug still goes through the generator, so it
            // is slugified and de-duplicated like any other - the override
            // chooses the words, not the uniqueness rules.
            $post->slug = SlugGenerator::for(BlogPost::class, $requested ?? $data['title']);

            if ($cover !== null) {
                $post->cover_path = PublicUpload::store($cover, self::UPLOAD_BUCKET);
            }

            $post->save();

            $this->settleFeatured($post);

            return $post->load('category:id,name');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePost(
        BlogPost $post,
        array $data,
        ?UploadedFile $cover = null,
        bool $removeCover = false,
    ): BlogPost {
        return DB::transaction(function () use ($post, $data, $cover, $removeCover) {
            $requested = $this->requestedSlug($data);

            $post->fill($this->preparePost($data, $post));

            if ($requested !== null) {
                // Typed deliberately, so it wins - including over a title that
                // changed in the same save.
                $post->slug = SlugGenerator::for(BlogPost::class, $requested, $post->id);
            } elseif ($post->isDirty('title')) {
                // Only re-slug when the title actually moved: an unrelated edit
                // must not silently invalidate a URL that is already indexed
                // and linked to.
                $post->slug = SlugGenerator::for(BlogPost::class, $post->title, $post->id);
            }

            if ($cover !== null) {
                $post->cover_path = PublicUpload::replace($cover, self::UPLOAD_BUCKET, $post->cover_path);
            } elseif ($removeCover && $post->cover_path) {
                PublicUpload::delete($post->cover_path, self::UPLOAD_BUCKET);
                $post->cover_path = null;
            }

            $post->save();

            $this->settleFeatured($post);

            return $post->load('category:id,name');
        });
    }

    public function deletePost(BlogPost $post): void
    {
        // Soft delete, and the cover is left on disk so a restore does not
        // come back pointing at a file that no longer exists.
        $post->delete();
    }

    public function togglePublished(BlogPost $post): BlogPost
    {
        if ($post->status === 'published') {
            $post->status = 'draft';
        } else {
            $post->status = 'published';

            // Publishing something that was never scheduled dates it now,
            // rather than leaving it with no date on the card.
            $post->published_at ??= now();
        }

        $post->save();

        return $post;
    }

    /* --------------------------------------------------------- preparation */

    /**
     * The slug the operator asked for, or null when they left the field blank
     * and want it derived from the title.
     *
     * @param  array<string, mixed>  $data
     */
    private function requestedSlug(array $data): ?string
    {
        $slug = trim((string) ($data['slug'] ?? ''));

        return $slug === '' ? null : $slug;
    }

    /**
     * Turns validated form input into columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preparePost(array $data, ?BlogPost $existing = null): array
    {
        // Never mass-assigned: the slug column is settled above, after the
        // generator has had its say about uniqueness.
        unset($data['slug']);

        // The one place the editor's HTML is filtered.
        if (array_key_exists('body', $data)) {
            $data['body'] = HtmlSanitizer::clean($data['body']);
        }

        $body = $data['body'] ?? $existing?->body;

        // An operator who typed a figure keeps it; everyone else gets an
        // estimate that stays right as the post is edited.
        if (blank($data['read_minutes'] ?? null)) {
            $data['read_minutes'] = BlogPost::estimateReadMinutes($body);
        }

        if (array_key_exists('takeaways', $data)) {
            $data['takeaways'] = $this->cleanTakeaways($data['takeaways']);
        }

        // A post published without a date is published now. A scheduled one
        // keeps the date the operator chose, however far ahead it is.
        if (($data['status'] ?? null) === 'published' && blank($data['published_at'] ?? null)) {
            $data['published_at'] = $existing?->published_at ?? now();
        }

        return $data;
    }

    /**
     * @param  mixed  $takeaways
     * @return array<int, string>|null
     */
    private function cleanTakeaways($takeaways): ?array
    {
        if (! is_array($takeaways)) {
            return null;
        }

        $lines = array_values(array_filter(
            array_map(fn ($line) => trim((string) $line), $takeaways),
            fn (string $line) => $line !== '',
        ));

        // null rather than [] so "no short version" is one state in the
        // database instead of two that render identically.
        return $lines === [] ? null : array_slice($lines, 0, (int) config('admin.blog.max_takeaways', 8));
    }

    /**
     * The journal has one featured slot, so setting a new one clears the last.
     * Done here rather than in the request because it is a rule about the
     * collection, not about the form.
     */
    private function settleFeatured(BlogPost $post): void
    {
        if (! $post->is_featured) {
            return;
        }

        BlogPost::query()
            ->whereKeyNot($post->id)
            ->where('is_featured', true)
            ->update(['is_featured' => false]);
    }

    /* ========================================================= categories == */

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateCategories(array $filters): LengthAwarePaginator
    {
        return BlogCategory::query()
            ->withCount('posts')
            ->search($filters['search'] ?? null)
            ->when(($filters['status'] ?? '') !== '', function (Builder $query) use ($filters) {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->ordered()
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCategory(array $data): BlogCategory
    {
        $category = new BlogCategory($data);
        $category->slug = SlugGenerator::for(BlogCategory::class, $data['name']);
        $category->save();

        return $category;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(BlogCategory $category, array $data): BlogCategory
    {
        $category->fill($data);

        if ($category->isDirty('name')) {
            $category->slug = SlugGenerator::for(BlogCategory::class, $category->name, $category->id);
        }

        $category->save();

        return $category;
    }

    /**
     * @throws ResourceInUseException
     */
    public function deleteCategory(BlogCategory $category): void
    {
        // The foreign key would null the posts' category rather than fail, so
        // this check exists to stop it happening by accident: an operator
        // tidying categories should be told what they are about to detach.
        if ($category->posts()->exists()) {
            throw new ResourceInUseException(
                'This category still has posts in it. Move them to another category first.',
            );
        }

        $category->delete();
    }

    public function toggleCategory(BlogCategory $category): BlogCategory
    {
        $category->is_active = ! $category->is_active;
        $category->save();

        return $category;
    }

    /* ========================================================= storefront == */

    /**
     * The newest published posts, for the home page rail.
     *
     * @return Collection<int, BlogPost>
     */
    public function latest(int $limit = 3): Collection
    {
        return BlogPost::query()
            ->published()
            ->with('category:id,name,slug')
            ->newest()
            ->limit($limit)
            ->get();
    }

    /**
     * The post that gets the large treatment at the top of the journal: the
     * flagged one, or simply the newest when nothing is flagged.
     */
    public function featured(): ?BlogPost
    {
        return BlogPost::query()
            ->published()
            ->with('category:id,name,slug')
            ->where('is_featured', true)
            ->newest()
            ->first()
            ?? BlogPost::query()->published()->with('category:id,name,slug')->newest()->first();
    }

    /**
     * The journal listing. Filtering and paging happen in SQL rather than in
     * the browser, so the page works with two posts or two thousand.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginatePublished(array $filters, ?int $excludeId = null): LengthAwarePaginator
    {
        return BlogPost::query()
            ->published()
            ->with('category:id,name,slug')
            ->when($excludeId !== null, fn (Builder $q) => $q->whereKeyNot($excludeId))
            ->search($filters['q'] ?? null)
            ->when(($filters['category'] ?? '') !== '', function (Builder $query) use ($filters) {
                $query->whereHas('category', fn (Builder $q) => $q->where('slug', $filters['category']));
            })
            ->newest()
            ->paginate((int) config('admin.blog.per_page', 9))
            ->withQueryString();
    }

    /**
     * The chips above the listing - active categories that have something to
     * show. A chip leading to an empty page is worse than no chip.
     *
     * @return Collection<int, BlogCategory>
     */
    public function publicCategories(): Collection
    {
        return BlogCategory::query()
            ->active()
            ->whereHas('posts', fn (Builder $q) => $q->published())
            ->withCount(['posts as published_posts_count' => fn (Builder $q) => $q->published()])
            ->ordered()
            ->get();
    }

    public function findPublished(string $slug): ?BlogPost
    {
        return BlogPost::query()
            ->published()
            ->with('category:id,name,slug')
            ->where('slug', $slug)
            ->first();
    }

    /**
     * More from the journal: same category first, topped up with the newest
     * from anywhere so the block is never half empty.
     *
     * @return Collection<int, BlogPost>
     */
    public function related(BlogPost $post, int $limit = 3): Collection
    {
        $sameCategory = $post->category_id === null
            ? new Collection
            : BlogPost::query()
                ->published()
                ->with('category:id,name,slug')
                ->where('category_id', $post->category_id)
                ->whereKeyNot($post->id)
                ->newest()
                ->limit($limit)
                ->get();

        if ($sameCategory->count() >= $limit) {
            return $sameCategory;
        }

        $filler = BlogPost::query()
            ->published()
            ->with('category:id,name,slug')
            ->whereKeyNot($post->id)
            ->whereNotIn('id', $sameCategory->pluck('id'))
            ->newest()
            ->limit($limit - $sameCategory->count())
            ->get();

        return $sameCategory->concat($filler);
    }

    /**
     * One read. A bare UPDATE rather than save(), so counting a view neither
     * touches updated_at nor races another reader on the same post.
     */
    public function countView(BlogPost $post): void
    {
        BlogPost::query()->whereKey($post->id)->increment('views_count');
    }

    /* -------------------------------------------------------------- private */

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? config('admin.catalogue.per_page', 15));

        return max(5, min((int) config('admin.catalogue.max_per_page', 100), $perPage));
    }
}
