<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_profiles', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->string('code', 50)->unique();

            $table->enum('tax_type', [
                'vat',
                'none',
            ])->default('vat');

            $table->decimal('tax_rate', 5, 2)->default(0);

            $table->enum('price_mode', [
                'inclusive',
                'exclusive',
                'exempt',
                'none',
            ])->default('inclusive');

            $table->boolean('is_exempt')->default(false);
            $table->text('exemption_reason')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tax_type', 'price_mode']);
            $table->index(['is_default', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_profiles');
    }
};
