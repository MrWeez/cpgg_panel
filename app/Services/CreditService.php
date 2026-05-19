<?php

namespace App\Services;

use App\Enums\BillingPriority;
use App\Jobs\RecalculateCreditRunoutJob;
use App\Models\User;
use Carbon\Carbon;

class CreditService
{

    public function reserve(User $user, int $amount): void
    {
        $reserved = User::where('id', $user->id)
            ->where('credits', '>=', $amount)
            ->decrement('credits', $amount);

        if ($reserved === 0) {
            // Either not enough credits or another concurrent request updated this user first.
            throw new \Exception('Unable to reserve credits: either insufficient balance or concurrent provisioning in progress. Please retry.');
        }

        // Queue projection recalculation
        RecalculateCreditRunoutJob::dispatch($user->id);
    }

    public function refund(User $user, int $amount): void
    {
        User::where('id', $user->id)->increment('credits', $amount);

        // Queue projection recalculation
        RecalculateCreditRunoutJob::dispatch($user->id);
    }

    /**
     * Calculate when user will run out of credits using discrete billing simulation.
     *
     * This mirrors the ChargeServers cron behavior (calendar-aware periods, per-server charges).
     *
     * @param  User $user
     * @return array{runoutAt: ?Carbon, capped: bool}
     */
    public function calculateCreditRunout(User $user): array
    {
        $servers = $user->servers()
            ->whereNull('suspended')
            ->with('product')
            ->get();
        if ($servers->isEmpty()) {
            return ['runoutAt' => null, 'capped' => false];
        }

        $hasPositivePrice = $servers->contains(function ($server) {
            return $server->product && $server->product->price > 0;
        });

        if (!$hasPositivePrice) {
            return ['runoutAt' => null, 'capped' => false];
        }

        $now = now();
        $projectionLimit = $now->copy()->addYears(2);
        $serverStates = [];

        foreach ($servers as $server) {
            $product = $server->product;
            if (!$product) {
                continue;
            }

            $period = $product->billing_period;
            $price = $product->price;

            $effectivePriority = $server->effective_billing_priority;
            $priorityValue = $effectivePriority instanceof BillingPriority
                ? $effectivePriority->value
                : (int) $effectivePriority;

            $lastBilled = $server->last_billed ? Carbon::parse($server->last_billed) : $now;
            $nextBilling = $this->advanceBillingDate($lastBilled, $period);

            $serverStates[] = [
                'period' => $period,
                'price' => $price,
                'nextBilling' => $nextBilling,
                'priority' => $priorityValue,
                'createdAt' => $server->created_at?->getTimestamp() ?? 0,
            ];
        }

        if (empty($serverStates)) {
            return ['runoutAt' => null, 'capped' => false];
        }

        $currentCredits = $user->credits;
        $maxIterations = 25000;
        $iterations = 0;

        while ($iterations < $maxIterations) {
            $iterations++;
            $minDate = $serverStates[0]['nextBilling'];
            foreach ($serverStates as $state) {
                if ($state['nextBilling']->lt($minDate)) {
                    $minDate = $state['nextBilling'];
                }
            }

            if ($minDate->gte($projectionLimit)) {
                return ['runoutAt' => $projectionLimit, 'capped' => true];
            }

            $dueServers = array_filter($serverStates, fn($s) => $s['nextBilling']->equalTo($minDate));
            usort($dueServers, function ($a, $b) {
                if ($a['priority'] === $b['priority']) {
                    return $a['createdAt'] <=> $b['createdAt'];
                }

                return $a['priority'] <=> $b['priority'];
            });

            foreach ($dueServers as $s) {
                if ($s['price'] > 0 && $currentCredits < $s['price']) {
                    return ['runoutAt' => $minDate->greaterThan($now) ? $minDate : $now, 'capped' => false];
                }

                if ($s['price'] > 0) {
                    $currentCredits -= $s['price'];
                }
            }

            foreach ($serverStates as &$s) {
                if ($s['nextBilling']->equalTo($minDate)) {
                    $s['nextBilling'] = $this->advanceBillingDate($s['nextBilling'], $s['period']);
                }
            }
            unset($s);
        }

        throw new \RuntimeException('Credit runout simulation exceeded max iterations.');
    }

    /**
     * Advance a billing date by the product billing period.
     */
    private function advanceBillingDate(Carbon $date, string $period): Carbon
    {
        $next = $date->copy();

        switch ($period) {
            case 'hourly':
                return $next->addHour();
            case 'daily':
                return $next->addDay();
            case 'weekly':
                return $next->addWeek();
            case 'monthly':
                return $next->addMonth();
            case 'quarterly':
                return $next->addMonths(3);
            case 'half-annually':
                return $next->addMonths(6);
            case 'annually':
                return $next->addYear();
            default:
                throw new \InvalidArgumentException("Invalid billing period: {$period}");
        }
    }
}
