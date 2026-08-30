<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('invoice.company_city', null);
        $this->migrator->add('invoice.company_state', null);
        $this->migrator->add('invoice.company_postal_code', null);
    }

    public function down(): void
    {
        $this->migrator->delete('invoice.company_city');
        $this->migrator->delete('invoice.company_state');
        $this->migrator->delete('invoice.company_postal_code');
    }
};
