<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 50)->index();
            $table->string('module', 100)->index();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_import')->default(false);
            $table->boolean('can_export')->default(false);
            $table->boolean('can_confirm')->default(false);
            $table->boolean('can_cancel')->default(false);
            $table->boolean('can_restore')->default(false);
            $table->timestamps();

            $table->unique(['role', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
