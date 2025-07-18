<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class InvoiceSettings extends Settings
{
    public bool $enabled = false;
    public ?string $company_address = null;
    public ?string $company_mail = null;
    public ?string $company_name = null;
    public ?string $company_phone = null;
    public ?string $company_vat = null;
    public ?string $company_website = null;
    public ?string $additional_notes = null;

    public ?string $prefix = 'INV-';

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
            'company_address' => 'nullable|string',
            'company_mail' => 'nullable|string',
            'company_name' => 'nullable|string',
            'company_phone' => 'nullable|string',
            'company_vat' => 'nullable|string',
            'company_website' => 'nullable|string',
            'additional_notes' => 'nullable|string',
            'enabled' => 'nullable|string',
            'prefix' => 'nullable|string',
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
            'company_address' => [
                'label' => 'Company Address',
                'type' => 'string',
                'description' => 'The address of your company.',
            ],
            'company_mail' => [
                'label' => 'Company Mail',
                'type' => 'string',
                'description' => 'The mail of your company.',
            ],
            'company_name' => [
                'label' => 'Company Name',
                'type' => 'string',
                'description' => 'The name of your company.',
            ],
            'company_phone' => [
                'label' => 'Company Phone',
                'type' => 'string',
                'description' => 'The phone of your company.',
            ],
            'company_vat' => [
                'label' => 'Company VAT ID',
                'type' => 'string',
                'description' => 'The VAT ID of your company.',
            ],
            'company_website' => [
                'label' => 'Company Website',
                'type' => 'string',
                'description' => 'The website of your company.',
            ],
            'enabled' => [
                'label' => 'Enabled',
                'type' => 'boolean',
                'description' => 'Enable or disable invoices.',
            ],
            'prefix' => [
                'label' => 'Prefix',
                'type' => 'string',
                'description' => 'The prefix of your invoices.',
            ],
            'additional_notes' => [
                'label' => 'Custom additional Notes',
                'type' => 'textarea',
                'description' => 'Additional custom notes you want on your Invoices to appear.',
            ],
        ];
    }
}
