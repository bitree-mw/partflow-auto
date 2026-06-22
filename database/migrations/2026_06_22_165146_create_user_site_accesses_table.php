<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_site_access', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('site_id')
                ->constrained('sites')
                ->cascadeOnDelete();

            $table->enum('access_level', [
                'view_only',
                'sales',
                'stock',
                'manager',
                'admin',
            ])->default('view_only');

            $table->boolean('can_view_stock')->default(true);
            $table->boolean('can_make_sales')->default(false);
            $table->boolean('can_receive_stock')->default(false);
            $table->boolean('can_transfer_stock')->default(false);
            $table->boolean('can_adjust_stock')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'site_id']);
            $table->index(['site_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_site_access');
    }
};
