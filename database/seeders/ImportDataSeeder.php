<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportDataSeeder extends Seeder
{
    protected $tables = ['users', 'pools','pre_moves']; // ← Update with your tables

    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;'); // Disable FK constraints
    
        foreach ($this->tables as $table) {
            $filePath = database_path("data/{$table}.json");
    
            if (File::exists($filePath)) {
                $data = json_decode(File::get($filePath), true);
                DB::table($table)->truncate(); // Now safe
                DB::table($table)->insert($data);
                $this->command->info("Seeded: {$table}");
            } else {
                $this->command->warn("Missing: {$filePath}");
            }
        }
    
        DB::statement('SET FOREIGN_KEY_CHECKS=1;'); // Re-enable FK constraints
    }
    
}
