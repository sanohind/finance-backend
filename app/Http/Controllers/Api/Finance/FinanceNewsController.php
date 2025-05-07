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
            $documentPath = $request->file('document')->store('news_documents', 'public');
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
            $documentPath = $request->file('document')->store('news_documents', 'public');
        }

        $news->update([
            'title' => $request->title,
            'description' => $request->description,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
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
}
