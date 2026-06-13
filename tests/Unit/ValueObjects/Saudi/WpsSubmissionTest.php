<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\Saudi;

use App\Enums\Payroll\WpsSubmissionStatus;
use App\ValueObjects\Saudi\WpsSubmission;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class WpsSubmissionTest extends TestCase
{
    private function pending(): WpsSubmission
    {
        return new WpsSubmission(
            id: 'test-uuid-001',
            employerId: 'EMP-100',
            payrollMonth: '2026-06',
            status: WpsSubmissionStatus::Pending,
            recordCount: 5,
            totalNetSalary: 50_000.0,
            mudadReferenceId: null,
            bankReferenceId: null,
            errorMessage: null,
            createdAt: Carbon::parse('2026-06-10 10:00:00'),
            submittedAt: null,
            completedAt: null,
        );
    }

    /** @test */
    public function pending_status_helpers_return_correct_values(): void
    {
        $s = $this->pending();
        $this->assertTrue($s->isPending());
        $this->assertFalse($s->isSubmitted());
        $this->assertFalse($s->isAccepted());
        $this->assertFalse($s->isFinal());
    }

    /** @test */
    public function with_status_submitted_sets_submitted_at(): void
    {
        $ts      = Carbon::parse('2026-06-10 11:00:00');
        $updated = $this->pending()->withStatus(WpsSubmissionStatus::Submitted, 'MUD-001', null, $ts);

        $this->assertSame(WpsSubmissionStatus::Submitted, $updated->status);
        $this->assertTrue($updated->submittedAt->equalTo($ts));
        $this->assertNull($updated->completedAt);
    }

    /** @test */
    public function with_status_accepted_sets_completed_at(): void
    {
        $ts       = Carbon::parse('2026-06-11 09:00:00');
        $accepted = $this->pending()
            ->withStatus(WpsSubmissionStatus::Submitted, 'MUD-001')
            ->withStatus(WpsSubmissionStatus::Accepted, null, null, $ts);

        $this->assertTrue($accepted->isAccepted());
        $this->assertTrue($accepted->isFinal());
        $this->assertTrue($accepted->completedAt->equalTo($ts));
    }

    /** @test */
    public function with_status_preserves_unrelated_fields(): void
    {
        $updated = $this->pending()->withStatus(WpsSubmissionStatus::Submitted, 'MUD-REF-99');

        $this->assertSame('test-uuid-001', $updated->id);
        $this->assertSame('EMP-100', $updated->employerId);
        $this->assertSame('2026-06', $updated->payrollMonth);
        $this->assertSame(5, $updated->recordCount);
        $this->assertSame(50_000.0, $updated->totalNetSalary);
        $this->assertSame('MUD-REF-99', $updated->mudadReferenceId);
    }

    /** @test */
    public function rejected_status_is_final(): void
    {
        $rejected = $this->pending()->withStatus(WpsSubmissionStatus::Rejected, null, 'Duplicate submission');

        $this->assertTrue($rejected->isRejected());
        $this->assertTrue($rejected->isFinal());
        $this->assertSame('Duplicate submission', $rejected->errorMessage);
    }

    /** @test */
    public function cancelled_status_is_final(): void
    {
        $cancelled = $this->pending()->withStatus(WpsSubmissionStatus::Cancelled);
        $this->assertTrue($cancelled->isFinal());
    }

    /** @test */
    public function to_array_contains_all_top_level_keys(): void
    {
        $arr = $this->pending()->toArray();

        foreach (['id', 'employer_id', 'payroll_month', 'status', 'status_label',
                  'record_count', 'total_net_salary', 'mudad_reference_id',
                  'bank_reference_id', 'error_message', 'created_at',
                  'submitted_at', 'completed_at'] as $key) {
            $this->assertArrayHasKey($key, $arr, "Missing key: {$key}");
        }
    }

    /** @test */
    public function to_array_status_label_is_non_empty_string(): void
    {
        $arr = $this->pending()->toArray();
        $this->assertIsString($arr['status_label']);
        $this->assertNotEmpty($arr['status_label']);
    }
}
