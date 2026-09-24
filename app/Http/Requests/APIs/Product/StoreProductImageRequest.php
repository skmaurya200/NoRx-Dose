<?php

namespace App\Http\Requests\APIs\Product;

use App\Support\PublicUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreProductImageRequest extends FormRequest
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
        $max = (int) config('admin.catalogue.max_gallery_images');

        return [
            'images' => ['required', 'array', 'min:1', 'max:'.$max],
            'images.*' => PublicUpload::rules('products', required: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => 'Choose at least one image to upload.',
            'images.max' => 'You can upload up to :max images at a time.',
            'images.*.image' => 'Every file must be a picture.',
            'images.*.max' => 'Each image must be 4 MB or smaller.',
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function uploadedImages(): array
    {
        return array_values(array_filter((array) $this->file('images')));
    }
}
