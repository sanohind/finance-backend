<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;
use App\Http\Resources\NewsResource;
use Illuminate\Support\Facades\Storage;

class SupplierNewsController extends Controller
{
    /**
     * Display a listing of all News for suppliers.
     */
    public function index()
    {
        $news = News::all();
        return NewsResource::collection($news);
    }

    /**
     * Stream the document for a given filename.
     */
    public function streamDocument($filename)
    {
        $path = 'news_documents/' . $filename;
        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'Document not found.');
        }
        return response()->file(storage_path('app/public/' . $path));
    }
}
