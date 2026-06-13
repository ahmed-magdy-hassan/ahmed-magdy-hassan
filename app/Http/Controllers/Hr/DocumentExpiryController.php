<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\DocumentExpiryUpdateRequest;
use App\Models\Employee;
use App\Services\Hr\DocumentExpiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocumentExpiryController extends Controller
{
    public function __construct(private readonly DocumentExpiryService $service) {}

    /**
     * GET /api/hr/employees/{employee}/documents
     * Full expiry status for all document types on one employee.
     */
    public function show(Employee $employee): JsonResponse
    {
        return response()->json([
            'employee_id' => $employee->id,
            'documents'   => $this->service->getEmployeeDocumentStatus($employee),
        ]);
    }

    /**
     * PATCH /api/hr/employees/{employee}/documents
     * Set or update expiry dates for one or more document types.
     */
    public function update(DocumentExpiryUpdateRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());

        return response()->json([
            'employee_id' => $employee->id,
            'documents'   => $this->service->getEmployeeDocumentStatus($employee),
        ]);
    }

    /**
     * GET /api/hr/companies/{companyId}/documents/expiring?days=30
     * All employees with documents expiring within N days (default 30).
     */
    public function expiring(Request $request, int $companyId): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $days = max(1, min($days, 365));

        $employees = $this->service->getExpiringWithinDays($companyId, $days);

        $rows = $employees->map(fn (Employee $e) => [
            'employee_id'   => $e->id,
            'employee_name' => $e->full_name,
            'documents'     => $this->service->getAlertableDocuments($e),
        ]);

        return response()->json([
            'days'      => $days,
            'total'     => $rows->count(),
            'employees' => $rows->values(),
        ]);
    }

    /**
     * GET /api/hr/companies/{companyId}/documents/lapsed
     * All employees with at least one already-lapsed document.
     */
    public function lapsed(int $companyId): JsonResponse
    {
        $employees = $this->service->getLapsed($companyId);

        $rows = $employees->map(fn (Employee $e) => [
            'employee_id'   => $e->id,
            'employee_name' => $e->full_name,
            'documents'     => collect($this->service->getEmployeeDocumentStatus($e))
                ->where('status', 'lapsed')
                ->values(),
        ]);

        return response()->json([
            'total'     => $rows->count(),
            'employees' => $rows->values(),
        ]);
    }
}
