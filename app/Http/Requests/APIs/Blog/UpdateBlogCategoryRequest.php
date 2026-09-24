<?php

namespace App\Http\Requests\APIs\Blog;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateBlogCategoryRequest extends StoreBlogCategoryRequest
{
    /**
     * The category being edited must not collide with its own name.
     */
    protected function uniqueName(): Unique
    {
        return Rule::unique('tbl_blog_categories', 'name')
            ->ignore($this->route('category')->id)
            ->withoutTrashed();
    }
}
