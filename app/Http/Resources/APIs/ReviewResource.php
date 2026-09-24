<?php

namespace App\Http\Resources\APIs;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * The reviewer's email and IP are deliberately absent: they are moderation
     * data, not part of a review.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_name' => $this->author_name,
            'byline' => $this->byline(),
            'initials' => $this->initials(),
            'location' => $this->location,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'rating' => $this->rating,
            'stars' => $this->stars(),
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            // What the shared row toggle in the panel reads. It speaks one
            // vocabulary across every module - on/off plus a label - and for
            // a review "on" means published.
            'is_active' => $this->isApproved(),
            'status_label' => ucfirst($this->status),
            'is_featured' => $this->is_featured,
            'is_verified' => $this->is_verified,
            'helpful_count' => $this->helpful_count,
            'source' => $this->source,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
                'slug' => $this->product?->slug,
            ]),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
