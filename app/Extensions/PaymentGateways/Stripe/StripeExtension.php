<?php

namespace App\Extensions\PaymentGateways\Stripe;

use App\Classes\PaymentExtension;
use App\Models\Payment;
use App\Models\ShopProduct;
use App\Traits\HandlesGatewayPayments;
use Exception;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeExtension extends PaymentExtension
{
    use HandlesGatewayPayments;

    // https://docs.stripe.com/currencies#zero-decimal
    protected const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    // https://docs.stripe.com/currencies#three-decimal
    protected const THREE_DECIMAL_CURRENCIES = [
        'BHD', 'JOD', 'KWD', 'OMR', 'TND',
    ];

    public static function getConfig(): array
    {
        return [
            "name" => "Stripe",
            "RoutesIgnoreCsrf" => [
                "payment/StripeWebhooks",
            ],
        ];
    }

    public static function getRedirectUrl(Payment $payment, ShopProduct $shopProduct, int $totalPrice): string
    {
        $displayTotal = self::currencyHelper()->convertForDisplay($totalPrice);

        // check if the total price is valid for stripe
        if (!self::checkPriceAmount((float) $displayTotal, strtoupper($shopProduct->currency_code), 'stripe')) {
            Log::warning('Stripe getRedirectUrl rejected due to invalid price amount', [
                'payment_id' => $payment->id,
                'currency_code' => $shopProduct->currency_code,
                'display_total' => $displayTotal,
            ]);
            throw new Exception('Invalid price amount');
        }

        $stripeClient = self::getStripeClient();
        $request = $stripeClient->checkout->sessions->create([
            'metadata' => [
                'payment_id' => $payment->id,
            ],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => $shopProduct->currency_code,
                        'product_data' => [
                            'name' => $shopProduct->display,
                            'description' => $shopProduct->description,
                        ],
                        'unit_amount' => self::convertAmount($totalPrice, $shopProduct->currency_code),
                    ],
                    'quantity' => 1,
                ],
                /* Removed due to errors in the coupon discount calculation. Its not used in other paymentgateways aswell and basically nice to have but unnessecary
                [
                    'price_data' => [
                        'currency' => $shopProduct->currency_code,
                        'product_data' => [
                            'name' => __('Tax'),
                            'description' => $shopProduct->getTaxPercent() . '%',
                        ],
                        'unit_amount_decimal' => round($shopProduct->getTaxValue(), 2),
                    ],
                    'quantity' => 1,
                ],
                */
            ],

            'mode' => 'payment',
            'success_url' => route('payment.StripeSuccess', ['payment' => $payment->id]) . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('payment.Cancel'),
            'payment_intent_data' => [
                'metadata' => [
                    'payment_id' => $payment->id,
                ],
            ],
        ]);

        return $request->url;
    }

    /**
     * @param  object  $paymentIntent
     */
    public static function handleStripePaymentSucceeded(object $paymentIntent): void
    {
        $paymentId = $paymentIntent->metadata->payment_id ?? null;
        if (empty($paymentId)) {
            Log::warning('Stripe webhook payment intent missing payment_id metadata', [
                'payment_intent_id' => $paymentIntent->id ?? null,
            ]);
            return;
        }

        $payment = Payment::find($paymentId);
        if (!$payment || $payment->payment_method !== 'Stripe') {
            Log::warning('Stripe webhook payment lookup failed.', [
                'payment_id' => $paymentId,
                'payment_intent_id' => $paymentIntent->id ?? null,
            ]);
            return;
        }

        if (!self::isValidStripePaymentPayload($payment, $paymentIntent)) {
            Log::warning('Stripe webhook payload validation failed; canceling payment', [
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntent->id ?? null,
                'payment_currency' => $payment->currency_code,
                'webhook_currency' => $paymentIntent->currency ?? null,
                'amount_received' => $paymentIntent->amount_received ?? null,
                'expected_total' => $payment->total_price,
            ]);
            self::setPaymentCanceled($payment->id, $paymentIntent->id ?? null);
            return;
        }

        self::completePayment($payment->id, $paymentIntent->id ?? null);

    }

    public static function isValidStripePaymentPayload(Payment $payment, object $paymentIntent): bool
    {
        $currency = strtoupper((string) ($paymentIntent->currency ?? ''));
        $expectedCurrency = strtoupper($payment->currency_code);
        $amountReceived = (int) ($paymentIntent->amount_received ?? $paymentIntent->amount ?? 0);

        $expectedAmount = self::convertAmount((float) $payment->total_price, $payment->currency_code);

        $isValid = $currency !== ''
            && $currency === $expectedCurrency
            && $amountReceived === $expectedAmount;

        return $isValid;
    }

    /**
     * Verify a Stripe webhook signature against the configured endpoint secrets.
     *
     * @param  string  $payload
     * @param  string  $sigHeader
     * @param  array<string, string>  $endpointSecrets
     * @return \Stripe\Event|null
     */
    public static function verifyWebhookSignature(string $payload, string $sigHeader, array $endpointSecrets): ?Event
    {
        $event = null;
        $signatureErrors = [];

        try {
            foreach ($endpointSecrets as $secretName => $endpointSecret) {
                try {
                    $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
                    break;
                } catch (SignatureVerificationException $e) {
                    $signatureErrors[$secretName] = $e->getMessage();
                }
            }
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook payload could not be parsed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return null;
        }

        if ($event === null) {
            Log::warning('Stripe webhook signature verification failed', [
                'errors' => $signatureErrors,
                'secrets_checked' => array_keys($endpointSecrets),
            ]);
        }

        return $event;
    }

    /**
     * Process a verified Stripe webhook event.
     */
    public static function processWebhookEvent(Event $event): void
    {
        switch ($event->type) {
            case 'payment_intent.processing':
                $paymentIntent = $event->data->object;
                $paymentId = $paymentIntent->metadata->payment_id ?? null;
                if (!empty($paymentId) && Payment::whereKey($paymentId)->where('payment_method', 'Stripe')->exists()) {
                    self::setPaymentProcessing($paymentId, $paymentIntent->id ?? null);
                }
                break;
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object; // contains a \Stripe\PaymentIntent
                self::handleStripePaymentSucceeded($paymentIntent);
                break;
            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                $paymentId = $paymentIntent->metadata->payment_id ?? null;
                if (!empty($paymentId) && Payment::whereKey($paymentId)->where('payment_method', 'Stripe')->exists()) {
                    Log::warning('Stripe webhook setting payment canceled', [
                        'payment_id' => $paymentId,
                        'payment_intent_id' => $paymentIntent->id ?? null,
                    ]);
                    self::setPaymentCanceled($paymentId, $paymentIntent->id ?? null);
                }
                break;
            default:
                break;
        }
    }

    public static function supportsRecheck(): bool
    {
        return true;
    }

    // Recheck the payment status
    public static function recheckPayment(Payment $payment): void
    {
        if (empty($payment->payment_id)) {
            return;
        }

        $stripeClient = self::getStripeClient();

        try {
            // It could be a checkout session ID or a payment intent ID
            if (str_starts_with($payment->payment_id, 'cs_')) {
                $session = $stripeClient->checkout->sessions->retrieve($payment->payment_id);
                $paymentIntentId = $session->payment_intent;
                if ($paymentIntentId) {
                    $paymentIntent = $stripeClient->paymentIntents->retrieve($paymentIntentId);
                } else {
                    if ($session->status === 'complete' || $session->payment_status === 'paid') {
                        self::completePayment($payment->id, null);
                    }
                    return;
                }
            } else {
                $paymentIntent = $stripeClient->paymentIntents->retrieve($payment->payment_id);
            }

            if ($paymentIntent->status === 'succeeded') {
                self::completePayment($payment->id, $paymentIntent->id);
            } elseif (in_array($paymentIntent->status, ['canceled', 'requires_payment_method'], true)) {
                self::setPaymentCanceled($payment->id, $paymentIntent->id);
            }
        } catch (Exception $e) {
            Log::error('Stripe recheck failed', [
                'payment_id' => $payment->id,
                'gateway_id' => $payment->payment_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return \Stripe\StripeClient
     */
    public static function getStripeClient()
    {
        return new StripeClient(self::getStripeSecret());
    }

    /**
     * @return string
     */
    public static function getStripeSecret()
    {
        $settings = new StripeSettings();

        return config('app.env') == 'local'
            ? $settings->test_secret_key
            : $settings->secret_key;
    }

    /**
     * @return string|null
     */
    public static function getStripeEndpointSecret()
    {
        $endpointSecrets = self::getStripeEndpointSecrets();
        $firstSecret = reset($endpointSecrets);

        return $firstSecret === false ? null : $firstSecret;
    }

    /**
     * @return array<string, string>
     */
    public static function getStripeEndpointSecrets(): array
    {
        $settings = new StripeSettings();
        $isLocal = config('app.env') == 'local';

        // Prefer explicit webhook signing secret fields, then legacy endpoint secret fields.
        $orderedSecrets = $isLocal
            ? [
                'test_webhook_signing_secret' => $settings->test_webhook_signing_secret,
                'test_publishable_key' => $settings->test_publishable_key,
                'webhook_signing_secret' => $settings->webhook_signing_secret,
                'publishable_key' => $settings->publishable_key,
            ]
            : [
                'webhook_signing_secret' => $settings->webhook_signing_secret,
                'publishable_key' => $settings->publishable_key,
                'test_webhook_signing_secret' => $settings->test_webhook_signing_secret,
                'test_publishable_key' => $settings->test_publishable_key,
            ];

        $secrets = [];
        $seenValues = [];
        foreach ($orderedSecrets as $name => $secret) {
            if (!is_string($secret)) {
                continue;
            }

            $normalized = trim($secret);
            if ($normalized === '' || isset($seenValues[$normalized])) {
                continue;
            }

            $seenValues[$normalized] = true;
            $secrets[$name] = $normalized;
        }

        return $secrets;
    }
    /**
     * @param  $amount
     * @param  $currencyCode
     * @param  $payment_method
     * @return bool
     * @description check if the amount is higher than the minimum amount for the stripe gateway
     */
    public static function checkPriceAmount(float $amount,  string $currencyCode, string $payment_method)
    {
        $minimums = [
            "USD" => [
                "paypal" => 0,
                "stripe" => 0.5
            ],
            "AED" => [
                "paypal" => 0,
                "stripe" => 2
            ],
            "AUD" => [
                "paypal" => 0,
                "stripe" => 0.5
            ],
            "BGN" => [
                "paypal" => 0,
                "stripe" => 1
            ],
            "BRL" => [
                "paypal" => 0,
                "stripe" => 0.5
            ],
            "CAD" => [
                "paypal" => 0,
                "stripe" => 0.5
            ],
            "CHF" => [
                "paypal" => 0,
                "stripe" => 0.5
            ],
            "CZK" => [
                "paypal" => 0,
                "stripe" => 15
            ],
            "DKK" => [
                "paypal" => 0,
                "stripe" => 2.5
            ],
            "EUR" => [
                "paypal" => 0,
                "stripe" => 0.5
            ],
            "GBP" => [
                "paypal" => 0,
                "stripe" => 0.3
            ],
            "HKD" => [
                "paypal" => 0,
                "stripe" => 4
            ],
            "HRK" => [
                "paypal" => 0,
                "stripe" => 0.5
            ],
            "HUF" => [
                "paypal" => 0,
                "stripe" => 175
            ],
            "INR" => [
                "paypal" => 0,
                "stripe" => 0.5
            ],
            "JPY" => [
                "paypal" => 0,
                "stripe" => 0.5
            ],
            "MXN" => [
                "paypal" => 0,
                "stripe" => 10
            ],
            "MYR" => [
                "paypal" => 0,
                "stripe" => 2
            ],
            "NOK" => [
                "paypal" => 0,
                "stripe" => 3
            ],
            "NZD" => [
                "paypal" => 0,
                "stripe" => 0.5
            ],
            "PLN" => [
                "paypal" => 0,
                "stripe" => 2
            ],
            "RON" => [
                "paypal" => 0,
                "stripe" => 2
            ],
            "SEK" => [
                "paypal" => 0,
                "stripe" => 3
            ],
            "SGD" => [
                "paypal" => 0,
                "stripe" => 0.5
            ],
            "THB" => [
                "paypal" => 0,
                "stripe" => 10
            ]
        ];

        if (!isset($minimums[$currencyCode], $minimums[$currencyCode][$payment_method])) {
            return false;
        }

        return $amount >= $minimums[$currencyCode][$payment_method];
    }

    public static function convertAmount(float $amount, string $currency): int
    {
        $displayAmount = self::currencyHelper()->convertForDisplay($amount);

        if (in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (int) round($displayAmount);
        }

        if (in_array(strtoupper($currency), self::THREE_DECIMAL_CURRENCIES, true)) {
            return (int) round($displayAmount * 1000);
        }

        return (int) round($displayAmount * 100);
    }
}
