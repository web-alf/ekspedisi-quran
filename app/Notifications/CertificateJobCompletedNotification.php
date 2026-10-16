<?php

namespace App\Notifications;

use App\Models\JobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateJobCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $jobProgress;

    protected $jobType;

    /**
     * Create a new notification instance.
     */
    public function __construct(JobProgress $jobProgress, string $jobType = 'certificate')
    {
        $this->jobProgress = $jobProgress;
        $this->jobType = $jobType;

        // Use notification queue
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = ['database'];

        // Add mail if user has email and job is important
        if ($notifiable->email && $this->shouldSendEmail()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $subject = $this->getEmailSubject();
        $greeting = "Halo {$notifiable->name},";

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($greeting);

        if ($this->jobProgress->status === 'completed') {
            $mail->success()
                ->line($this->getSuccessMessage())
                ->line($this->getJobSummary());

            // Add download links if available
            $downloadLinks = $this->getDownloadLinks();
            if (! empty($downloadLinks)) {
                $mail->line('File yang tersedia:');
                foreach ($downloadLinks as $link) {
                    $mail->action($link['text'], $link['url']);
                }
            }

            $mail->line('Terima kasih telah menggunakan sistem Ekspedisi Al-Quran.');

        } elseif ($this->jobProgress->status === 'failed') {
            $mail->error()
                ->line($this->getFailureMessage())
                ->line('Detail error: '.$this->jobProgress->error_message)
                ->line('Silakan coba lagi atau hubungi administrator jika masalah berlanjut.')
                ->action('Lihat Detail', route('admin.certificates.index'));
        }

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        $data = [
            'job_id' => $this->jobProgress->id,
            'job_type' => $this->jobProgress->job_type,
            'title' => $this->jobProgress->title,
            'status' => $this->jobProgress->status,
            'message' => $this->getNotificationMessage(),
            'icon' => $this->getNotificationIcon(),
            'color' => $this->getNotificationColor(),
            'created_at' => now(),
            'action_url' => $this->getActionUrl(),
            'action_text' => $this->getActionText(),
        ];

        // Add results for completed jobs
        if ($this->jobProgress->status === 'completed' && $this->jobProgress->results) {
            $data['results'] = $this->formatResultsForNotification();
        }

        // Add error details for failed jobs
        if ($this->jobProgress->status === 'failed') {
            $data['error_message'] = $this->jobProgress->error_message;
            $data['can_retry'] = $this->canRetry();
        }

        return $data;
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase($notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Determine if email should be sent based on job importance
     */
    private function shouldSendEmail(): bool
    {
        // Send email for:
        // - Failed jobs (user needs to know)
        // - Bulk jobs with many items (important completion)
        // - Jobs that took a long time (over 5 minutes)

        if ($this->jobProgress->status === 'failed') {
            return true;
        }

        if ($this->jobProgress->total_items >= 10) {
            return true;
        }

        if ($this->jobProgress->duration && $this->jobProgress->duration > 300) { // 5 minutes
            return true;
        }

        return false;
    }

    /**
     * Get email subject based on job status
     */
    private function getEmailSubject(): string
    {
        if ($this->jobProgress->status === 'completed') {
            return "Sertifikat berhasil dibuat - {$this->jobProgress->title}";
        } elseif ($this->jobProgress->status === 'failed') {
            return "Gagal membuat sertifikat - {$this->jobProgress->title}";
        }

        return "Update pekerjaan - {$this->jobProgress->title}";
    }

    /**
     * Get success message for notifications
     */
    private function getSuccessMessage(): string
    {
        $results = $this->jobProgress->results ?? [];
        $generated = $results['certificates_generated'] ?? 0;
        $failed = $results['certificates_failed'] ?? 0;

        if ($failed == 0) {
            return "Berhasil membuat {$generated} sertifikat tanpa error.";
        } else {
            return "Selesai dengan {$generated} sertifikat berhasil dan {$failed} gagal.";
        }
    }

    /**
     * Get failure message for notifications
     */
    private function getFailureMessage(): string
    {
        return 'Pembuatan sertifikat gagal karena terjadi error dalam sistem.';
    }

    /**
     * Get job summary for notifications
     */
    private function getJobSummary(): string
    {
        $summary = [];

        if ($this->jobProgress->duration_formatted) {
            $summary[] = "Waktu pemrosesan: {$this->jobProgress->duration_formatted}";
        }

        $results = $this->jobProgress->results ?? [];
        if (isset($results['total_size_mb'])) {
            $summary[] = "Total ukuran file: {$results['total_size_mb']} MB";
        }

        if (isset($results['success_rate'])) {
            $summary[] = "Tingkat keberhasilan: {$results['success_rate']}%";
        }

        return implode(' | ', $summary);
    }

    /**
     * Get download links for completed jobs
     */
    private function getDownloadLinks(): array
    {
        $links = [];
        $results = $this->jobProgress->results ?? [];

        // ZIP file download if available
        if (isset($results['zip_download_url'])) {
            $links[] = [
                'text' => 'Download ZIP Semua Sertifikat',
                'url' => $results['zip_download_url'],
            ];
        }

        // Individual certificates (limit to first 5 for email)
        if (isset($results['generated_files']) && is_array($results['generated_files'])) {
            $files = array_slice($results['generated_files'], 0, 5);
            foreach ($files as $file) {
                if (isset($file['download_url'])) {
                    $name = $file['donatur_name'] ?? $file['batch_code'] ?? 'Sertifikat';
                    $links[] = [
                        'text' => "Download {$name}",
                        'url' => $file['download_url'],
                    ];
                }
            }
        }

        return $links;
    }

    /**
     * Get notification message based on status
     */
    private function getNotificationMessage(): string
    {
        if ($this->jobProgress->status === 'completed') {
            return $this->getSuccessMessage();
        } elseif ($this->jobProgress->status === 'failed') {
            return $this->getFailureMessage();
        }

        return "Status pekerjaan berubah menjadi: {$this->jobProgress->status_display}";
    }

    /**
     * Get notification icon based on status
     */
    private function getNotificationIcon(): string
    {
        return match ($this->jobProgress->status) {
            'completed' => 'check-circle',
            'failed' => 'x-circle',
            'processing' => 'clock',
            default => 'info'
        };
    }

    /**
     * Get notification color based on status
     */
    private function getNotificationColor(): string
    {
        return match ($this->jobProgress->status) {
            'completed' => 'green',
            'failed' => 'red',
            'processing' => 'blue',
            default => 'gray'
        };
    }

    /**
     * Get action URL for notification
     */
    private function getActionUrl(): string
    {
        // Return to certificates index by default
        return route('admin.certificates.index');
    }

    /**
     * Get action text for notification
     */
    private function getActionText(): string
    {
        if ($this->jobProgress->status === 'completed') {
            return 'Lihat Sertifikat';
        } elseif ($this->jobProgress->status === 'failed') {
            return 'Lihat Detail Error';
        }

        return 'Lihat Detail';
    }

    /**
     * Format results for notification display
     */
    private function formatResultsForNotification(): array
    {
        $results = $this->jobProgress->results ?? [];

        return [
            'certificates_generated' => $results['certificates_generated'] ?? 0,
            'certificates_failed' => $results['certificates_failed'] ?? 0,
            'processing_time' => $results['processing_time_formatted'] ?? null,
            'success_rate' => $results['success_rate'] ?? null,
            'total_size_mb' => $results['total_size_mb'] ?? null,
            'has_zip' => isset($results['zip_download_url']),
            'files_count' => isset($results['generated_files']) ? count($results['generated_files']) : 0,
        ];
    }

    /**
     * Check if job can be retried
     */
    private function canRetry(): bool
    {
        return $this->jobProgress->status === 'failed' &&
               in_array($this->jobProgress->job_type, [
                   'single_certificate_generation',
                   'bulk_certificate_generation',
                   'consolidated_certificate_generation',
               ]);
    }
}
