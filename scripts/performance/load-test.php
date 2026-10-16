#!/usr/bin/env php
<?php

/**
 * Load Testing Script
 * 
 * This script simulates concurrent users and measures system performance under load
 */

require_once __DIR__ . '/../../vendor/autoload.php';

class LoadTester
{
    private $baseUrl;
    private $results = [];
    private $concurrentUsers = 10;
    private $requestsPerUser = 20;
    private $endpoints = [
        'GET /admin/dashboard',
        'GET /admin/pengiriman',
        'GET /admin/pengiriman?page=2',
        'GET /admin/pengiriman?search=test',
        'GET /admin/donatur',
        'GET /tracking/RESI123456'
    ];

    public function __construct($baseUrl = 'http://localhost:8000')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        echo "🚀 Load Testing Started\n";
        echo "Base URL: {$this->baseUrl}\n";
        echo "Concurrent Users: {$this->concurrentUsers}\n";
        echo "Requests per User: {$this->requestsPerUser}\n";
        echo "==========================================\n\n";
    }

    public function runLoadTest()
    {
        $this->warmupTest();
        $this->singleUserTest();
        $this->concurrentUserTest();
        $this->peakLoadTest();
        $this->generateReport();
    }

    private function warmupTest()
    {
        echo "🔥 Warming up system...\n";
        
        // Make a few requests to warm up caches
        for ($i = 0; $i < 5; $i++) {
            $this->makeRequest('GET', '/');
            usleep(100000); // 100ms delay
        }
        
        echo "  ✓ Warmup completed\n\n";
    }

    private function singleUserTest()
    {
        echo "👤 Single User Performance Test...\n";
        
        $results = [];
        $totalTime = 0;
        
        foreach ($this->endpoints as $endpoint) {
            list($method, $path) = explode(' ', $endpoint, 2);
            
            $start = microtime(true);
            $response = $this->makeRequest($method, $path);
            $responseTime = (microtime(true) - $start) * 1000;
            
            $results[$endpoint] = [
                'response_time' => $responseTime,
                'status_code' => $response['status_code'] ?? 0,
                'success' => ($response['status_code'] ?? 0) < 400
            ];
            
            $totalTime += $responseTime;
            
            echo "  {$endpoint}: " . round($responseTime, 2) . "ms (Status: {$response['status_code']})\n";
        }
        
        $this->results['single_user'] = [
            'total_time' => $totalTime,
            'avg_response_time' => $totalTime / count($this->endpoints),
            'endpoints' => $results
        ];
        
        echo "  ✓ Average response time: " . round($this->results['single_user']['avg_response_time'], 2) . "ms\n\n";
    }

    private function concurrentUserTest()
    {
        echo "👥 Concurrent Users Test ({$this->concurrentUsers} users)...\n";
        
        $start = microtime(true);
        $processes = [];
        $results = [];
        
        // Create temporary files for process communication
        $tempFiles = [];
        for ($i = 0; $i < $this->concurrentUsers; $i++) {
            $tempFiles[$i] = tempnam(sys_get_temp_dir(), 'loadtest_');
        }
        
        // Start concurrent processes
        for ($i = 0; $i < $this->concurrentUsers; $i++) {
            $cmd = sprintf(
                'php %s concurrent-worker %s %d %d %s > /dev/null 2>&1 &',
                __FILE__,
                escapeshellarg($this->baseUrl),
                $i,
                $this->requestsPerUser,
                escapeshellarg($tempFiles[$i])
            );
            
            exec($cmd);
            $processes[] = $i;
        }
        
        // Wait for all processes to complete (max 60 seconds)
        $timeout = 60;
        $waited = 0;
        
        do {
            sleep(1);
            $waited++;
            
            $completed = 0;
            foreach ($tempFiles as $file) {
                if (file_exists($file) && filesize($file) > 0) {
                    $completed++;
                }
            }
            
            echo "  Progress: {$completed}/{$this->concurrentUsers} users completed\r";
            
        } while ($completed < $this->concurrentUsers && $waited < $timeout);
        
        $totalTestTime = (microtime(true) - $start) * 1000;
        
        // Collect results from temporary files
        $allResponseTimes = [];
        $successfulRequests = 0;
        $totalRequests = 0;
        
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true);
                if ($data) {
                    $allResponseTimes = array_merge($allResponseTimes, $data['response_times']);
                    $successfulRequests += $data['successful_requests'];
                    $totalRequests += $data['total_requests'];
                }
                unlink($file);
            }
        }
        
        $this->results['concurrent'] = [
            'total_test_time' => $totalTestTime,
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $totalRequests - $successfulRequests,
            'success_rate' => ($totalRequests > 0) ? ($successfulRequests / $totalRequests) * 100 : 0,
            'avg_response_time' => count($allResponseTimes) > 0 ? array_sum($allResponseTimes) / count($allResponseTimes) : 0,
            'min_response_time' => count($allResponseTimes) > 0 ? min($allResponseTimes) : 0,
            'max_response_time' => count($allResponseTimes) > 0 ? max($allResponseTimes) : 0,
            'requests_per_second' => $totalRequests > 0 ? $totalRequests / ($totalTestTime / 1000) : 0
        ];
        
        echo "\n  ✓ Concurrent test completed\n";
        echo "  ✓ Total requests: {$totalRequests}\n";
        echo "  ✓ Success rate: " . round($this->results['concurrent']['success_rate'], 1) . "%\n";
        echo "  ✓ Requests/second: " . round($this->results['concurrent']['requests_per_second'], 2) . "\n\n";
    }

    private function peakLoadTest()
    {
        echo "⚡ Peak Load Test (burst)...\n";
        
        $burstSize = 50;
        $start = microtime(true);
        
        $processes = [];
        $tempFiles = [];
        
        // Create burst of requests
        for ($i = 0; $i < $burstSize; $i++) {
            $tempFiles[$i] = tempnam(sys_get_temp_dir(), 'burst_');
            
            $endpoint = $this->endpoints[array_rand($this->endpoints)];
            list($method, $path) = explode(' ', $endpoint, 2);
            
            $cmd = sprintf(
                'php %s burst-worker %s %s %s %s > /dev/null 2>&1 &',
                __FILE__,
                escapeshellarg($this->baseUrl),
                escapeshellarg($method),
                escapeshellarg($path),
                escapeshellarg($tempFiles[$i])
            );
            
            exec($cmd);
        }
        
        // Wait for completion
        $timeout = 30;
        $waited = 0;
        
        do {
            sleep(1);
            $waited++;
            
            $completed = 0;
            foreach ($tempFiles as $file) {
                if (file_exists($file) && filesize($file) > 0) {
                    $completed++;
                }
            }
            
            echo "  Burst progress: {$completed}/{$burstSize} requests completed\r";
            
        } while ($completed < $burstSize && $waited < $timeout);
        
        $burstTime = (microtime(true) - $start) * 1000;
        
        // Collect burst results
        $burstResponseTimes = [];
        $burstSuccessful = 0;
        
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['response_time'])) {
                    $burstResponseTimes[] = $data['response_time'];
                    if ($data['success']) {
                        $burstSuccessful++;
                    }
                }
                unlink($file);
            }
        }
        
        $this->results['peak_load'] = [
            'burst_size' => $burstSize,
            'burst_time' => $burstTime,
            'successful_requests' => $burstSuccessful,
            'success_rate' => ($burstSuccessful / $burstSize) * 100,
            'avg_response_time' => count($burstResponseTimes) > 0 ? array_sum($burstResponseTimes) / count($burstResponseTimes) : 0,
            'max_response_time' => count($burstResponseTimes) > 0 ? max($burstResponseTimes) : 0,
            'peak_rps' => $burstSize / ($burstTime / 1000)
        ];
        
        echo "\n  ✓ Peak load test completed\n";
        echo "  ✓ Burst success rate: " . round($this->results['peak_load']['success_rate'], 1) . "%\n";
        echo "  ✓ Peak RPS: " . round($this->results['peak_load']['peak_rps'], 2) . "\n\n";
    }

    private function makeRequest($method, $path, $timeout = 10)
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'LoadTester/1.0'
        ]);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        return [
            'status_code' => $statusCode,
            'response' => $response,
            'error' => $error
        ];
    }

    private function generateReport()
    {
        echo "📋 Load Test Report\n";
        echo "===================\n\n";
        
        echo "📊 Single User Performance:\n";
        echo "  Average response time: " . round($this->results['single_user']['avg_response_time'], 2) . "ms\n\n";
        
        echo "👥 Concurrent Users ({$this->concurrentUsers} users):\n";
        echo "  Total requests: " . $this->results['concurrent']['total_requests'] . "\n";
        echo "  Success rate: " . round($this->results['concurrent']['success_rate'], 1) . "%\n";
        echo "  Average response time: " . round($this->results['concurrent']['avg_response_time'], 2) . "ms\n";
        echo "  Min response time: " . round($this->results['concurrent']['min_response_time'], 2) . "ms\n";
        echo "  Max response time: " . round($this->results['concurrent']['max_response_time'], 2) . "ms\n";
        echo "  Requests per second: " . round($this->results['concurrent']['requests_per_second'], 2) . "\n\n";
        
        echo "⚡ Peak Load (burst of 50):\n";
        echo "  Success rate: " . round($this->results['peak_load']['success_rate'], 1) . "%\n";
        echo "  Average response time: " . round($this->results['peak_load']['avg_response_time'], 2) . "ms\n";
        echo "  Max response time: " . round($this->results['peak_load']['max_response_time'], 2) . "ms\n";
        echo "  Peak RPS: " . round($this->results['peak_load']['peak_rps'], 2) . "\n\n";
        
        echo "🎯 Performance Grade: " . $this->calculateLoadGrade() . "\n\n";
        
        $this->saveLoadReport();
    }

    private function calculateLoadGrade()
    {
        $score = 100;
        
        // Deduct points for poor performance
        if ($this->results['single_user']['avg_response_time'] > 500) $score -= 20;
        if ($this->results['concurrent']['success_rate'] < 95) $score -= 25;
        if ($this->results['concurrent']['avg_response_time'] > 1000) $score -= 20;
        if ($this->results['peak_load']['success_rate'] < 80) $score -= 25;
        if ($this->results['concurrent']['requests_per_second'] < 10) $score -= 10;
        
        if ($score >= 90) return 'A+ (Excellent)';
        if ($score >= 80) return 'A (Very Good)';
        if ($score >= 70) return 'B (Good)';
        if ($score >= 60) return 'C (Acceptable)';
        return 'D (Needs Improvement)';
    }

    private function saveLoadReport()
    {
        $reportData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'test_config' => [
                'base_url' => $this->baseUrl,
                'concurrent_users' => $this->concurrentUsers,
                'requests_per_user' => $this->requestsPerUser,
                'endpoints' => $this->endpoints
            ],
            'results' => $this->results,
            'grade' => $this->calculateLoadGrade()
        ];
        
        $filename = 'load-test-report-' . date('Y-m-d-H-i-s') . '.json';
        $filepath = __DIR__ . '/reports/' . $filename;
        
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        file_put_contents($filepath, json_encode($reportData, JSON_PRETTY_PRINT));
        
        echo "📁 Load test report saved to: {$filename}\n";
    }
}

// Handle different execution modes
if ($argc > 1) {
    $mode = $argv[1];
    
    if ($mode === 'concurrent-worker') {
        // Worker process for concurrent testing
        $baseUrl = $argv[2];
        $workerId = $argv[3];
        $requests = $argv[4];
        $outputFile = $argv[5];
        
        $responseTimes = [];
        $successful = 0;
        $total = 0;
        
        $endpoints = [
            'GET /',
            'GET /admin/dashboard',
            'GET /admin/pengiriman',
            'GET /tracking/TEST123'
        ];
        
        for ($i = 0; $i < $requests; $i++) {
            $endpoint = $endpoints[array_rand($endpoints)];
            list($method, $path) = explode(' ', $endpoint, 2);
            
            $start = microtime(true);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $baseUrl . $path,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $responseTime = (microtime(true) - $start) * 1000;
            $responseTimes[] = $responseTime;
            $total++;
            
            if ($statusCode < 400) {
                $successful++;
            }
        }
        
        $result = [
            'worker_id' => $workerId,
            'response_times' => $responseTimes,
            'successful_requests' => $successful,
            'total_requests' => $total
        ];
        
        file_put_contents($outputFile, json_encode($result));
        
    } elseif ($mode === 'burst-worker') {
        // Worker process for burst testing
        $baseUrl = $argv[2];
        $method = $argv[3];
        $path = $argv[4];
        $outputFile = $argv[5];
        
        $start = microtime(true);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $responseTime = (microtime(true) - $start) * 1000;
        
        $result = [
            'response_time' => $responseTime,
            'status_code' => $statusCode,
            'success' => $statusCode < 400
        ];
        
        file_put_contents($outputFile, json_encode($result));
    }
} else {
    // Main load test execution
    $baseUrl = $argv[1] ?? 'http://localhost:8000';
    $loadTester = new LoadTester($baseUrl);
    $loadTester->runLoadTest();
    
    echo "✅ Load testing completed!\n";
}
