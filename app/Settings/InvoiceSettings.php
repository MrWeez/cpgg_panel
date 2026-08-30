<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;
use Symfony\Component\Intl\Countries;

class InvoiceSettings extends Settings
{
    public bool $enabled = false;
    public ?string $company_address = null;
    public ?string $company_city = null;
    public ?string $company_state = null;
    public ?string $company_postal_code = null;
    public ?string $company_mail = null;
    public ?string $company_name = null;
    public ?string $company_phone = null;
    public ?string $company_vat = null;
    public ?string $company_website = null;
    public ?string $company_country = null;
    public ?string $prefix = 'INV-';
    public ?string $additional_notes = null;


    public static function group(): string
    {
        return 'invoice';
    }

    /**
     * Summary of validations array
     * @return array<string, string>
     */
    public static function getValidations()
    {
        return [
            'enabled' => 'nullable|string',
            'company_address' => 'nullable|string',
            'company_city' => 'nullable|string',
            'company_state' => 'nullable|string',
            'company_postal_code' => 'nullable|string',
            'company_country' => 'nullable|string|size:2',
            'company_mail' => 'nullable|string',
            'company_name' => 'nullable|string',
            'company_phone' => 'nullable|string',
            'company_vat' => 'nullable|string',
            'company_website' => 'nullable|string',
            'prefix' => 'nullable|string',
            'additional_notes' => 'nullable|string',
        ];
    }

    /**
     * Summary of optionTypes
     * Only used for the settings page
     * @return array<array<'type'|'label'|'description'|'options', string|bool|float|int|array<string, string>>>
     */
    public static function getOptionInputData()
    {
        return [
            'category_icon' => 'fas fa-file-invoice-dollar',
            'position' => 9,
            'category_description' => 'Enable invoices and fill in the company details shown on them',
            'sections' => [
                'company' => [
                    'label' => 'Company Details',
                    'description' => 'The company details shown on your invoices',
                ],
            ],
            'enabled' => [
                'label' => 'Enabled',
                'type' => 'boolean',
                'description' => 'Enable or disable invoices',
            ],
            'prefix' => [
                'label' => 'Prefix',
                'type' => 'string',
                'description' => 'The prefix of your invoices',
            ],
            'additional_notes' => [
                'label' => 'Custom additional Notes',
                'type' => 'textarea',
                'description' => 'Additional custom notes you want on your Invoices to appear',
            ],
            'company_name' => [
                'label' => 'Company Name',
                'type' => 'string',
                'description' => 'The name of your company',
                'section' => 'company',
            ],
            'company_address' => [
                'label' => 'Company Address',
                'type' => 'string',
                'description' => 'The street, building and office of your company address',
                'section' => 'company',
            ],
            'company_city' => [
                'label' => 'Company City',
                'type' => 'string',
                'description' => 'The city of your company',
                'section' => 'company',
            ],
            'company_state' => [
                'label' => 'Company State/Province',
                'type' => 'string',
                'description' => 'The state or province of your company (optional)',
                'section' => 'company',
            ],
            'company_postal_code' => [
                'label' => 'Company Postal Code',
                'type' => 'string',
                'description' => 'The postal code of your company',
                'section' => 'company',
            ],
            'company_country' => [
                'label' => 'Company Country',
                'type' => 'select',
                'description' => 'The country of your company. Shown in the company address on your invoices',
                'options' => Countries::getNames('en'),
                'section' => 'company',
            ],
            'company_phone' => [
                'label' => 'Company Phone',
                'type' => 'string',
                'description' => 'The phone of your company',
                'section' => 'company',
            ],
            'company_mail' => [
                'label' => 'Company Email',
                'type' => 'string',
                'description' => 'The email of your company',
                'section' => 'company',
            ],
            'company_website' => [
                'label' => 'Company Website',
                'type' => 'string',
                'description' => 'The website of your company',
                'section' => 'company',
            ],
            'company_vat' => [
                'label' => 'Company VAT ID',
                'type' => 'string',
                'description' => 'The VAT ID of your company',
                'section' => 'company',
            ],
        ];
    }
}
