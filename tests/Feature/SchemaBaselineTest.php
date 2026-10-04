<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaBaselineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Dedicated in-memory connection: never use the application's database.
        config(['database.default' => 'baseline_test', 'database.connections.baseline_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
        ]]);
    }

    protected function tearDown(): void
    {
        DB::purge('baseline_test');
        parent::tearDown();
    }

    public function test_baseline_matches_historical_schema_and_does_not_replay_migrations(): void
    {
        $this->artisan('schema:install-baseline')->assertSuccessful();
        $baseline = $this->schemaSnapshot();
        $manifest = require database_path('baseline/manifest.php');
        $this->assertSame($manifest, DB::table('migrations')->orderBy('migration')->pluck('migration')->all());
        $this->artisan('migrate')->assertSuccessful();
        $this->assertSame($baseline, $this->schemaSnapshot());

        DB::purge('baseline_test');
        $this->artisan('migrate')->assertSuccessful();
        $this->assertSame($this->schemaSnapshot(), $baseline);
    }

    public function test_existing_database_is_rejected_without_changes(): void
    {
        Schema::create('existing_data', fn ($table) => $table->string('value'));
        DB::table('existing_data')->insert(['value' => 'preserve me']);
        $this->artisan('schema:install-baseline')->assertFailed();
        $this->assertSame('preserve me', DB::table('existing_data')->sole()->value);
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('migrations'));
    }

    private function schemaSnapshot(): array
    {
        return collect(Schema::getTableListing())->sort()->mapWithKeys(function ($table) {
            $columns = collect(Schema::getColumns($table))->map(fn ($column) => Arr::except($column, ['generation']))->keyBy('name')->sortKeys()->all();
            $indexes = collect(Schema::getIndexes($table))->map(fn ($index) => Arr::except($index, ['name']))->sortBy(fn ($index) => json_encode($index))->values()->all();
            $foreignKeys = collect(Schema::getForeignKeys($table))->map(fn ($key) => Arr::except($key, ['name']))->sortBy(fn ($key) => json_encode($key))->values()->all();

            return [$table => compact('columns', 'indexes', 'foreignKeys')];
        })->all();
    }
}
