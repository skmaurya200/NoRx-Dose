<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\Page\UpdatePageContentRequest;
use App\Services\Content\PageContentService;
use App\Support\ApiResponse;
use App\Support\Content\PageSchema;
use Illuminate\Http\JsonResponse;

/**
 * Saving the editable copy on a storefront page.
 */
class PageContentController extends Controller
{
    public function __construct(private readonly PageContentService $content) {}

    /**
     * POST /api/manager/pages/{page}
     *
     * POST rather than PUT: the form is multipart so it can carry images, and
     * PHP does not populate $_FILES for a PUT body.
     */
    public function update(UpdatePageContentRequest $request, string $page): JsonResponse
    {
        abort_unless(PageSchema::exists($page), 404);

        $this->content->save(
            $page,
            $request->fields(),
            $request->images(),
            $request->removeImages(),
            $request->repeaters(),
        );

        return ApiResponse::success(
            ['page' => $page],
            PageSchema::page($page)['name'].' page updated.',
        );
    }
}
