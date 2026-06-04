<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('eosb_amount', 12, 2)->nullable()->after('basic_salary');
            $table->string('eosb_termination_reason', 30)->nullable()->after('eosb_amount');
            $table->timestamp('eosb_calculated_at')->nullable()->after('eosb_termination_reason');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['eosb_amount', 'eosb_termination_reason', 'eosb_calculated_at']);
        });
    }
};
