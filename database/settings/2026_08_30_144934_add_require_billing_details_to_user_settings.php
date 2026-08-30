<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('user.require_billing_details_on_purchase', false);
    }

    public function down(): void
    {
        $this->migrator->delete('user.require_billing_details_on_purchase');
    }
};