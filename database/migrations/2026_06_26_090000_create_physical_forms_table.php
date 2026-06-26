<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_forms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('physical_form_id')
                ->nullable()
                ->after('physical_form')
                ->constrained('physical_forms')
                ->nullOnDelete();
        });

        $defaults = [
            ['code' => 'liquid', 'name' => 'Liquid'],
            ['code' => 'powder', 'name' => 'Powder'],
            ['code' => 'wax', 'name' => 'Wax'],
            ['code' => 'other', 'name' => 'Others'],
        ];

        foreach ($defaults as $record) {
            DB::table('physical_forms')->insert([
                'code' => $record['code'],
                'name' => $record['name'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $existingForms = DB::table('products')
            ->whereNotNull('physical_form')
            ->distinct()
            ->pluck('physical_form')
            ->filter()
            ->values();

        foreach ($existingForms as $value) {
            $normalized = Str::of($value)
                ->trim()
                ->lower()
                ->replace([' ', '-'], '_')
                ->toString();

            $existingId = DB::table('physical_forms')->where('code', $normalized)->value('id');

            if (!$existingId) {
                $existingId = DB::table('physical_forms')->insertGetId([
                    'code' => $normalized,
                    'name' => Str::of($normalized)->replace('_', ' ')->title()->toString(),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('products')
                ->where('physical_form', $value)
                ->whereNull('physical_form_id')
                ->update(['physical_form_id' => $existingId]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('physical_form_id');
        });

        Schema::dropIfExists('physical_forms');
    }
};
