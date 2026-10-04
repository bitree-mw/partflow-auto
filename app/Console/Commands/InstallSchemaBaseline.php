<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstallSchemaBaseline extends Command
{
    use ConfirmableTrait;

    protected $signature = 'schema:install-baseline {--force : Allow installation in production}';

    protected $description = 'Install the grouped September 2026 schema into an empty database only';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'sqlite'], true)) {
            $this->error('The baseline supports MySQL and SQLite only.');

            return self::FAILURE;
        }

        if (Schema::getTables() !== [] || Schema::getViews() !== []) {
            $this->error('Baseline installation requires an empty database. Import existing databases with their migrations table and use migrate instead.');

            return self::FAILURE;
        }

        // MySQL DDL is not transactional. A failed installation must be inspected;
        // never automatically drop tables or mark a partially installed schema complete.
        foreach (glob(database_path('baseline/[0-9]*.php')) as $file) {
            (require $file)->up();
            $this->components->info(pathinfo($file, PATHINFO_FILENAME));
        }

        $repository = app('migration.repository');
        $repository->createRepository();
        DB::transaction(function () use ($repository): void {
            foreach (require database_path('baseline/manifest.php') as $migration) {
                $repository->log($migration, 1);
            }
        });

        $this->info('Baseline installed. Run migrate to apply migrations newer than the baseline.');

        return self::SUCCESS;
    }
}
