<?php

namespace App\Traits;

use App\Helpers\CurrencyHelper;
use App\Models\Payment;
use App\Models\ShopProduct;
use App\Models\Invoice;
use App\Notifications\InvoiceNotification;
use App\Settings\InvoiceSettings;
use Illuminate\Support\Facades\Storage;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Invoice as DailyInvoice;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Currencies;

trait Invoiceable
{
    public function createInvoice(Payment $payment, ShopProduct $shopProduct, InvoiceSettings $invoice_settings)
    {
        $user = $payment->user;
        //create invoice
        $lastInvoiceID = Invoice::where("invoice_name", "like", "%" . now()->format('mY') . "%")->count("id");
        $newInvoiceID = $lastInvoiceID + 1;
        $logoPath = storage_path('app/public/logo.png');

        $seller = new Party([
            'name' => $invoice_settings->company_name,
            'phone' => $invoice_settings->company_phone,
            'address' => $invoice_settings->company_address,
            'vat' => $invoice_settings->company_vat,
            'custom_fields' => [
                'E-Mail' => $invoice_settings->company_mail,
                "Web" => $invoice_settings->company_website
            ],
        ]);

        $countryCode = strtoupper((string) ($user->country ?? ''));
        $countryName = null;
        if (!blank($countryCode)) {
            $countryName = Countries::exists($countryCode)
                ? Countries::getName($countryCode, 'en')
                : $countryCode;
        }

        // Expose the billing fields separately so the invoice template can
        // render the address according to the regional (EU/US/LatAm) format.
        $customerData = [
            'name' => $user->getBillingName(),
            'street' => $user->address ?? '',
            'city' => $user->city ?? '',
            'state' => $user->state ?? '',
            'code' => $user->postal_code ?? '',
            'country_code' => $countryCode,
            'country' => $countryName,
            'phone' => $user->phone ?? '',
            'vat' => $user->vat_number ?? '',
            'custom_fields' => [
                'E-Mail' => $user->email,
                'Client ID' => $user->id,
            ],
        ];

        $postalAndCity = trim(($user->postal_code ?? '') . ' ' . ($user->city ?? ''));
        $addressParts = [];
        foreach ([$user->address, $postalAndCity !== '' ? $postalAndCity : null, $user->state, $countryName] as $part) {
            if (blank($part)) {
                continue;
            }

            $last = end($addressParts);
            if ($last !== false && mb_strtolower(trim((string) $last)) === mb_strtolower(trim((string) $part))) {
                continue;
            }

            $addressParts[] = trim((string) $part);
        }

        if (!empty($addressParts)) {
            $customerData['address'] = implode(', ', $addressParts);
        }

        $customer = new Buyer($customerData);

        // EU reverse charge (Art. 196 Directive 2006/112/EC): cross-border B2B
        // supply between EU businesses. VAT is not charged; the customer is
        // liable to account for it instead. Requires both parties to be EU
        // businesses in different member states.
        $sellerCountry = strtoupper((string) $invoice_settings->company_country);
        $isReverseCharge = !blank($user->vat_number)
            && $this->isEUCountry($countryCode)
            && $this->isEUCountry($sellerCountry)
            && $sellerCountry !== $countryCode;

        // The date of supply (Art. 226(6)) for instantly delivered digital
        // goods equals the moment the payment was confirmed.
        $supplyDate = $payment->updated_at ?? $payment->created_at;

        // Everything below is stored in thousandths, so /1000 gives currency units.
        $originalPrice = (int) $payment->price;
        $fee = (int) $payment->fee;
        $taxValue = (int) $payment->tax_value;
        $totalPrice = (int) $payment->total_price;

        // The amount actually paid for the product (before tax and fee).
        $discountedBase = $totalPrice - $fee - $taxValue;
        $discountAmount = max(0, $originalPrice - $discountedBase);

        $item = (new InvoiceItem())
            ->title($shopProduct->description)
            ->pricePerUnit(($originalPrice / 1000));

        $notes = [
            __("Payment method") . ": " . $payment->payment_method,
        ];

        if ($payment->coupon_code) {
            $notes[] = __("Coupon") . ": " . $payment->coupon_code;
        }

        if ($fee > 0) {
            $notes[] = __("Payment fee") . ": " . resolve(CurrencyHelper::class)->formatToCurrency($fee, $payment->currency_code);
        }

        if ($invoice_settings->additional_notes) {
            $notes[] = $invoice_settings->additional_notes;
        }

        if ($isReverseCharge) {
            $notes[] = __("Reverse charge") . ": " . __("VAT to be accounted for by the customer");
        }

        $notes = implode("<br>", $notes);

        $region = $this->getRegionalFormat($countryCode, $payment->currency_code);

        $invoice = DailyInvoice::make()
            ->template('ctrlpanel')
            ->name(__("Invoice"))
            ->buyer($customer)
            ->seller($seller)
            ->addItem($item)
            ->status(__($payment->status->value))
            ->series(now()->format('mY'))
            ->delimiter("-")
            ->sequence($newInvoiceID)
            ->serialNumberFormat($invoice_settings->prefix . '{SERIES}{DELIMITER}{SEQUENCE}')
            ->currencyCode(strtoupper($payment->currency_code))
            ->currencySymbol(Currencies::getSymbol(strtoupper($payment->currency_code)))
            ->dateFormat($region['date_format'])
            ->currencyDecimals(2)
            ->currencyDecimalPoint($region['decimal_point'])
            ->currencyThousandsSeparator($region['thousands_separator'])
            ->currencyFormat($region['currency_format'])
            ->setCustomData([
                'supply_date' => $supplyDate->format($region['date_format']),
                'reverse_charge' => $isReverseCharge,
            ])
            ->notes("<br/>" . $notes);

        if (!$isReverseCharge) {
            $invoice->taxRate(floatval($payment->tax_percent));
        }

        if ($discountAmount > 0) {
            $invoice->totalDiscount($discountAmount / 1000);
        }

        if ($fee > 0) {
            $invoice->shipping($fee / 1000);
        }

        if (file_exists($logoPath)) {
            $invoice->logo($logoPath);
        }

        //Save the invoice in "storage\app\invoice\USER_ID\YEAR"
        $invoice->filename = $invoice->getSerialNumber() . '.pdf';
        $invoice->render();
        Storage::disk("local")->put("invoice/" . $user->id . "/" . now()->format('Y') . "/" . $invoice->filename, $invoice->output);

        Invoice::create([
            'invoice_user' => $user->id,
            'invoice_name' => $invoice->getSerialNumber(),
            'payment_id' => $payment->payment_id,
        ]);

        //Send Invoice per Mail
        $user->notify(new InvoiceNotification($invoice->filename, $user, $payment));
    }

    /**
     * Whether the given country code is an EU member state.
     *
     * @param  string  $countryCode
     * @return bool
     */
    private function isEUCountry(?string $countryCode): bool
    {
        $euCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE',
            'GR', 'EL', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL',
            'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        ];

        return in_array(strtoupper((string) $countryCode), $euCountries, true);
    }

    /**
     * Resolve the date/currency formatting for the buyer's region.
     *
     * Follows the conventions used across Europe, the US/Canada and
     * South America:
     *  - EU/LatAm: d/m/Y dates and 1.234,56 style amounts.
     *  - US/CA:    m/d/Y dates and 1,234.56 style amounts.
     *
     * @param  string  $countryCode
     * @param  string  $currencyCode
     * @return array{date_format: string, decimal_point: string, thousands_separator: string, currency_format: string}
     */
    private function getRegionalFormat(string $countryCode, string $currencyCode): array
    {
        $code = strtoupper($countryCode);
        $currency = strtoupper($currencyCode);

        $usLike = ['US', 'CA'];
        $symbolFirst = ['USD', 'CAD', 'AUD', 'NZD', 'GBP', 'BRL', 'ARS', 'MXN', 'CLP', 'COP'];

        if (in_array($code, $usLike, true)) {
            return [
                'date_format' => 'm/d/Y',
                'decimal_point' => '.',
                'thousands_separator' => ',',
                // $1,234.56
                'currency_format' => '{SYMBOL}{VALUE}',
            ];
        }

        $format = in_array($currency, $symbolFirst, true)
            // R$ 1.234,56
            ? '{SYMBOL}{VALUE}'
            // 1.234,56 €
            : '{VALUE} {SYMBOL}';

        return [
            'date_format' => 'd/m/Y',
            'decimal_point' => ',',
            'thousands_separator' => '.',
            'currency_format' => $format,
        ];
    }
}
