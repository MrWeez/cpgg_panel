<?php

namespace App\Http\Controllers;

use App\Jobs\RecalculateCreditRunoutJob;
use App\Models\PartnerDiscount;
use App\Models\UsefulLink;
use App\Settings\GeneralSettings;
use App\Settings\WebsiteSettings;
use App\Settings\ReferralSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;


class HomeController extends Controller
{
    const TIME_LEFT_BG_SUCCESS = 'bg-success';
    const TIME_LEFT_BG_WARNING = 'bg-warning';
    const TIME_LEFT_BG_DANGER = 'bg-danger';

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Format time left for display
     */
    protected function formatTimeLeft($date)
    {
        if (!$date) return null;

        $now = now();
        $daysLeft = $now->diffInDays($date, false);
        $hoursLeft = $now->diffInHours($date, false);
        $minutesLeft = $now->diffInMinutes($date, false);

        if ($daysLeft > 1) {
            return [
                'value' => floor($daysLeft),
                'unit' => 'days',
                'bg' => $daysLeft >= 15 ? self::TIME_LEFT_BG_SUCCESS :
                    ($daysLeft <= 7 ? self::TIME_LEFT_BG_DANGER : self::TIME_LEFT_BG_WARNING)
            ];
        }

        if ($hoursLeft > 1) {
            return [
                'value' => floor($hoursLeft),
                'unit' => 'hours',
                'bg' => $hoursLeft <= 24 ? self::TIME_LEFT_BG_DANGER : self::TIME_LEFT_BG_WARNING
            ];
        }

        if ($minutesLeft > 1) {
            return [
                'value' => floor($minutesLeft),
                'unit' => 'minutes',
                'bg' => self::TIME_LEFT_BG_DANGER
            ];
        }

        return [
            'value' => 'Less than 1',
            'unit' => 'minute',
            'bg' => self::TIME_LEFT_BG_DANGER
        ];
    }

    /**
     * Show the application dashboard
     */
    public function index(GeneralSettings $general_settings, WebsiteSettings $website_settings, ReferralSettings $referral_settings)
    {
        $user = Auth::user();
        $credits = $user->credits;
        $timeLeft = null;

        if ($credits > 0) {
            $stale = $user->credit_runout_updated_at
                ? $user->credit_runout_updated_at->lt(now()->subHour())
                : true;

            if ($stale) {
                $this->queueRunoutRecalc($user->id);
                $timeLeft = $this->calculatingTimeLeft();
            } elseif ($user->credit_runout_capped) {
                $timeLeft = [
                    'value' => 'More than 2',
                    'unit' => 'years',
                    'bg' => self::TIME_LEFT_BG_SUCCESS,
                    'message' => 'Estimated run out: More than 2 years'
                ];
            } elseif ($user->credit_runout_at) {
                $timeLeft = $this->formatTimeLeft($user->credit_runout_at);
                $timeLeft['message'] = 'Estimated run out: ' . $user->credit_runout_at->format('d.m.Y H:i');
            }
            // If credit_runout_at is null and not capped, user has no active billing.
        }

        return view('home')->with([
            'usage' => $user->creditUsage(),
            'credits' => $credits,
            'useful_links_dashboard' => UsefulLink::where("position","like","%dashboard%")->get()->sortby("id"),
            'timeLeft' => $timeLeft,
            'numberOfReferrals' => DB::table('user_referrals')->where('referral_id', '=', $user->id)->count(),
            'partnerDiscount' => PartnerDiscount::where('user_id', $user->id)->first(),
            'myDiscount' => PartnerDiscount::getDiscount(),
            'general_settings' => $general_settings,
            'website_settings' => $website_settings,
            'referral_settings' => $referral_settings
        ]);
    }

    private function queueRunoutRecalc(int $userId): void
    {
        $lock = Cache::lock("credit-runout-recalc:{$userId}", 300);
        if ($lock->get()) {
            RecalculateCreditRunoutJob::dispatch($userId);
        }
    }

    private function calculatingTimeLeft(): array
    {
        return [
            'value' => '...',
            'unit' => '',
            'bg' => self::TIME_LEFT_BG_WARNING,
            'message' => 'Calculating estimate...'
        ];
    }
}
