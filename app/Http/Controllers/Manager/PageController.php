<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Services\Content\PageContentService;
use App\Support\Content\PageSchema;
use Illuminate\View\View;

/**
 * The Pages screens: a list of the storefront's fixed pages, and a form per
 * page built from App\Support\Content\PageSchema.
 *
 * HTML only - saving posts to App\Http\Controllers\APIs\PageContentController.
 */
class PageController extends Controller
{
    public function __construct(private readonly PageContentService $content) {}

    /**
     * GET /manager/pages
     */
    public function index(): View
    {
        $pages = collect(PageSchema::all())->map(fn (array $page, string $key) => [
            'key' => $key,
            'name' => $page['name'],
            'url' => route($page['route']),
            'sections' => count($page['sections']),
            'fields' => count(PageSchema::fields($key)),
            'edited' => $this->content->editedCount($key),
        ])->values();

        return view('manager.pages.index', ['pages' => $pages]);
    }

    /**
     * GET /manager/pages/{page}
     */
    public function edit(string $page): View
    {
        abort_unless(PageSchema::exists($page), 404);

        $definition = PageSchema::page($page);

        return view('manager.pages.form', [
            'pageKey' => $page,
            'page' => $definition,
            'content' => $this->content->forPage($page),
            'liveUrl' => route($definition['route']),
        ]);
    }
}
