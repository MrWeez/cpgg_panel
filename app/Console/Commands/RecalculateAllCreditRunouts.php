<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Jobs\RecalculateCreditRunoutJob;
use Illuminate\Console\Command;

class RecalculateAllCreditRunouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:recalculate-all {--chunk=100 : Number of users to process per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate credit runout projections for all users (backfill after deployment)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $chunkSize = (int) $this->option('chunk');
        $total = User::count();

        $this->info("Queueing credit runout recalculation for {$total} users...");

        User::query()
            ->chunk($chunkSize, function ($users) {
                foreach ($users as $user) {
                    RecalculateCreditRunoutJob::dispatch($user->id);
                }

                $this->line("Queued batch of users...");
            });

        $this->info("All users queued for credit runout recalculation.");
        $this->info("Monitor your queue worker/cronjob for completion: php artisan queue:work");
    }
}

