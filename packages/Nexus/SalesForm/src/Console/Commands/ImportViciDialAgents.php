<?php

namespace Nexus\SalesForm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Nexus\SalesForm\Models\ViciDialAgent;

class ImportViciDialAgents extends Command
{
    protected $signature = 'nexus:import-vicidial
        {file : Path to the ViciDial agent CSV export}
        {--truncate : Empty the table before importing}
        {--chunk=1000 : Rows per insert batch}';

    protected $description = 'Import the ViciDial eXp agent list into vicidial_agents for phone lookups.';

    /**
     * CSV header => table column. Anything not listed is ignored.
     */
    protected array $map = [
        'phone_number'     => 'phone',
        'alt_phone'        => 'alt_phone',
        'vendor_lead_code' => 'vendor_lead_code',
        'source_id'        => 'source_id',
        'title'            => 'title',
        'first_name'       => 'first_name',
        'middle_initial'   => 'middle_initial',
        'last_name'        => 'last_name',
        'address1'         => 'address1',
        'address2'         => 'address2',
        'address3'         => 'address3',
        'city'             => 'city',
        'state'            => 'state',
        'province'         => 'province',
        'postal_code'      => 'postal_code',
        'country_code'     => 'country_code',
        'gender'           => 'gender',
        'date_of_birth'    => 'date_of_birth',
        'email'            => 'email',
        'comments'         => 'comments',
        'rank'             => 'rank',
        'owner'            => 'owner',
    ];

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! is_readable($file)) {
            $this->error("Cannot read file: $file");

            return self::FAILURE;
        }

        if ($this->option('truncate')) {
            DB::table('vicidial_agents')->truncate();
            $this->warn('Table truncated.');
        }

        $handle = fopen($file, 'r');

        $header = fgetcsv($handle);

        if (! $header) {
            $this->error('CSV appears to be empty.');
            fclose($handle);

            return self::FAILURE;
        }

        $header = array_map(fn ($h) => trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF"), $header);
        $indexes = [];

        foreach ($this->map as $csvColumn => $dbColumn) {
            $position = array_search($csvColumn, $header, true);

            if ($position !== false) {
                $indexes[$dbColumn] = $position;
            }
        }

        if (! isset($indexes['phone'])) {
            $this->error('CSV has no `phone_number` column; nothing to key lookups on.');
            fclose($handle);

            return self::FAILURE;
        }

        $chunkSize = max(100, (int) $this->option('chunk'));
        $now = now();
        $batch = [];
        $imported = 0;
        $skipped = 0;

        $this->info('Importing…');

        while (($row = fgetcsv($handle)) !== false) {
            $record = ['created_at' => $now, 'updated_at' => $now];

            foreach ($indexes as $dbColumn => $position) {
                $value = $row[$position] ?? null;
                $record[$dbColumn] = ($value === '' ? null : $value);
            }

            $record['phone'] = ViciDialAgent::normalizePhone($record['phone'] ?? '');
            $record['alt_phone'] = $record['alt_phone']
                ? ViciDialAgent::normalizePhone($record['alt_phone'])
                : null;

            // A row without a usable phone number can never be looked up.
            if ($record['phone'] === '') {
                $skipped++;

                continue;
            }

            $batch[] = $record;

            if (count($batch) >= $chunkSize) {
                DB::table('vicidial_agents')->insert($batch);
                $imported += count($batch);
                $batch = [];
                $this->output->write('.');
            }
        }

        if ($batch) {
            DB::table('vicidial_agents')->insert($batch);
            $imported += count($batch);
        }

        fclose($handle);

        $this->newLine();
        $this->info("Imported $imported agents. Skipped $skipped row(s) without a phone number.");
        $this->info('Total in table: '.DB::table('vicidial_agents')->count());

        return self::SUCCESS;
    }
}
