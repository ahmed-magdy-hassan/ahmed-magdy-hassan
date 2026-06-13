<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Saudi;

use App\Contracts\MudadApiClientInterface;
use App\Enums\Payroll\WpsSubmissionStatus;
use App\Services\Payroll\Saudi\MudadSubmissionService;
use App\Services\Payroll\Saudi\WpsSifGenerator;
use App\ValueObjects\Saudi\WpsSifRecord;
use App\ValueObjects\Saudi\WpsSubmission;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MudadSubmissionServiceTest extends TestCase
{
    private MudadApiClientInterface $mockApi;
    private MudadSubmissionService  $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockApi = $this->createMock(MudadApiClientInterface::class);
        $this->service = new MudadSubmissionService(new WpsSifGenerator(), $this->mockApi);
    }

    private function validRecord(
        string $id   = '1234567890',
        string $iban = 'SA4420000001234567891234',
        float  $net  = 10_000.0,
    ): WpsSifRecord {
        return new WpsSifRecord(
            employeeId: $id,
            iban: $iban,
            basicSalary: $net,
            housingAllowance: 0.0,
            otherAllowances: 0.0,
            deductions: 0.0,
            netSalary: $net,
            nationalityCode: 'SAU',
        );
    }

    /** Current month — always within the 30-day window */
    private function currentMonth(): string
    {
        return Carbon::now()->format('Y-m');
    }

    // -----------------------------------------------------------------------
    // submit()
    // -----------------------------------------------------------------------

    /** @test */
    public function successful_submission_returns_submitted_status(): void
    {
        $this->mockApi
            ->expects($this->once())
            ->method('submit')
            ->willReturn(['reference_id' => 'MUD-2026-001', 'status' => 'submitted', 'message' => null]);

        $result = $this->service->submit(
            employerId: 'EMP-001',
            employerBankId: 'RJHI',
            payrollMonth: $this->currentMonth(),
            records: [$this->validRecord()],
        );

        $this->assertSame(WpsSubmissionStatus::Submitted, $result->status);
        $this->assertSame('MUD-2026-001', $result->mudadReferenceId);
        $this->assertTrue($result->isSubmitted());
    }

    /** @test */
    public function submission_record_count_and_total_are_correct(): void
    {
        $this->mockApi->method('submit')->willReturn(['reference_id' => 'X', 'status' => 'submitted', 'message' => null]);

        $records = [
            $this->validRecord('1234567890', 'SA4420000001234567891234', 8_000.0),
            $this->validRecord('0987654321', 'SA4420000009876543210987', 12_000.0),
        ];

        $result = $this->service->submit('EMP-001', 'RJHI', $this->currentMonth(), $records);

        $this->assertSame(2, $result->recordCount);
        $this->assertSame(20_000.0, $result->totalNetSalary);
    }

    /** @test */
    public function throws_on_invalid_iban_before_api_call(): void
    {
        $this->mockApi->expects($this->never())->method('submit');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('WPS validation failed');

        $bad = new WpsSifRecord(
            employeeId: '1234567890',
            iban: 'INVALID',
            basicSalary: 5_000.0,
            housingAllowance: 0.0,
            otherAllowances: 0.0,
            deductions: 0.0,
            netSalary: 5_000.0,
            nationalityCode: 'SAU',
        );

        $this->service->submit('EMP-001', 'RJHI', $this->currentMonth(), [$bad]);
    }

    /** @test */
    public function throws_when_submission_window_expired(): void
    {
        $this->mockApi->expects($this->never())->method('submit');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('submission window');

        // Provide an explicit deadline in the past
        $this->service->submit(
            employerId: 'EMP-001',
            employerBankId: 'RJHI',
            payrollMonth: Carbon::now()->subMonths(2)->format('Y-m'),
            records: [$this->validRecord()],
            windowDeadline: Carbon::now()->subDay(),  // already expired
        );
    }

    // -----------------------------------------------------------------------
    // pollStatus()
    // -----------------------------------------------------------------------

    /** @test */
    public function poll_status_returns_same_instance_when_already_accepted(): void
    {
        $submission = new WpsSubmission(
            id: 'uuid', employerId: 'EMP', payrollMonth: '2026-06',
            status: WpsSubmissionStatus::Accepted,
            recordCount: 1, totalNetSalary: 10_000.0,
            mudadReferenceId: 'MUD-001', bankReferenceId: null, errorMessage: null,
            createdAt: Carbon::now(), submittedAt: Carbon::now(), completedAt: Carbon::now(),
        );

        $this->mockApi->expects($this->never())->method('getStatus');

        $this->assertSame($submission, $this->service->pollStatus($submission));
    }

    /** @test */
    public function poll_status_returns_same_when_no_reference_id(): void
    {
        $submission = new WpsSubmission(
            id: 'uuid', employerId: 'EMP', payrollMonth: '2026-06',
            status: WpsSubmissionStatus::Pending,
            recordCount: 1, totalNetSalary: 10_000.0,
            mudadReferenceId: null, bankReferenceId: null, errorMessage: null,
            createdAt: Carbon::now(), submittedAt: null, completedAt: null,
        );

        $this->mockApi->expects($this->never())->method('getStatus');

        $this->assertSame($submission, $this->service->pollStatus($submission));
    }

    /** @test */
    public function poll_status_calls_api_and_transitions_to_accepted(): void
    {
        $this->mockApi
            ->expects($this->once())
            ->method('getStatus')
            ->with('MUD-001')
            ->willReturn(['reference_id' => 'MUD-001', 'status' => 'accepted', 'message' => null, 'bank_reference_id' => null]);

        $submission = new WpsSubmission(
            id: 'uuid', employerId: 'EMP', payrollMonth: '2026-06',
            status: WpsSubmissionStatus::Submitted,
            recordCount: 1, totalNetSalary: 10_000.0,
            mudadReferenceId: 'MUD-001', bankReferenceId: null, errorMessage: null,
            createdAt: Carbon::now(), submittedAt: Carbon::now(), completedAt: null,
        );

        $result = $this->service->pollStatus($submission);

        $this->assertSame(WpsSubmissionStatus::Accepted, $result->status);
        $this->assertTrue($result->isFinal());
    }

    /** @test */
    public function poll_status_captures_error_message_on_rejection(): void
    {
        $this->mockApi->method('getStatus')
            ->willReturn(['reference_id' => 'MUD-002', 'status' => 'rejected', 'message' => 'Invalid IBAN on record 3', 'bank_reference_id' => null]);

        $submission = new WpsSubmission(
            id: 'uuid', employerId: 'EMP', payrollMonth: '2026-06',
            status: WpsSubmissionStatus::Processing,
            recordCount: 3, totalNetSalary: 30_000.0,
            mudadReferenceId: 'MUD-002', bankReferenceId: null, errorMessage: null,
            createdAt: Carbon::now(), submittedAt: Carbon::now(), completedAt: null,
        );

        $result = $this->service->pollStatus($submission);

        $this->assertTrue($result->isRejected());
        $this->assertSame('Invalid IBAN on record 3', $result->errorMessage);
    }

    // -----------------------------------------------------------------------
    // cancel()
    // -----------------------------------------------------------------------

    /** @test */
    public function cancel_transitions_to_cancelled_and_calls_api(): void
    {
        $this->mockApi->expects($this->once())->method('cancel')->with('MUD-001')->willReturn(true);

        $submission = new WpsSubmission(
            id: 'uuid', employerId: 'EMP', payrollMonth: '2026-06',
            status: WpsSubmissionStatus::Submitted,
            recordCount: 1, totalNetSalary: 10_000.0,
            mudadReferenceId: 'MUD-001', bankReferenceId: null, errorMessage: null,
            createdAt: Carbon::now(), submittedAt: Carbon::now(), completedAt: null,
        );

        $result = $this->service->cancel($submission);

        $this->assertSame(WpsSubmissionStatus::Cancelled, $result->status);
        $this->assertTrue($result->isFinal());
    }

    /** @test */
    public function cancel_throws_when_already_accepted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel');

        $accepted = new WpsSubmission(
            id: 'uuid', employerId: 'EMP', payrollMonth: '2026-06',
            status: WpsSubmissionStatus::Accepted,
            recordCount: 1, totalNetSalary: 10_000.0,
            mudadReferenceId: 'MUD-001', bankReferenceId: null, errorMessage: null,
            createdAt: Carbon::now(), submittedAt: Carbon::now(), completedAt: Carbon::now(),
        );

        $this->service->cancel($accepted);
    }
}
