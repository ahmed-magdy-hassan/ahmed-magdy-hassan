<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('iqama_expiry_date')->nullable()->after('gosi_number');
            $table->date('passport_expiry_date')->nullable()->after('iqama_expiry_date');
            $table->date('work_visa_expiry_date')->nullable()->after('passport_expiry_date');
            $table->date('work_permit_expiry_date')->nullable()->after('work_visa_expiry_date');
            $table->date('contract_end_date')->nullable()->after('work_permit_expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'iqama_expiry_date',
                'passport_expiry_date',
                'work_visa_expiry_date',
                'work_permit_expiry_date',
                'contract_end_date',
            ]);
        });
    }
};
