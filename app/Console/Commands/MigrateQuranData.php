<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class MigrateQuranData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-quran-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate Quran texts from SQLite to MySQL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sqlitePath = database_path('database.sqlite');

        if (!file_exists($sqlitePath)) {
            $this->error("SQLite database not found at: {$sqlitePath}");
            return;
        }

        // Configure dynamic sqlite connection to point specifically to the file
        Config::set('database.connections.sqlite_source', [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
            'prefix' => '',
        ]);

        $this->info('Starting migration of quran_texts...');

        $sourceCount = DB::connection('sqlite_source')->table('quran_texts')->count();
        $this->info("Found {$sourceCount} records in SQLite.");

        if ($sourceCount === 0) {
            $this->warn('No records to migrate.');
            return;
        }

        // Ensure target table exists and is empty
        if (!Schema::hasTable('quran_texts')) {
            $this->error('Target table "quran_texts" does not exist in MySQL. Please run migrations first.');
            return;
        }

        if ($this->confirm('Truncate MySQL "quran_texts" table before migration?', true)) {
            Schema::disableForeignKeyConstraints();
            DB::table('quran_texts')->truncate();
            Schema::enableForeignKeyConstraints();
            $this->info('Target table truncated.');
        }

        $bar = $this->output->createProgressBar($sourceCount);
        $bar->start();

        Schema::disableForeignKeyConstraints();
        DB::connection('sqlite_source')->table('quran_texts')->orderBy('id')->chunk(500, function ($rows) use ($bar) {
            $data = json_decode(json_encode($rows), true);
            DB::table('quran_texts')->insert($data);
            $bar->advance(count($data));
        });
        Schema::enableForeignKeyConstraints();

        $bar->finish();
        $this->info("\nMigration completed successfully!");
    }
}
