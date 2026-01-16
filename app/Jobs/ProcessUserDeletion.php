<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUserDeletion implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected string $entityName;
    protected int $entityId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $entityName, int $entityId)
    {
        $this->entityName = $entityName;
        $this->entityId = $entityId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $entityColumnMap = [
            'country' => 'country_id',
            'region' => 'region_id',
            'province' => 'province_id',
            'rehab_service' => 'clinic_id',
            'phc_service' => 'phc_service_id',
        ];

        $column = $entityColumnMap[$this->entityName];

        $deletedRows = User::where($column, $this->entityId)->delete();

        Log::info('Successfully deleted ' . $deletedRows . ' therapists belonging to ' . $this->entityName . ' id ' . $this->entityId);
    }
}
