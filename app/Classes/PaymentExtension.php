<?php

namespace App\Classes;

use App\Helpers\CurrencyHelper;
use App\Models\Payment;
use App\Models\ShopProduct;

abstract class PaymentExtension extends AbstractExtension
{
    protected static function currencyHelper(): CurrencyHelper
    {
        return resolve(CurrencyHelper::class);
    }

    /**
     * Returns the redirect url of the payment gateway to redirect the user to
     */
    abstract public static function getRedirectUrl(Payment $payment, ShopProduct $shopProduct, int $totalPrice): string;

    /**
     * Returns the list of ISO 4217 currency codes this gateway accepts for checkout,
     * or null to allow every currency.
     *
     * @return array<int, string>|null
     */
    public static function getSupportedCurrencies(): ?array
    {
        return null;
    }

    /**
     * Returns the minimum order value required by this gateway for the given currency,
     * in the currency's display units, or null if this gateway has no minimum.
     */
    public static function getMinimumPrice(string $currencyCode): ?float
    {
        return null;
    }

    /**
     * Determines whether this gateway can be used for a checkout with the given currency
     * and total price (in the currency's display units).
     *
     * @return array{available: bool, reason: string|null}
     */
    public static function isAvailableForCheckout(string $currencyCode, float $totalPrice): array
    {
        $currency = strtoupper($currencyCode);

        $supportedCurrencies = static::getSupportedCurrencies();
        if (is_array($supportedCurrencies) && !in_array($currency, $supportedCurrencies, true)) {
            return [
                'available' => false,
                'reason' => __('This payment gateway does not support the :currency currency', ['currency' => $currency]),
            ];
        }

        $minimum = static::getMinimumPrice($currency);
        if ($minimum !== null && $totalPrice < $minimum) {
            $formattedMinimum = resolve(CurrencyHelper::class)->formatToCurrency((int) round($minimum * 1000), $currency);

            return [
                'available' => false,
                'reason' => __('This payment gateway requires a minimum order of :amount', ['amount' => $formattedMinimum]),
            ];
        }

        return ['available' => true, 'reason' => null];
    }

    /**
     * Returns true if the payment gateway supports rechecking the payment status
     */
    public static function supportsRecheck(): bool
    {
        return false;
    }

    /**
     * Recheck the payment status with the payment gateway
     */
    public static function recheckPayment(Payment $payment): void
    {
        throw new \Exception('Recheck not implemented');
    }
}
