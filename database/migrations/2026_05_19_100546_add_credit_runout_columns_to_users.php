<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('credit_runout_at')->nullable()->after('credits');
            $table->timestamp('credit_runout_updated_at')->nullable()->after('credit_runout_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('credit_runout_at');
            $table->dropColumn('credit_runout_updated_at');
        });
    }
};
