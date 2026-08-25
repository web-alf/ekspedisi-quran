<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\Warehouse\BulkStatusUpdateJob;
use App\Jobs\Warehouse\BulkBoxProcessingJob;
use App\Models\PackingBox;
use App\Models\StatusPengiriman;
use App\Models\User;

class TestQueueJob extends Command
{
    protected $signature = 'queue:test {type=status} {--user=1}';
    protected $description = 'Test queue jobs for warehouse operations';

    public function handle()
    {
        $type = $this->argument('type');
        $userId = $this->option('user');

        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        // Set the authenticated user for the job
        auth()->login($user);

        switch ($type) {
            case 'status':
                $this->testBulkStatusUpdate();
                break;
            case 'box':
                $this->testBulkBoxProcessing();
                break;
            default:
                $this->error("Unknown test type: {$type}. Use 'status' or 'box'.");
                return 1;
        }

        return 0;
    }

    private function testBulkStatusUpdate()
    {
        $this->info('Testing Bulk Status Update Job...');

        // Get some sample boxes
        $boxes = PackingBox::with('packingItems')->limit(3)->get();
        
        if ($boxes->isEmpty()) {
            $this->error('No boxes found in database. Please create some boxes first.');
            return;
        }

        $boxIds = $boxes->pluck('id')->toArray();
        
        // Get a status to update to
        $status = StatusPengiriman::first();
        if (!$status) {
            $this->error('No status found in database.');
            return;
        }

        $this->info("Dispatching bulk status update for " . count($boxIds) . " boxes to status: {$status->nama}");

        // Dispatch the job
        $job = BulkStatusUpdateJob::dispatch(
            $boxIds,
            $status->id,
            'Test address update',
            'Test bulk operation via CLI',
            'Test location',
            null,
            auth()->id()
        );

        $this->info("Job dispatched with ID: " . $job->getJobId());
        $this->info("Run 'php artisan queue:work' to process the job.");
    }

    private function testBulkBoxProcessing()
    {
        $this->info('Testing Bulk Box Processing Job...');

        // Get some sample box codes
        $boxes = PackingBox::limit(2)->get();
        
        if ($boxes->isEmpty()) {
            $this->error('No boxes found in database. Please create some boxes first.');
            return;
        }

        $boxCodes = $boxes->pluck('kode_kerdus')->toArray();

        $this->info("Dispatching bulk validation for " . count($boxCodes) . " boxes");

        // Dispatch the job
        $job = BulkBoxProcessingJob::dispatch(
            $boxCodes,
            'validate',
            ['test_mode' => true],
            auth()->id()
        );

        $this->info("Job dispatched with ID: " . $job->getJobId());
        $this->info("Run 'php artisan queue:work' to process the job.");
    }
}
