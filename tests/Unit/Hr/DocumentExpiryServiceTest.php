<?php

declare(strict_types=1);

namespace Tests\Unit\Hr;

use App\Enums\Hr\DocumentType;
use App\Models\Employee;
use App\Services\Hr\DocumentExpiryService;
use Carbon\Carbon;
use Tests\TestCase;

final class DocumentExpiryServiceTest extends TestCase
{
    private DocumentExpiryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentExpiryService();
        Carbon::setTestNow('2026-06-04');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeEmployee(array $attrs = []): Employee
    {
        return Employee::factory()->make(array_merge([
            'iqama_expiry_date'       => null,
            'passport_expiry_date'    => null,
            'work_visa_expiry_date'   => null,
            'work_permit_expiry_date' => null,
            'contract_end_date'       => null,
        ], $attrs));
    }

    /** @test */
    public function all_null_expiry_fields_return_not_set_status(): void
    {
        $statuses = $this->service->getEmployeeDocumentStatus($this->makeEmployee());

        foreach ($statuses as $s) {
            $this->assertEquals('not_set', $s['status']);
            $this->assertNull($s['expiry_date']);
            $this->assertNull($s['days_remaining']);
        }
    }

    /** @test */
    public function lapsed_document_has_lapsed_status_and_negative_days(): void
    {
        $employee = $this->makeEmployee(['iqama_expiry_date' => '2026-05-01']);
        $statuses = collect($this->service->getEmployeeDocumentStatus($employee))->keyBy('document_type');

        $this->assertEquals('lapsed', $statuses['iqama']['status']);
        $this->assertLessThan(0, $statuses['iqama']['days_remaining']);
    }

    /** @test */
    public function document_expiring_in_5_days_is_critical(): void
    {
        $employee = $this->makeEmployee([
            'passport_expiry_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);
        $statuses = collect($this->service->getEmployeeDocumentStatus($employee))->keyBy('document_type');

        $this->assertEquals('critical', $statuses['passport']['status']);
        $this->assertEquals(5, $statuses['passport']['days_remaining']);
    }

    /** @test */
    public function document_expiring_in_20_days_is_warning(): void
    {
        $employee = $this->makeEmployee([
            'work_visa_expiry_date' => Carbon::today()->addDays(20)->toDateString(),
        ]);
        $statuses = collect($this->service->getEmployeeDocumentStatus($employee))->keyBy('document_type');

        $this->assertEquals('warning', $statuses['work_visa']['status']);
    }

    /** @test */
    public function document_expiring_in_60_days_is_upcoming(): void
    {
        $employee = $this->makeEmployee([
            'contract_end_date' => Carbon::today()->addDays(60)->toDateString(),
        ]);
        $statuses = collect($this->service->getEmployeeDocumentStatus($employee))->keyBy('document_type');

        $this->assertEquals('upcoming', $statuses['contract']['status']);
    }

    /** @test */
    public function document_expiring_in_200_days_is_valid(): void
    {
        $employee = $this->makeEmployee([
            'iqama_expiry_date' => Carbon::today()->addDays(200)->toDateString(),
        ]);
        $statuses = collect($this->service->getEmployeeDocumentStatus($employee))->keyBy('document_type');

        $this->assertEquals('valid', $statuses['iqama']['status']);
    }

    /** @test */
    public function alertable_returns_only_documents_within_threshold(): void
    {
        $employee = $this->makeEmployee([
            'iqama_expiry_date'    => Carbon::today()->addDays(25)->toDateString(),  // hits 30d
            'passport_expiry_date' => Carbon::today()->addDays(100)->toDateString(), // outside 90d
        ]);

        $alertable = $this->service->getAlertableDocuments($employee, [90, 60, 30, 7]);

        $this->assertCount(1, $alertable);
        $this->assertEquals('iqama', $alertable[0]['document_type']);
        $this->assertEquals(30, $alertable[0]['threshold_hit']);
    }

    /** @test */
    public function alertable_returns_lapsed_documents(): void
    {
        $employee = $this->makeEmployee([
            'work_permit_expiry_date' => '2026-05-15', // lapsed
        ]);

        $alertable = $this->service->getAlertableDocuments($employee);

        $this->assertNotEmpty($alertable);
        $this->assertEquals('lapsed', $alertable[0]['status']);
    }

    /** @test */
    public function alertable_is_empty_when_all_documents_are_valid(): void
    {
        $employee = $this->makeEmployee([
            'iqama_expiry_date' => Carbon::today()->addDays(200)->toDateString(),
        ]);

        $this->assertEmpty($this->service->getAlertableDocuments($employee));
    }

    /** @test */
    public function all_document_types_have_non_empty_label_and_expiry_field(): void
    {
        foreach (DocumentType::cases() as $type) {
            $this->assertNotEmpty($type->label(), "Label missing for {$type->value}");
            $this->assertNotEmpty($type->expiryField(), "Expiry field missing for {$type->value}");
        }
    }

    /** @test */
    public function status_response_count_matches_document_type_count(): void
    {
        $statuses = $this->service->getEmployeeDocumentStatus($this->makeEmployee());

        $this->assertCount(count(DocumentType::cases()), $statuses);
    }
}
