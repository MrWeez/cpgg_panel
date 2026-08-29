<?php

namespace App\Listeners;

use App\Settings\UserSettings;
use App\Settings\ReferralSettings;
use Illuminate\Support\Facades\DB;
use App\Notifications\ReferralNotification;
class Verified
{
    private $server_limit_increment_after_verify_email;
    private $credits_reward_after_verify_email;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(
        UserSettings $user_settings,
        protected ReferralSettings $referralSettings,
    ) {
        $this->server_limit_increment_after_verify_email = $user_settings->server_limit_increment_after_verify_email;
        $this->credits_reward_after_verify_email = $user_settings->credits_reward_after_verify_email;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        if ($this->referralSettings->require_email_verification) {
            $referral = DB::table('user_referrals')
                ->where('registered_user_id', $event->user->id)
                ->whereNull('rewarded_at')
                ->first();

            if ($referral) {
                $ref_user = \App\Models\User::find($referral->referral_id);

                if ($ref_user) {
                    $ref_user->increment('credits', $this->referralSettings->reward);
                    $ref_user->notify(new ReferralNotification($event->user));

                    DB::table('user_referrals')
                        ->where('registered_user_id', $event->user->id)
                        ->whereNull('rewarded_at')
                        ->update(['rewarded_at' => now()]);
                }
            }
        }
    }
}
