<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class QueueJobFailedNotification extends Notification
{
    use Queueable;

    protected $job;
    protected $exception;

    public function __construct($job, $exception)
    {
        $this->job = $job;
        $this->exception = $exception;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->error()
                    ->subject('Queue Job Failed - Ekspedisi Quran')
                    ->line('A queue job has failed in the Ekspedisi Quran system.')
                    ->line('**Job Class:** ' . get_class($this->job))
                    ->line('**Queue:** ' . ($this->job->queue ?? 'default'))
                    ->line('**Failed At:** ' . now()->format('Y-m-d H:i:s'))
                    ->line('**Error:** ' . $this->exception->getMessage())
                    ->line('Please check the logs for more details.')
                    ->action('View Logs', url('/admin/logs'));
    }

    public function toArray($notifiable)
    {
        return [
            'job_class' => get_class($this->job),
            'queue' => $this->job->queue ?? 'default',
            'failed_at' => now()->toDateTimeString(),
            'error_message' => $this->exception->getMessage(),
            'job_payload' => $this->getJobPayload(),
        ];
    }

    private function getJobPayload()
    {
        try {
            $payload = [];
            
            // Extract useful information from common job types
            if (method_exists($this->job, 'sertifikat') && $this->job->sertifikat) {
                $payload['sertifikat_id'] = $this->job->sertifikat->id;
                $payload['nomor_sertifikat'] = $this->job->sertifikat->nomor_sertifikat;
            }
            
            if (method_exists($this->job, 'donatur') && $this->job->donatur) {
                $payload['donatur_id'] = $this->job->donatur->id;
                $payload['donatur_name'] = $this->job->donatur->nama_donatur;
            }
            
            if (method_exists($this->job, 'pengiriman') && $this->job->pengiriman) {
                $payload['pengiriman_id'] = $this->job->pengiriman->id;
                $payload['no_resi'] = $this->job->pengiriman->no_resi;
            }

            return $payload;
        } catch (\Exception $e) {
            Log::warning('Failed to extract job payload', ['error' => $e->getMessage()]);
            return ['error' => 'Could not extract job details'];
        }
    }
}