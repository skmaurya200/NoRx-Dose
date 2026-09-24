<?php

namespace App\Http\Requests\APIs\Blog;

class UpdateBlogPostRequest extends StoreBlogPostRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['remove_cover'] = ['sometimes', 'boolean'];

        return $rules;
    }

    public function shouldRemoveCover(): bool
    {
        return $this->boolean('remove_cover');
    }
}
