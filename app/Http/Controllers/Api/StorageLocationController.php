<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StorageLocation;
use Illuminate\Http\Request;

class StorageLocationController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q') ?? $request->input('search');

        $locations = StorageLocation::query()
            ->with('parent')
            ->when($query, function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner
                        ->where('code', 'like', "%{$query}%")
                        ->orWhere('name', 'like', "%{$query}%")
                        ->orWhere('type', 'like', "%{$query}%");
                });
            })
            ->orderBy('code')
            ->limit(50)
            ->get()
            ->map(fn (StorageLocation $location) => [
                'value' => $location->id,
                'id' => $location->id,
                'text' => $location->display_label,
                'name' => $location->name,
                'code' => $location->code,
                'type' => $location->type,
                'parent_name' => $location->parent?->name,
            ]);

        return response()->json($locations);
    }
}
