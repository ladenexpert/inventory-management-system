<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function search(Request $request)
    {
        abort_unless($request->user()?->hasAnyPermission([
            ['master_data', 'view'],
            ['materials', 'create'],
            ['materials', 'update'],
        ]), 403);

        $query = $request->input('q');

        $categories = Cache::rememberForever('categories_list_all', function () {
            return Category::all()->map(function($category) {
                return [
                    'value' => $category->id,
                    'text' => $category->name,
                ];
            });
        });

        if ($query) {
            $categories = $categories->filter(function ($item) use ($query) {
                return stripos($item['text'], $query) !== false;
            });
        }

        return response()->json($categories->values()->take(20));
    }
}
