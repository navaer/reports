<?php

namespace App\Console\Commands;

use App\Jobs\InsertAgentLogRecords;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GetAgentLogData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-agent-log-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //$token = $this->getToken();
        $reportName = $this->buildReport();

        Log::info($reportName);

        $this->downloadReport($reportName);
    }

    public function buildReport()
    {
        $url = env('GOCONTACT_URL');

        $response = Http::asForm()->post($url . 'fs/modules/report-builder/php/reportBuilderRequests.php', [
            'action' => 'downloadReport',
            'domain' => 'c64fac52-2f39-49ce-9650-37d77f05a4af',
            'username' => 'enava',
            'password' => '3c8096e41554432e361b4972cbb96807a252c3c002e4bbdda9204ef9cacd5468469da1071daf5d39c960599430559ebec480b381a6ca4666e47cae850a7e2f58',
            'api_download' => 'true',
            'ownerType' => 'agents',
            'ownerId' => 'allOwners',
            'startDate' => '2024-10-28 00:00:00',
            'endDate' => '2024-11-20 10:59:59',
            'dataType' => 0,
            'templateId' => 38,
            'includeALLOwners' => 'true'
        ]);

        $fileName = str_replace('"', '', $response->body());

        return $fileName;
    }

    public function downloadReport($reportName)
    {
        $url = env('GOCONTACT_URL');

        $response = Http::get($url . 'fs/modules/report-builder/php/reportBuilderRequests.php', [
            'action' => 'getCsvReportFile',
            'domain' => 'c64fac52-2f39-49ce-9650-37d77f05a4af',
            'username' => 'enava',
            'password' => '3c8096e41554432e361b4972cbb96807a252c3c002e4bbdda9204ef9cacd5468469da1071daf5d39c960599430559ebec480b381a6ca4666e47cae850a7e2f58',
            'api_download' => 'true',
            'file' => $reportName
        ]);

        // Split the rows
        $rows = explode("\n", trim($response->body()));

        // Extract and clean headers
        $headers = array_map(
            fn($header) => trim($header, '"'),
            explode(';', array_shift($rows))
        );

        // Prepare data for insertion
        $batchSize = 500;
        $chunk = [];
        $totalRecordsSaved = 0;

        foreach ($rows as $index => $row) {
            $columns = array_map(
                fn($column) => trim($column, '"'),
                explode(';', $row)
            );

            // Skip rows with mismatched column count
            if (count($columns) !== count($headers)) {
                continue;
            }

            $data = array_combine($headers, $columns);

            // Validate event_metadata
            $metadata = $data['Event Metadata'] ?? null;

            // If event_metadata is empty or invalid, replace it with '{}'
            if (empty($metadata) || !is_string($metadata) || json_decode($metadata) === null) {
                $metadata = '{}';
            }

            // Format for database insertion
            $chunk[] = [
                'event_subtype_name' => $data['Event Subtype Name'] ?? null,
                'event_type' => $data['Event Type'] ?? null,
                'user_id' => (int) ($data['User Id'] ?? 0),
                'event_date' => isset($data['Event Date']) ? Carbon::parse($data['Event Date']) : null,
                'user_full_name' => $data['User Full Name'] ?? null,
                'username' => $data['Username'] ?? null,
                'event_subtype' => $data['Event Subtype'] ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // When the chunk reaches the batch size, dispatch a job
            if (count($chunk) === $batchSize) {
                InsertAgentLogRecords::dispatch($chunk);
                sleep(1);
                $totalRecordsSaved += count($chunk);
                $chunk = []; // Reset the chunk
            }
        }

        // Dispatch the remaining records
        if (!empty($chunk)) {
            InsertAgentLogRecords::dispatch($chunk);
            $totalRecordsSaved += count($chunk);
        }

        Log::info("Total records saved: " . $totalRecordsSaved);
    }

    public function getToken()
    {

        $username = env('GOCONTACT_USERNAME');
        $password = env('GOCONTACT_PASSWORD');
        $url = env('GOCONTACT_URL');

        $response = Http::withBasicAuth($username, $password)->post($url . 'poll/auth/token');

        $token = $response->object();
        return $token->token;
    }
}
