<?php

namespace App\Console\Commands;

use App\Jobs\InsertCampaignRecords;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GetCampaignData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get GoContact Data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //$token = $this->getToken();
        $reportName = $this->buildReport();

        //Log::info($reportName);

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
            'ownerType' => 'campaign',
            'ownerId' => 'allOwners',
            'startDate' => '2024-11-23 00:00:00',
            'endDate' => '2024-11-23 23:59:59',
            'dataType' => 0,
            'templateId' => 6,
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

            // Format for database insertion
            $chunk[] = [
                'campaign_name' => $data['Campaign Name'] ?? null,
                'call_start' => isset($data['Call start']) ? Carbon::parse($data['Call start']) : null,
                'hold_time' => (int) ($data['Hold Time'] ?? 0),
                'ring_time' => (int) ($data['Ring time'] ?? 0),
                'talk_time' => (int) ($data['Talk Time'] ?? 0),
                'transfer_destination' => $data['Transfer Destination'] ?? null,
                'call_outcome_name' => $data['Call Outcome name'] ?? null,
                'contact_outcome_group' => $data['Contact Outcome Group'] ?? null,
                'agent_id' => (int) ($data['Agent ID'] ?? 0),
                'call_type' => $data['Call Type'] ?? null,
                'ticket' => $data['98-2 Ticket_renovación'] ?? null,
                'call_outcome_group' => $data['Call Outcome Group'] ?? null,
                'queue_time' => (int) ($data['Queue Time'] ?? 0),
                'call_uuid' => $data['Call uuid'] ?? null,
                'agent_first_name' => $data['Agent First Name'] ?? null,
                'wait_time' => (int) ($data['Wait Time'] ?? 0),
                'wrap_up_time' => (int) ($data['Wrap up time'] ?? 0),
                'call_length' => (int) ($data['Call length'] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // When the chunk reaches the batch size, dispatch a job
            if (count($chunk) === $batchSize) {
                InsertCampaignRecords::dispatch($chunk);
                sleep(1);
                $totalRecordsSaved += count($chunk);
                $chunk = []; // Reset the chunk
            }
        }

        // Dispatch the remaining records
        if (!empty($chunk)) {
            InsertCampaignRecords::dispatch($chunk);
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
