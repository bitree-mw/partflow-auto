<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Frozen schema baseline through 2026-09-15. Use schema:install-baseline only.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('type', ['shop', 'warehouse', 'branch'])->default('shop');
            $table->string('location')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
        });

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

        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            $table->enum('contact_type', [
                'customer',
                'supplier',
                'both',
            ])->default('customer');

            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number')->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['contact_type', 'is_active']);
            $table->index(['name', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('business_settings');
        Schema::dropIfExists('user_site_access');
        Schema::dropIfExists('sites');
    }
};
