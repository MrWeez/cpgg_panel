<?php

namespace App\Http\Controllers;

use App\Jobs\RecalculateCreditRunoutJob;
use App\Models\PartnerDiscount;
use App\Models\UsefulLink;
use App\Settings\GeneralSettings;
use App\Settings\WebsiteSettings;
use App\Settings\ReferralSettings;
use Illuminate\Support\Facades\Auth;
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
            // Check if projection has been computed
            if ($user->credit_runout_at) {
                // Use existing projection
                $timeLeft = $this->formatTimeLeft($user->credit_runout_at);
                $timeLeft['message'] = 'Estimated run out: ' . $user->credit_runout_at->format('d.m.Y H:i');
            } elseif (!$user->credit_runout_updated_at) {
                // Queue projection calculation and show placeholder
                RecalculateCreditRunoutJob::dispatch($user->id);

                $timeLeft = [
                    'value' => '...',
                    'unit' => '',
                    'bg' => self::TIME_LEFT_BG_WARNING,
                    'message' => 'Calculating estimate...'
                ];
            }
            // if the  credit_runout_at is null and credit_runout_updated_at exists,
            // it means user has no active billing (all servers suspended or canceled)
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
}
