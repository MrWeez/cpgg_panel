<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\CreditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;

class RecalculateCreditRunoutJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $userId)
    {
        $this->queue = 'default';

    }
    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping("credit_runout:{$this->userId}")];
    }

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    /**
     * Execute the job.
     */
    public function handle(CreditService $creditService): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        $user->refresh();

        try {
            $result = $creditService->calculateCreditRunout($user);
            $runoutAt = $result['runoutAt'];
            $capped = $result['capped'];
        } catch (\Throwable $exception) {
            $runoutAt = null;
            $capped = false;
        }

        DB::transaction(function () use ($user, $runoutAt, $capped) {
            $user->update([
                'credit_runout_at' => $runoutAt,
                'credit_runout_capped' => $capped,
                'credit_runout_updated_at' => now(),
            ]);
        });
    }
}

