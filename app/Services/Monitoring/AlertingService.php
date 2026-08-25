<?php

namespace App\Services\Monitoring;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertingService
{
    protected array $config;

    protected array $alertChannels;

    public function __construct()
    {
        $this->config = config('monitoring.alerting', [
            'enabled' => true,
            'cooldown_period' => 300, // 5 minutes
            'escalation_levels' => ['info', 'warning', 'critical', 'emergency'],
            'channels' => ['log', 'email', 'webhook'],
        ]);

        $this->alertChannels = [
            'log' => true,
            'email' => config('services.monitoring.email_alerts', false),
            'webhook' => config('services.monitoring.webhook_url') ?: false,
        ];
    }

    /**
     * Process and send alerts
     */
    public function processAlerts(array $alerts, array $context = []): void
    {
        if (! $this->config['enabled'] || empty($alerts)) {
            return;
        }

        foreach ($alerts as $alert) {
            $this->processAlert($alert, $context);
        }
    }

    /**
     * Process a single alert
     */
    protected function processAlert(array $alert, array $context): void
    {
        // Check cooldown period
        if ($this->isInCooldown($alert)) {
            return;
        }

        // Enrich alert with additional context
        $enrichedAlert = $this->enrichAlert($alert, $context);

        // Send through configured channels
        $this->sendToChannels($enrichedAlert);

        // Update cooldown
        $this->setCooldown($alert);

        // Store alert history
        $this->storeAlertHistory($enrichedAlert);
    }

    /**
     * Check if alert is in cooldown period
     */
    protected function isInCooldown(array $alert): bool
    {
        $cooldownKey = $this->getCooldownKey($alert);

        return Cache::has($cooldownKey);
    }

    /**
     * Set cooldown for alert
     */
    protected function setCooldown(array $alert): void
    {
        $cooldownKey = $this->getCooldownKey($alert);
        Cache::put($cooldownKey, true, $this->config['cooldown_period']);
    }

    /**
     * Generate cooldown key for alert
     */
    protected function getCooldownKey(array $alert): string
    {
        return 'alert_cooldown:'.md5($alert['type'].':'.$alert['metric']);
    }

    /**
     * Enrich alert with additional context
     */
    protected function enrichAlert(array $alert, array $context): array
    {
        $alert['id'] = uniqid('alert_');
        $alert['timestamp'] = now()->toISOString();
        $alert['hostname'] = gethostname();
        $alert['environment'] = app()->environment();
        $alert['context'] = $context;

        // Add trend analysis if available
        $alert['trend'] = $this->analyzeTrend($alert);

        // Add suggested actions
        $alert['suggested_actions'] = $this->getSuggestedActions($alert);

        return $alert;
    }

    /**
     * Analyze trend for the metric
     */
    protected function analyzeTrend(array $alert): array
    {
        $metricKey = "metric_history:{$alert['type']}:{$alert['metric']}";
        $history = Cache::get($metricKey, []);

        // Add current value to history
        $history[] = [
            'timestamp' => now()->timestamp,
            'value' => $alert['current'],
        ];

        // Keep only last 24 hours
        $cutoff = now()->subDay()->timestamp;
        $history = array_filter($history, fn ($point) => $point['timestamp'] >= $cutoff);

        // Store updated history
        Cache::put($metricKey, $history, 86400);

        if (count($history) < 2) {
            return ['status' => 'insufficient_data'];
        }

        // Calculate trend
        $values = array_column($history, 'value');
        $trend = $this->calculateTrendDirection($values);

        return [
            'direction' => $trend,
            'data_points' => count($history),
            'min' => min($values),
            'max' => max($values),
            'avg' => round(array_sum($values) / count($values), 2),
        ];
    }

    /**
     * Calculate trend direction from values
     */
    protected function calculateTrendDirection(array $values): string
    {
        if (count($values) < 2) {
            return 'unknown';
        }

        $recent = array_slice($values, -5); // Last 5 values
        $older = array_slice($values, -10, 5); // Previous 5 values

        if (empty($older)) {
            return 'unknown';
        }

        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);

        $change = (($recentAvg - $olderAvg) / $olderAvg) * 100;

        if ($change > 10) {
            return 'increasing';
        }
        if ($change < -10) {
            return 'decreasing';
        }

        return 'stable';
    }

    /**
     * Get suggested actions for alert
     */
    protected function getSuggestedActions(array $alert): array
    {
        $actions = [];

        switch ($alert['type']) {
            case 'performance':
                if ($alert['metric'] === 'response_time') {
                    $actions = [
                        'Check database slow queries',
                        'Review recent deployments',
                        'Monitor server resources',
                        'Check external API dependencies',
                    ];
                }
                break;

            case 'system':
                if ($alert['metric'] === 'memory_usage') {
                    $actions = [
                        'Review memory-intensive processes',
                        'Check for memory leaks',
                        'Consider scaling up server resources',
                        'Review large data processing jobs',
                    ];
                }
                break;

            case 'queue':
                $actions = [
                    'Check queue workers status',
                    'Review failed jobs',
                    'Monitor job processing times',
                    'Consider adding more workers',
                ];
                break;

            case 'errors':
                $actions = [
                    'Review error logs',
                    'Check recent code changes',
                    'Monitor external service status',
                    'Review user-reported issues',
                ];
                break;
        }

        return $actions;
    }

    /**
     * Send alert to configured channels
     */
    protected function sendToChannels(array $alert): void
    {
        foreach ($this->alertChannels as $channel => $enabled) {
            if (! $enabled) {
                continue;
            }

            try {
                $this->sendToChannel($channel, $alert);
            } catch (\Exception $e) {
                Log::error("Failed to send alert to {$channel}", [
                    'error' => $e->getMessage(),
                    'alert' => $alert,
                ]);
            }
        }
    }

    /**
     * Send alert to specific channel
     */
    protected function sendToChannel(string $channel, array $alert): void
    {
        switch ($channel) {
            case 'log':
                $this->sendToLog($alert);
                break;

            case 'email':
                $this->sendToEmail($alert);
                break;

            case 'webhook':
                $this->sendToWebhook($alert);
                break;
        }
    }

    /**
     * Send alert to log
     */
    protected function sendToLog(array $alert): void
    {
        $level = $alert['level'];
        $message = "[{$alert['type']}] {$alert['message']}";

        Log::log($level, $message, [
            'alert_id' => $alert['id'],
            'metric' => $alert['metric'],
            'current_value' => $alert['current'],
            'threshold' => $alert['threshold'],
            'trend' => $alert['trend'],
            'suggested_actions' => $alert['suggested_actions'],
        ]);
    }

    /**
     * Send alert to email
     */
    protected function sendToEmail(array $alert): void
    {
        if (! $this->alertChannels['email']) {
            return;
        }

        $to = config('services.monitoring.alert_email');
        if (! $to) {
            return;
        }

        $subject = "[{$alert['level']}] Monitoring Alert: {$alert['type']}";
        $body = $this->formatEmailBody($alert);

        // Use simple mail for now - could be enhanced with proper mail classes
        mail($to, $subject, $body, [
            'From' => config('services.monitoring.from_address'),
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * Format email body for alert
     */
    protected function formatEmailBody(array $alert): string
    {
        $body = "MONITORING ALERT\n";
        $body .= "================\n\n";
        $body .= "Alert ID: {$alert['id']}\n";
        $body .= "Timestamp: {$alert['timestamp']}\n";
        $body .= 'Level: '.strtoupper($alert['level'])."\n";
        $body .= "Type: {$alert['type']}\n";
        $body .= "Metric: {$alert['metric']}\n";
        $body .= "Message: {$alert['message']}\n\n";

        $body .= "DETAILS\n";
        $body .= "-------\n";
        $body .= "Current Value: {$alert['current']}\n";
        $body .= "Threshold: {$alert['threshold']}\n";
        $body .= "Environment: {$alert['environment']}\n";
        $body .= "Hostname: {$alert['hostname']}\n\n";

        if (! empty($alert['trend']) && $alert['trend']['status'] !== 'insufficient_data') {
            $body .= "TREND ANALYSIS\n";
            $body .= "-------------\n";
            $body .= "Direction: {$alert['trend']['direction']}\n";
            $body .= "Data Points: {$alert['trend']['data_points']}\n";
            $body .= "Min/Max/Avg: {$alert['trend']['min']}/{$alert['trend']['max']}/{$alert['trend']['avg']}\n\n";
        }

        if (! empty($alert['suggested_actions'])) {
            $body .= "SUGGESTED ACTIONS\n";
            $body .= "----------------\n";
            foreach ($alert['suggested_actions'] as $action) {
                $body .= "• {$action}\n";
            }
            $body .= "\n";
        }

        $body .= "This is an automated alert from the system monitoring service.\n";

        return $body;
    }

    /**
     * Send alert to webhook
     */
    protected function sendToWebhook(array $alert): void
    {
        $webhookUrl = config('services.monitoring.webhook_url');
        if (! $webhookUrl) {
            return;
        }

        $payload = [
            'alert' => $alert,
            'timestamp' => now()->toISOString(),
            'source' => 'laravel_monitoring',
        ];

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: Laravel-Monitoring/1.0',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("Webhook returned HTTP {$httpCode}: {$response}");
        }
    }

    /**
     * Store alert in history
     */
    protected function storeAlertHistory(array $alert): void
    {
        $historyKey = 'alert_history:'.now()->format('Y-m-d');
        $history = Cache::get($historyKey, []);

        $history[] = [
            'id' => $alert['id'],
            'timestamp' => $alert['timestamp'],
            'level' => $alert['level'],
            'type' => $alert['type'],
            'metric' => $alert['metric'],
            'message' => $alert['message'],
            'current' => $alert['current'],
            'threshold' => $alert['threshold'],
        ];

        // Keep only last 1000 alerts per day
        if (count($history) > 1000) {
            $history = array_slice($history, -1000);
        }

        Cache::put($historyKey, $history, 86400 * 7); // Keep for 7 days
    }

    /**
     * Get alert history
     */
    public function getAlertHistory(int $days = 7): array
    {
        $history = [];

        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($i);
            $dayHistory = Cache::get('alert_history:'.$date->format('Y-m-d'), []);
            $history = array_merge($history, $dayHistory);
        }

        // Sort by timestamp descending
        usort($history, fn ($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));

        return $history;
    }

    /**
     * Get active alerts
     */
    public function getActiveAlerts(): array
    {
        return Cache::get('monitoring_active_alerts', []);
    }

    /**
     * Clear active alerts
     */
    public function clearActiveAlerts(): void
    {
        Cache::forget('monitoring_active_alerts');
    }

    /**
     * Get alert statistics
     */
    public function getAlertStatistics(int $days = 7): array
    {
        $history = $this->getAlertHistory($days);

        $stats = [
            'total_alerts' => count($history),
            'by_level' => [],
            'by_type' => [],
            'by_metric' => [],
            'by_day' => [],
        ];

        foreach ($history as $alert) {
            // Count by level
            $level = $alert['level'];
            $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;

            // Count by type
            $type = $alert['type'];
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;

            // Count by metric
            $metric = $alert['metric'];
            $stats['by_metric'][$metric] = ($stats['by_metric'][$metric] ?? 0) + 1;

            // Count by day
            $day = Carbon::parse($alert['timestamp'])->format('Y-m-d');
            $stats['by_day'][$day] = ($stats['by_day'][$day] ?? 0) + 1;
        }

        return $stats;
    }
}
