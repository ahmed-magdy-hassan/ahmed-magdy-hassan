<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Notifications\Hr\DocumentExpiryNotification;
use App\Services\Hr\DocumentExpiryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

final class CheckDocumentExpiriesCommand extends Command
{
    protected $signature = 'hr:check-document-expiries
                            {--days=* : Override alert day thresholds (default: 90,60,30,7)}
                            {--company= : Scope to a specific company ID}';

    protected $description = 'Send document-expiry alerts to HR admins for upcoming and lapsed documents';

    public function __construct(private readonly DocumentExpiryService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $thresholds = $this->option('days')
            ? array_map('intval', (array) $this->option('days'))
            : DocumentExpiryService::DEFAULT_THRESHOLDS;

        sort($thresholds);
        $maxThreshold = max($thresholds);
        $companyScope = $this->option('company');

        $query = Company::query();
        if ($companyScope) {
            $query->where('id', (int) $companyScope);
        }

        $query->each(function (Company $company) use ($thresholds, $maxThreshold): void {
            $expiring = $this->service->getExpiringWithinDays($company->id, $maxThreshold);
            $lapsed   = $this->service->getLapsed($company->id);
            $toAlert  = $expiring->merge($lapsed)->unique('id');

            if ($toAlert->isEmpty()) {
                return;
            }

            $hrAdmins = $company->hrAdmins;

            foreach ($toAlert as $employee) {
                $alertable = $this->service->getAlertableDocuments($employee, $thresholds);

                if (empty($alertable)) {
                    continue;
                }

                Notification::send($hrAdmins, new DocumentExpiryNotification($employee, $alertable));
                $this->line("  [{$company->name}] Alerted: {$employee->full_name} — " . count($alertable) . ' doc(s)');
            }
        });

        $this->info('Document expiry check complete.');

        return self::SUCCESS;
    }
}
