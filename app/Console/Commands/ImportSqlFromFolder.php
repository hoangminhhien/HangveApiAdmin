<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportSqlFromFolder extends Command
{
    protected $signature = 'db:import-folder {folder_path}';
    protected $description = 'Import all .sql files from a folder into the database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */


    public function handle()
    {
        $folderPath = $this->argument('folder_path');

        if (!is_dir($folderPath)) {
            $this->error("Folder not found: $folderPath");
            return 1;
        }

        // Lấy tất cả file .sql trong folder
        $files = File::files($folderPath);

        $sqlFiles = array_filter($files, function ($file) {
            return strtolower($file->getExtension()) === 'sql';
        });

        if (empty($sqlFiles)) {
            $this->warn("No SQL files found in: $folderPath");
            return 0;
        }

        foreach ($sqlFiles as $file) {
            $this->line("Importing: " . $file->getFilename());

            try {
                $sql = File::get($file->getPathname());
                DB::unprepared($sql);
                $this->info("✓ Imported: " . $file->getFilename());
            } catch (\Exception $e) {
                $this->error("✗ Failed to import {$file->getFilename()}: " . $e->getMessage());
            }
        }

        return 0;
    }
}
