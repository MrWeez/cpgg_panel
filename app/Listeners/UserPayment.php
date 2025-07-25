<?php

namespace App\Listeners;

use App\Enums\PaymentStatus;
use App\Events\PaymentEvent;
use App\Facades\Currency;
use App\Models\User;
use App\Settings\DiscordSettings;
use App\Models\PartnerDiscount;
use App\Settings\GeneralSettings;
use App\Settings\ReferralSettings;
use App\Settings\UserSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UserPayment implements ShouldQueue
{
    private $server_limit_increment_after_irl_purchase;

    private $referral_mode;

    private $referral_percentage;

    private $referral_always_give_commission;

    private $credits_display_name;

    private $role_id_on_purchase;

    private $role_on_purchase;

    private $bot_token;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(UserSettings $user_settings, ReferralSettings $referral_settings, GeneralSettings $general_settings, DiscordSettings $discord_settings)
    {
        $this->server_limit_increment_after_irl_purchase = $user_settings->server_limit_increment_after_irl_purchase;
        $this->referral_mode = $referral_settings->mode;
        $this->referral_percentage = $referral_settings->percentage;
        $this->referral_always_give_commission = $referral_settings->always_give_commission;
        $this->credits_display_name = $general_settings->credits_display_name;
        $this->role_id_on_purchase = $discord_settings->role_id_on_purchase;
        $this->role_on_purchase = $discord_settings->role_on_purchase;
        $this->bot_token = $discord_settings->bot_token;

    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\PaymentEvent  $event
     * @return void
     */
    public function handle(PaymentEvent $event)
    {
        $user = $event->user;
        $payment = $event->payment;
        $shopProduct = $event->shopProduct;

        // only update user if payment is paid
        if ($payment->status != PaymentStatus::PAID) {
            return;
        }

        //update server limit
        if (!$user->email_verified_reward && $this->server_limit_increment_after_irl_purchase !== 0 && $user->server_limit < $this->server_limit_increment_after_irl_purchase) {
            $user->increment('server_limit', $this->server_limit_increment_after_irl_purchase);
        }

        //update User with bought item
        if ($shopProduct->type == "Credits") {
            $user->increment('credits', $shopProduct->quantity);
        } elseif ($shopProduct->type == "Server slots") {
            $user->increment('server_limit', $shopProduct->quantity);
        }

        //give referral commission always
        if (($this->referral_mode === "commission" || $this->referral_mode === "both") && $shopProduct->type == "Credits" && $this->referral_always_give_commission) {
            if ($ref_user = DB::table("user_referrals")->where('registered_user_id', '=', $user->id)->first()) {
                $ref_user = User::findOrFail($ref_user->referral_id);
                $increment = number_format($shopProduct->quantity * (PartnerDiscount::getCommission($ref_user->id, $this->referral_percentage)) / 100, 0, "", "");
                $ref_user->increment('credits', $increment);

                //LOGS REFERRALS IN THE ACTIVITY LOG
                activity()
                    ->performedOn($user)
                    ->causedBy($ref_user)
                    ->log('gained ' . $increment . ' ' . $this->credits_display_name . ' for commission-referral of ' . $user->name . ' (ID:' . $user->id . ')');
            }
        }
        //update role give Referral-reward
        if ($user->hasRole(4)) {
            $user->syncRoles(3);

            //give referral commission only on first purchase
            if (($this->referral_mode === "commission" || $this->referral_mode === "both") && $shopProduct->type == "Credits" && !$this->referral_always_give_commission) {
                if ($ref_user = DB::table("user_referrals")->where('registered_user_id', '=', $user->id)->first()) {
                    $ref_user = User::findOrFail($ref_user->referral_id);
                    $increment = number_format($shopProduct->quantity * (PartnerDiscount::getCommission($ref_user->id, $this->referral_percentage)) / 100, 0, "", "");
                    $ref_user->increment('credits', $increment);

                    //LOGS REFERRALS IN THE ACTIVITY LOG
                    activity()
                        ->performedOn($user)
                        ->causedBy($ref_user)
                        ->log('gained ' . $increment . ' ' . $this->credits_display_name . ' for commission-referral of ' . $user->name . ' (ID:' . $user->id . ')');
                }
            }
        }

        //set discord role
        if(!empty($this->bot_token) && $this->role_on_purchase && !empty($this->role_id_on_purchase) && !empty($user->discordUser)) {
            $discordUser = $user->discordUser;
            $success = $discordUser->addOrRemoveRole('add', $this->role_id_on_purchase);

            if (!$success) {
                //Activity Log moved directly to discordUser->AddOrRemoveRole()
                Log::error("Couldnt add discord User. UserPayment Class L124");
            }
        }


        // LOGS PAYMENT IN THE ACTIVITY LOG
        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->log($payment->type == 'Credits'
                ? 'bought ' . Currency::formatForDisplay($payment->amount) . ' ' . $payment->type . ' for ' . Currency::formatForDisplay($payment->total_price) . $payment->currency_code
                : 'bought ' . $payment->amount . ' ' . $shopProduct->type . ' for ' . Currency::formatForDisplay($payment->total_price) . $payment->currency_code
            );
    }
}
