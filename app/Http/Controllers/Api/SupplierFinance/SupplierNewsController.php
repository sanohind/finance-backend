<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;
use App\Http\Resources\NewsResource;

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
}
