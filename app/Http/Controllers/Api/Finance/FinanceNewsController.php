<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;
use App\Http\Resources\NewsResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\FinanceNewsStoreRequest;
use App\Http\Requests\FinanceNewsUpdateRequest;
use App\Http\Requests\FinanceNewsCarouselImageRequest;
use Illuminate\Support\Str;

class FinanceNewsController extends Controller
{
    /**
     * Display a listing of all News.
     */
    public function index()
    {
        $news = News::orderBy('created_at', 'desc')->get();
        return NewsResource::collection($news);
    }

    /**
     * Store a newly created News in storage.
     */
    public function store(FinanceNewsStoreRequest $request)
    {
        $documentPath = null;
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $originalExtension = $file->getClientOriginalExtension();
            // Use title if provided, otherwise use timestamp
            $titleToUse = $request->title ?? 'NEWS_' . now()->format('YmdHis');
            $slugTitle = Str::slug($titleToUse, '_');
            $fileName = "NEWS_{$slugTitle}.{$originalExtension}";
            // Store on the 'public' disk, in 'news_documents' folder, with the constructed filename
            $documentPath = $file->storeAs('news_documents', $fileName, 'public');
        }

        // Handle carousel images upload
        $carouselImages = [];
        if ($request->hasFile('carousel_images')) {
            // Use title if provided, otherwise use timestamp
            $titleToUse = $request->title ?? 'CAROUSEL_' . now()->format('YmdHis');
            $slugTitle = Str::slug($titleToUse, '_');
            foreach ($request->file('carousel_images') as $index => $image) {
                $originalExtension = $image->getClientOriginalExtension();
                $fileName = "CAROUSEL_{$slugTitle}_" . ($index + 1) . ".{$originalExtension}";
                $imagePath = $image->storeAs('news_carousel', $fileName, 'public');
                $carouselImages[] = $imagePath;
            }
        }

        $news = News::create([
            'title' => $request->title ?? 'Untitled News',
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'document' => $documentPath,
            'carousel_images' => $carouselImages,
            'created_by' => Auth::user()->name,
        ]);

        return new NewsResource($news);
    }

    public function edit($id)
    {
        $news = News::findOrFail($id);
        return new NewsResource($news);
    }

    /**
     * Update the specified News in storage.
     */
    public function update(FinanceNewsUpdateRequest $request, $id)
    {
        $news = News::findOrFail($id);

        $documentPath = $news->document;
        if ($request->hasFile('document')) {
            // Optionally delete the old file
            if ($documentPath && Storage::disk('public')->exists($documentPath)) {
                Storage::disk('public')->delete($documentPath);
            }
            $file = $request->file('document');
            $originalExtension = $file->getClientOriginalExtension();
            // Use current title (from request or existing record) for filename
            $titleToUse = $request->has('title') ? $request->title : $news->title;
            $slugTitle = Str::slug($titleToUse, '_'); // Use full title
            $fileName = "NEWS_{$slugTitle}.{$originalExtension}";
            $documentPath = $file->storeAs('news_documents', $fileName, 'public');
        }

        // Handle carousel images update
        $carouselImages = $news->carousel_images ?? [];
        if ($request->hasFile('carousel_images')) {
            $titleToUse = $request->has('title') ? $request->title : $news->title;
            $slugTitle = Str::slug($titleToUse, '_');
            $existingCount = count($carouselImages);
            foreach ($request->file('carousel_images') as $index => $image) {
                $originalExtension = $image->getClientOriginalExtension();
                $fileName = "CAROUSEL_{$slugTitle}_" . ($existingCount + $index + 1) . ".{$originalExtension}";
                $imagePath = $image->storeAs('news_carousel', $fileName, 'public');
                $carouselImages[] = $imagePath;
            }
        }

        $news->update([
            'title' => $request->has('title') ? $request->title : $news->title,
            'start_date' => $request->has('start_date') ? $request->start_date : $news->start_date,
            'end_date' => $request->has('end_date') ? $request->end_date : $news->end_date,
            'document' => $documentPath,
            'carousel_images' => $carouselImages,
            'updated_by' => Auth::user()->name,
        ]);

        return new NewsResource($news);
    }

    /**
     * Remove the specified News from storage.
     */
    public function destroy($id)
    {
        $news = News::findOrFail($id);
        // Delete the document file if exists
        if ($news->document && Storage::disk('public')->exists($news->document)) {
            Storage::disk('public')->delete($news->document);
        }
        // Delete all carousel images if exist
        if ($news->carousel_images) {
            foreach ($news->carousel_images as $imagePath) {
                if (Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
            }
        }
        $news->delete();
        return response()->json(['message' => 'News deleted successfully.']);
    }

    public function streamDocument($filename)
    {
        $path = 'news_documents/' . $filename;
        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'Document not found.');
        }
        return response()->file(storage_path('app/public/' . $path));
    }

    /**
     * Stream a carousel image.
     */
    public function streamCarouselImage($filename)
    {
        $path = 'news_carousel/' . $filename;
        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'Carousel image not found.');
        }
        return response()->file(storage_path('app/public/' . $path));
    }

    /**
     * Upload a carousel image to a news item.
     */
    public function uploadCarouselImage(FinanceNewsCarouselImageRequest $request, $id)
    {
        $news = News::findOrFail($id);

        $slugTitle = Str::slug($news->title, '_');
        $carouselImages = $news->carousel_images ?? [];
        $existingCount = count($carouselImages);

        $file = $request->file('image');
        $originalExtension = $file->getClientOriginalExtension();
        $fileName = "CAROUSEL_{$slugTitle}_" . ($existingCount + 1) . ".{$originalExtension}";
        $imagePath = $file->storeAs('news_carousel', $fileName, 'public');

        $carouselImages[] = $imagePath;

        $news->update([
            'carousel_images' => $carouselImages,
            'updated_by' => Auth::user()->name,
        ]);

        return new NewsResource($news);
    }

    /**
     * Update a specific carousel image in a news item.
     */
    public function updateCarouselImage(FinanceNewsCarouselImageRequest $request, $id, $imageIndex)
    {
        $news = News::findOrFail($id);
        $carouselImages = $news->carousel_images ?? [];

        if (!isset($carouselImages[$imageIndex])) {
            return response()->json([
                'success' => false,
                'message' => 'Carousel image not found at the specified index.',
            ], 404);
        }

        // Delete the old image
        $oldImagePath = $carouselImages[$imageIndex];
        if (Storage::disk('public')->exists($oldImagePath)) {
            Storage::disk('public')->delete($oldImagePath);
        }

        // Upload the new image
        $slugTitle = Str::slug($news->title, '_');
        $file = $request->file('image');
        $originalExtension = $file->getClientOriginalExtension();
        $fileName = "CAROUSEL_{$slugTitle}_" . ($imageIndex + 1) . ".{$originalExtension}";
        $imagePath = $file->storeAs('news_carousel', $fileName, 'public');

        $carouselImages[$imageIndex] = $imagePath;

        $news->update([
            'carousel_images' => $carouselImages,
            'updated_by' => Auth::user()->name,
        ]);

        return new NewsResource($news);
    }

    /**
     * Delete a specific carousel image from a news item.
     */
    public function deleteCarouselImage($id, $imageIndex)
    {
        $news = News::findOrFail($id);
        $carouselImages = $news->carousel_images ?? [];

        if (!isset($carouselImages[$imageIndex])) {
            return response()->json([
                'success' => false,
                'message' => 'Carousel image not found at the specified index.',
            ], 404);
        }

        // Delete the image file
        $imagePath = $carouselImages[$imageIndex];
        if (Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

        // Remove from array
        unset($carouselImages[$imageIndex]);
        $carouselImages = array_values($carouselImages); // Re-index array

        $news->update([
            'carousel_images' => $carouselImages,
            'updated_by' => Auth::user()->name,
        ]);

        return new NewsResource($news);
    }
}
