<?php

namespace App\Console\Commands;

use App\Models\DownloadTracker;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteOldDownloadTrackersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:clean-up-download-trackers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete download trackers older than 7 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Delete old download history
        $daysAgo = Carbon::now()->subDays(7);
        DownloadTracker::where('created_at', '<', $daysAgo)->delete();

        $this->info('Old download trackers deleted successfully!');
        return Command::SUCCESS;
    }
}
