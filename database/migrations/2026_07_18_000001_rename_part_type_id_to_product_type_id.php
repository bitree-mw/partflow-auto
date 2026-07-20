<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'part_type_id') || Schema::hasColumn('products', 'product_type_id')) {
            return;
        }

        if (! $this->indexExists('products_car_model_id_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('car_model_id');
            });
        }

        if ($this->foreignKeyExists('part_type_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['part_type_id']);
            });
        }

        if ($this->indexExists('products_car_model_id_part_type_id_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['car_model_id', 'part_type_id']);
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('part_type_id', 'product_type_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('product_type_id')
                ->references('id')
                ->on('product_types')
                ->restrictOnDelete();
            $table->index(['car_model_id', 'product_type_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'product_type_id') || Schema::hasColumn('products', 'part_type_id')) {
            return;
        }

        if ($this->foreignKeyExists('product_type_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['product_type_id']);
            });
        }

        if ($this->indexExists('products_car_model_id_product_type_id_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['car_model_id', 'product_type_id']);
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('product_type_id', 'part_type_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('part_type_id')
                ->references('id')
                ->on('product_types')
                ->restrictOnDelete();
            $table->index(['car_model_id', 'part_type_id']);
        });

        if ($this->indexExists('products_car_model_id_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['car_model_id']);
            });
        }
    }

    private function foreignKeyExists(string $column): bool
    {
        return collect(Schema::getForeignKeys('products'))
            ->contains(fn (array $foreignKey): bool => in_array($column, $foreignKey['columns'] ?? [], true));
    }

    private function indexExists(string $name): bool
    {
        return collect(Schema::getIndexes('products'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
