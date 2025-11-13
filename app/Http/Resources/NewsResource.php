<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class NewsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $carouselImages = $this->carousel_images ?? [];
        $carouselImageUrls = [];

        foreach ($carouselImages as $imagePath) {
            if ($imagePath) {
                // Extract filename from path (e.g., 'news_carousel/filename.jpg' -> 'filename.jpg')
                $filename = basename($imagePath);
                $carouselImageUrls[] = [
                    'path' => $imagePath,
                    'url' => Storage::disk('public')->url($imagePath),
                    'filename' => $filename,
                ];
            }
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'carousel_images' => $carouselImageUrls,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'document' => $this->document,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
