<?php

namespace App\Http\Controllers\Api;

use App\Models\Unit;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class UnitController extends Controller
{
    public function search(Request $request)
    {
        abort_unless($request->user()?->hasAnyPermission([
            ['master_data', 'view'],
            ['materials', 'create'],
            ['materials', 'update'],
        ]), 403);

        $query = $request->input('q');

        $units = Cache::rememberForever('units_list_all', function () {
            return Unit::all()->map(function($unit) {
                return [
                    'value' => $unit->id,
                    'text' => "{$unit->name} ({$unit->symbol})",
                ];
            });
        });

        if ($query) {
            $units = $units->filter(function ($item) use ($query) {
                return stripos($item['text'], $query) !== false;
            });
        }

        return response()->json($units->values()->take(20));
    }
}
