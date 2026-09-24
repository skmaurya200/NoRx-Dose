<?php

namespace App\Http\Requests\APIs\Blog;

use App\Models\BlogPost;
use App\Support\HtmlSanitizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $bucket = config('admin.uploads.blog');

        return [
            'title' => ['required', 'string', 'min:3', 'max:200'],

            // Optional override. Left blank the slug is derived from the
            // title; typed, it is still slugified and de-duplicated by
            // BlogService, so a client cannot claim an arbitrary URL.
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/'],

            // Nullable: a post can be filed later without blocking the draft.
            'category_id' => ['nullable', 'integer', Rule::exists('tbl_blog_categories', 'id')->whereNull('deleted_at')],

            'excerpt' => ['nullable', 'string', 'max:400'],

            // The editor's HTML. Length is capped on the raw markup, which is
            // generous for prose and stops a paste of an entire web page.
            'body' => ['required', 'string', 'max:200000'],

            'takeaways' => ['nullable', 'array', 'max:'.config('admin.blog.max_takeaways', 8)],
            'takeaways.*' => ['nullable', 'string', 'max:200'],

            'cover_alt' => ['nullable', 'string', 'max:200'],

            'author_name' => ['nullable', 'string', 'max:120'],

            // Left empty, the service estimates it from the body.
            'read_minutes' => ['nullable', 'integer', 'min:1', 'max:999'],

            'status' => ['required', Rule::in(BlogPost::STATUSES)],
            'is_featured' => ['sometimes', 'boolean'],
            'noindex' => ['sometimes', 'boolean'],

            // A future date is a scheduled post, which is allowed and is why
            // there is no "after" or "before" rule here.
            'published_at' => ['nullable', 'date'],

            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:255'],

            'cover' => [
                'nullable',
                'image',
                'mimes:'.implode(',', $bucket['mimes']),
                'max:'.$bucket['max_kb'],
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // An untouched editor still posts "<p><br></p>", which passes
            // "required" and is not a post. Judged on what survives the
            // sanitiser, since that is what would be stored.
            if ($this->filled('body') && HtmlSanitizer::isBlank($this->input('body'))) {
                $validator->errors()->add('body', 'Write something in the post body.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        foreach (['excerpt', 'slug', 'cover_alt', 'author_name', 'read_minutes',
            'published_at', 'meta_title', 'meta_description'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }

        // The picker posts "" for "no category", which must not be read as
        // category zero.
        if ($this->input('category_id') === '') {
            $this->merge(['category_id' => null]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'author_name' => 'author',
            'read_minutes' => 'reading time',
            'published_at' => 'publish date',
            'cover_alt' => 'cover image description',
            'meta_title' => 'SEO title',
            'meta_description' => 'SEO description',
            'takeaways.*' => 'short-version line',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Write something in the post body.',
            'category_id.exists' => 'That category no longer exists.',
            'slug.regex' => 'A URL can only use letters, numbers and single dashes.',
        ];
    }

    /**
     * Everything the service needs. The file is excluded - it is pulled from
     * the request separately and never mass-assigned.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->safe()->except(['cover', 'remove_cover', 'noindex']);

        $data['is_featured'] = $this->boolean('is_featured');

        // Expressed as "noindex" on the form because an unticked checkbox
        // sends nothing at all: absent has to mean the common case, and the
        // common case is that a post should be findable.
        $data['is_indexable'] = ! $this->boolean('noindex');

        // safe() only carries keys that were actually sent, so an optional
        // field left out of the payload is absent here rather than null.
        $data['author_name'] = ($data['author_name'] ?? null) ?: 'The Aurum team';

        // An absent takeaways key means the form sent none, which is a real
        // instruction ("clear the list"), not a missing field.
        $data['takeaways'] = $this->input('takeaways', []);

        return $data;
    }
}
