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
            // Sanitize full title for use in filename, use underscore as separator
            $slugTitle = Str::slug($request->title, '_'); // Use full title
            $fileName = "NEWS_{$slugTitle}.{$originalExtension}";
            // Store on the 'public' disk, in 'news_documents' folder, with the constructed filename
            $documentPath = $file->storeAs('news_documents', $fileName, 'public');
        }

        $news = News::create([
            'title' => $request->title,
            'description' => $request->description,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'document' => $documentPath,
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

        $news->update([
            'title' => $request->has('title') ? $request->title : $news->title,
            'description' => $request->has('description') ? $request->description : $news->description,
            'start_date' => $request->has('start_date') ? $request->start_date : $news->start_date,
            'end_date' => $request->has('end_date') ? $request->end_date : $news->end_date,
            'document' => $documentPath,
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
}
