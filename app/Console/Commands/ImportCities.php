<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCities extends Command
{

    // Importing the json might require increasing allowed memory (shortcut for demonstration purposes):    php -d memory_limit=512M artisan import:cities
    // Can be fixed with streaming logic that can funnel through limited available memory

    protected $signature = 'import:cities';
    protected $description = 'Import cities from JSON file into database';

    public function handle()
    {
        $this->info('Importing cities for json...');

        // get data from json
        $jsonPath = storage_path('app/data/cities500.json');
        $cities = json_decode(file_get_contents($jsonPath), true);

        $chunks = array_chunk($cities, 1000);

        DB::statement('PRAGMA synchronous = OFF');
        DB::statement('PRAGMA journal_mode = MEMORY');

        DB::table('cities')->truncate();

        $bar = $this->output->createProgressBar(count($cities));

        // Loop through every chunk one by one and get info from json for each city
        foreach ($chunks as $chunk) {
            $records = [];
            foreach ($chunk as $city) {
                $records[] = [
                    'name' => $city['name'],
                    'country' => $city['country'],
                    'latitude' => $city['lat'] ?? null,
                    'longitude' => $city['lon'] ?? null,
                    'population' => $city['pop'] ?? 0,
                    'region' => $city['admin1'] ?? null,
                ];
            }

            DB::table('cities')->insert($records);
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->info("\nImport completed!");
    }
}
