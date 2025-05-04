<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportTableData extends Command
{
    protected $signature = 'export:tables {tables*}';
    protected $description = 'Export data from specific tables to JSON files';

    public function handle()
    {
        $tables = $this->argument('tables');
        $dir = database_path('data');
    
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
    
        foreach ($tables as $table) {
            $data = DB::table($table)->get();
            $filePath = "{$dir}/{$table}.json";
            File::put($filePath, json_encode($data, JSON_PRETTY_PRINT));
            $this->info("Exported: {$table} → {$filePath}");
        }
    
        return 0;
    }
    
}

