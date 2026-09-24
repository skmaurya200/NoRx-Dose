<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\PublicUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Images dropped into the body of a post from the editor's toolbar.
 *
 * Separate from the post itself because an author uploads a picture while they
 * are still writing, long before the post is saved - and a draft that has
 * never been saved has no id to attach a file to.
 *
 * The file goes through App\Support\PublicUpload like every other upload in
 * this project: the stored name is generated, the extension comes from the
 * file's own sniffed mime, and the content is re-checked with getimagesize(),
 * so a PHP script renamed to .jpg never reaches the disk.
 */
class BlogMediaController extends Controller
{
    /**
     * POST /api/manager/blog/uploads
     */
    public function store(Request $request): JsonResponse
    {
        $bucket = config('admin.uploads.blog');

        $request->validate([
            'image' => [
                'required',
                'image',
                'mimes:'.implode(',', $bucket['mimes']),
                'max:'.$bucket['max_kb'],
            ],
            'alt' => ['nullable', 'string', 'max:200'],
        ], [
            'image.max' => 'Images must be under '.round($bucket['max_kb'] / 1024).' MB.',
        ]);

        $path = PublicUpload::store($request->file('image'), 'blog');

        return ApiResponse::success([
            'path' => $path,
            // Root-relative rather than absolute: the post body outlives the
            // domain it was written on, and a hard-coded host in stored markup
            // is a broken image after the first migration.
            'url' => '/'.ltrim($path, '/'),
            'alt' => (string) $request->input('alt', ''),
        ], 'Image uploaded.', Response::HTTP_CREATED);
    }
}
