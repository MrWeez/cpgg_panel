<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('invoice.company_country', null);
    }

    public function down(): void
    {
        $this->migrator->delete('invoice.company_country');
    }
};