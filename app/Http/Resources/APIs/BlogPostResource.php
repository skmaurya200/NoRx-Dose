<?php

namespace App\Http\Resources\APIs;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlogPost
 */
class BlogPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'excerpt_label' => $this->excerptLabel(),
            // Already sanitised on the way in; see App\Services\Blog\BlogService.
            'body' => $this->body,
            'takeaways' => $this->takeawayList(),
            'cover_url' => $this->coverUrl(),
            'author_name' => $this->author_name,
            'read_minutes' => $this->read_minutes,
            'read_label' => $this->readLabel(),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'is_active' => $this->status === 'published',
            'is_featured' => $this->is_featured,
            'views_count' => $this->views_count,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'published_at' => $this->published_at?->toIso8601String(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
