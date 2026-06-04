<?php

declare(strict_types=1);

namespace App\Notifications\Hr;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DocumentExpiryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Employee $employee,
        private readonly array $alertableDocuments,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Document Expiry Alert — {$this->employee->full_name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The following documents for **{$this->employee->full_name}** require attention:");

        foreach ($this->alertableDocuments as $doc) {
            if ($doc['days_remaining'] < 0) {
                $detail = 'LAPSED ' . abs($doc['days_remaining']) . ' day(s) ago';
            } else {
                $detail = "expires in {$doc['days_remaining']} day(s) on {$doc['expiry_date']}";
            }

            $mail->line("**{$doc['label']}** — {$detail}");
        }

        return $mail
            ->action('Review Employee Documents', url("/hr/employees/{$this->employee->id}/documents"))
            ->line('Please take appropriate action to renew these documents before they lapse.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'document_expiry',
            'employee_id' => $this->employee->id,
            'employee'    => $this->employee->full_name,
            'documents'   => $this->alertableDocuments,
        ];
    }
}
