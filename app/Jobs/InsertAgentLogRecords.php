<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InsertAgentLogRecords implements ShouldQueue
{
    use Queueable;

    protected array $records;

    /**
     * Create a new job instance.
     */
    public function __construct(array $records)
    {
        $this->records = $records;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Insert records in bulk for efficiency
            Log::info(count($this->records));
            DB::table('agents_event_logs')->insert($this->records);

        } catch (\Exception $e) {
            Log::error("Failed to insert call records: " . $e->getMessage());
        }
    }
}
