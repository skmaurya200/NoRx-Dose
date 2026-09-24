<?php

namespace App\View\Composers;

use App\Services\Content\PageContentService;
use App\Support\Content\PageContent;
use App\Support\Content\PageSchema;
use App\Support\PageSeo;
use Illuminate\View\View;

/**
 * Puts $content into every storefront page that has editable copy.
 *
 * Composed onto the page views themselves rather than the layout: with
 *
 * @extends, the child view is evaluated before the layout is, so a composer on
 * the layout would arrive after the sections had already been rendered.
 *
 * The bag is memoised per request, because a page and its partials may ask for
 * it more than once and the values are the same each time.
 */
class ContentComposer
{
    /** @var array<string, PageContent> */
    private array $resolved = [];

    public function __construct(private readonly PageContentService $content) {}

    public function compose(View $view): void
    {
        $pageKey = PageSchema::keyForView($view->name());

        if ($pageKey === null) {
            return;
        }

        $content = $this->resolved[$pageKey] ??= $this->content->forPage($pageKey);

        $view->with('content', $content);

        // The page's own meta title and description, laid over whatever the
        // controller built. Done here rather than in each controller so a page
        // cannot be added to the schema and then quietly ignore its own SEO
        // fields - which is precisely the sort of thing nobody notices.
        $view->with('seo', $content->applySeo($view->getData()['seo'] ?? PageSeo::make()));
    }
}
