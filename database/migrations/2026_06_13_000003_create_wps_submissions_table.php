<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRIST-186: WPS payroll-submission tracking table.
 *
 * Each row represents one attempt to submit a monthly payroll to Mudad.
 * The status column mirrors WpsSubmissionStatus enum values.
 * sif_content stores the raw SIF file so submissions can be re-sent on failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wps_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('employer_id');
            $table->string('payroll_month', 7);          // YYYY-MM
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('record_count');
            $table->decimal('total_net_salary', 14, 2);
            $table->longText('sif_content');
            $table->string('mudad_reference_id')->nullable();
            $table->string('bank_reference_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['employer_id', 'payroll_month']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wps_submissions');
    }
};
