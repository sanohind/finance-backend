<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SuperAdminInvDocumentController extends Controller
{
    public function streamFile($folder, $filename)
    {
        $relativePath = "public/{$folder}/{$filename}";

        if (!Storage::disk('public')->exists($relativePath)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        // Get the absolute path to the file in storage/app/public/<folder>/<filename>.
        $absolutePath = storage_path("app/public/{$relativePath}");

        // Use response()->file(...) to show/stream the file inline instead of forcing download.
        return response()->file($absolutePath);
    }
}
