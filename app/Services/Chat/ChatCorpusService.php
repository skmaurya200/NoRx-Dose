<?php

namespace App\Services\Chat;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Content\PageContentService;
use App\Support\Content\PageSchema;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Str;

/**
 * Everything in this store the chat is allowed to answer from, as plain text.
 *
 * This is the one place that decides what the assistant can know. A record
 * that is not produced here can never be retrieved, and therefore can never
 * ground an answer - which is how "do not invent policies" is enforced at the
 * source rather than argued with in a prompt.
 *
 * Deliberately excluded: customers, orders, contacts, settings, cost prices,
 * stock numbers and anything else that is either private or changes faster
 * than the index. Live values like price and availability are read from the
 * product row when the answer is built, never from the embedded text.
 */
class ChatCorpusService
{
    public function __construct(
        private readonly PageContentService $content,
    ) {}

    /**
     * Everything the assistant is allowed to know about this shop.
     *
     * This list is the knowledge layer, and it is the only one: the model is
     * taught nothing, it is handed what these methods return at the moment it
     * asks. Each source reads the live tables, so an edit is known as soon as
     * it is saved - which is also why nothing volatile is written into a
     * record. Prices, stock and links are read from the row when the answer is
     * built, never from here.
     *
     * Adding a source is this one array. A future operator-authored "AI
     * knowledge" table needs a method beside these returning record() rows and
     * a line here; indexing, retrieval, the tools and the grounding checks all
     * pick it up with no further change, because none of them knows where a
     * record came from.
     *
     * What must never be added: customers, orders, contacts, settings, cost
     * prices, or anything else a visitor could not already read on the site.
     *
     * @return list<array{source_type: string, source_key: string, source_id: int|null, title: string, body: string, url: string|null}>
     */
    public function all(): array
    {
        // How the store itself works - payment, delivery, ordering - is not a
        // record here. It is read from configuration by ChatStoreFactsService
        // when the assistant asks, so there is no written answer to retrieve.
        return array_merge(
            $this->products(),
            $this->categories(),
            $this->faq(),
            $this->pages(),
            $this->posts(),
        );
    }

    /**
     * Published products only. A draft is not part of the store yet, and an
     * assistant that describes one is describing something nobody can buy.
     *
     * @return list<array<string, mixed>>
     */
    public function products(): array
    {
        return Product::query()
            ->published()
            ->with(['category:id,name', 'detail'])
            ->get()
            ->map(fn (Product $product) => $this->forProduct($product))
            ->values()
            ->all();
    }

    /**
     * One product as an indexable record.
     *
     * Public because a follow-up needs the same text for a product it is
     * already discussing, on a store whose index has not been built yet -
     * and two different notions of "what this product says" would make the
     * second half of a conversation disagree with the first.
     *
     * @return array<string, mixed>
     */
    public function forProduct(Product $product): array
    {
        return $this->record(
            'product',
            (string) $product->id,
            $product->name,
            [
                $product->name,
                $product->brand,
                $product->category?->name,
                $product->unit,
                $product->short_description,
                $product->description,
                $product->detail?->benefits,
                $product->detail?->ingredients,
                $product->detail?->how_to_use,
                // Sections the operator wrote for this product alone. They are
                // product copy like any other, so the assistant can answer from
                // them rather than sending the visitor to the page to read it.
                ...$this->sectionText($product->detail?->extra_sections),
            ],
            route('product', $product->slug),
            $product->id,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        return ProductCategory::query()
            ->active()
            ->get()
            ->map(fn (ProductCategory $category) => $this->record(
                'category',
                (string) $category->id,
                $category->name,
                [$category->name, $category->description],
                route('shop', ['category' => $category->slug]),
                $category->id,
            ))
            ->values()
            ->all();
    }

    /**
     * The published FAQ, minus anything still carrying template copy - a
     * placeholder answered as policy is worse than no answer at all.
     *
     * @return list<array<string, mixed>>
     */
    public function faq(): array
    {
        $records = [];

        foreach ($this->content->forPage('faq')->items('questions.items') as $index => $row) {
            $question = $this->text($row['question'] ?? '');
            $answer = $this->text($row['answer'] ?? '');

            if ($question === '' || $answer === '' || $this->isPlaceholder($answer)) {
                continue;
            }

            $records[] = $this->record(
                'faq',
                (string) $index,
                Str::limit($question, 190, ''),
                [$question, $answer],
                route('faq'),
            );
        }

        return $records;
    }

    /**
     * The storefront's own fixed pages - shipping, returns, about - straight
     * from the page-content module, so editing a page in the panel is what
     * changes what the assistant says about it.
     *
     * @return list<array<string, mixed>>
     */
    public function pages(): array
    {
        $records = [];

        foreach (PageSchema::all() as $key => $page) {
            if ($key === 'faq') {
                // Already indexed question by question above, which retrieves
                // far better than one record holding the whole page.
                continue;
            }

            // Saved values over schema defaults, which is what the page
            // actually renders - an unedited page still says something, and
            // the assistant should be able to answer from it.
            $saved = array_filter(
                $this->content->values($key),
                fn (mixed $value) => is_string($value) && trim($value) !== '',
            );

            $body = $this->flatten(array_replace(PageSchema::defaults($key), $saved));

            if ($this->isPlaceholder($body) || mb_strlen($body) < 40) {
                continue;
            }

            $route = $page['route'] ?? null;

            $records[] = $this->record(
                'page',
                $key,
                (string) ($page['name'] ?? Str::headline($key)),
                [$body],
                is_string($route) && app('router')->has($route) ? route($route) : null,
            );
        }

        return $records;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function posts(): array
    {
        return BlogPost::query()
            ->published()
            ->get()
            ->map(fn (BlogPost $post) => $this->record(
                'post',
                (string) $post->id,
                $post->title,
                [$post->title, $post->excerpt, HtmlSanitizer::toText($post->body)],
                $post->url(),
                $post->id,
            ))
            ->values()
            ->all();
    }

    /**
     * An operator's own detail sections, flattened to "heading: text".
     *
     * @return list<string>
     */
    private function sectionText(mixed $sections): array
    {
        if (! is_array($sections)) {
            return [];
        }

        return array_values(array_filter(array_map(static function (mixed $section): ?string {
            if (! is_array($section)) {
                return null;
            }

            $heading = trim((string) ($section['label'] ?? ''));
            $body = trim((string) ($section['body'] ?? ''));

            return $heading === '' || $body === '' ? null : $heading.': '.$body;
        }, $sections)));
    }

    /**
     * @param  list<string|null>  $parts
     * @return array<string, mixed>
     */
    private function record(
        string $type,
        string $key,
        string $title,
        array $parts,
        ?string $url = null,
        ?int $id = null,
    ): array {
        $body = collect($parts)
            ->map(fn (?string $part) => $this->text((string) $part))
            ->filter()
            ->unique()
            ->implode("\n");

        return [
            'source_type' => $type,
            'source_key' => $key,
            'source_id' => $id,
            'title' => Str::limit($title, 190, ''),
            'body' => Str::limit($body, (int) config('chat.retrieval.max_body_characters'), ''),
            'url' => $url,
        ];
    }

    /**
     * Markup, entities and runs of whitespace all collapse: the index stores
     * what a person would read aloud, because that is what the question is
     * being compared against.
     */
    private function text(string $value): string
    {
        $value = HtmlSanitizer::toText($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * Page content is a nested array of fields and repeater rows; retrieval
     * only wants the words in it.
     *
     * @param  array<string, mixed>  $values
     */
    private function flatten(array $values): string
    {
        $words = [];

        array_walk_recursive($values, function (mixed $value) use (&$words) {
            if (! is_string($value) || trim($value) === '') {
                return;
            }

            // A saved repeater is stored as a JSON array of rows, so it is
            // decoded rather than indexed as its own source code.
            $decoded = str_starts_with(ltrim($value), '[') ? json_decode($value, true) : null;

            if (is_array($decoded)) {
                $words[] = $this->flatten($decoded);

                return;
            }

            $words[] = $this->text($value);
        });

        return trim(implode(' ', array_filter($words)));
    }

    private function isPlaceholder(string $value): bool
    {
        return $value === '' || preg_match('/placeholder|replace with|lorem ipsum/i', $value) === 1;
    }
}
