<?php

use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('unit_id')->constrained()->restrictOnDelete();
            $table->string('physical_form', 50)->nullable()->after('name')->index();
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->string('storage_location', 150)->nullable()->after('expiry_date')->index();
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->string('storage_location', 150)->nullable()->after('received_at')->index();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->foreignId('supplier_id')->nullable()->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $fallbackSupplierId = null;

        if (DB::table('products')->whereNull('supplier_id')->exists() || DB::table('purchases')->whereNull('supplier_id')->exists()) {
            $fallbackSupplierId = Supplier::query()->first()?->id;

            if ($fallbackSupplierId === null) {
                $fallbackSupplierId = Supplier::query()->insertGetId([
                    'name' => 'Rollback Supplier',
                    'contact_person' => null,
                    'email' => null,
                    'phone' => null,
                    'address' => null,
                    'notes' => 'Auto-created for migration rollback.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('products')->whereNull('supplier_id')->update(['supplier_id' => $fallbackSupplierId]);
            DB::table('purchases')->whereNull('supplier_id')->update(['supplier_id' => $fallbackSupplierId]);
        }

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->foreignId('supplier_id')->nullable(false)->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropIndex(['storage_location']);
            $table->dropColumn('storage_location');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropIndex(['storage_location']);
            $table->dropColumn('storage_location');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['physical_form']);
            $table->dropColumn(['supplier_id', 'physical_form']);
        });
    }
};
