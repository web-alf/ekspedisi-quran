<?php

use App\Models\DailyPackingTask;
use App\Models\PackingBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->supervisor = User::create([
        'name' => 'Test Supervisor',
        'email' => 'supervisor@test.com',
        'password' => bcrypt('password'),
        'role' => 'supervisor'
    ]);
    
    $this->warehouse = User::create([
        'name' => 'Warehouse User',
        'email' => 'warehouse@test.com',
        'password' => bcrypt('password'),
        'role' => 'warehouse'
    ]);
    
    $this->actingAs($this->supervisor);
});

it('loads warehouse monitor page without undefined data on initial request', function () {
    // Create test data using raw database queries to avoid factory issues
    $task = DailyPackingTask::create([
        'user_id' => $this->warehouse->id,
        'tanggal_tugas' => today(),
        'total_target' => 100,
        'total_selesai' => 50,
        'sisa_kemarin' => 0,
        'status' => 'in_progress',
        'assigned_by' => $this->supervisor->id,
        'assigned_at' => now(),
    ]);
    
    $response = $this->get('/supervisor/warehouse-monitor');
    
    // Verify page loads successfully with all required data
    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Supervisor/WarehouseMonitor')
            ->has('dailyTasks')
            ->has('warehouseUsers')
            ->has('recentActivities')
            ->has('summary')
            ->has('performanceStats')
            ->has('userPerformance')
            ->has('stockInfo')
        );
});

it('provides fallback values when no data exists', function () {
    // Empty database - test fallback values
    $response = $this->get('/supervisor/warehouse-monitor');
    
    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_active_tasks', 0)
            ->where('summary.total_packed_today', 0)
            ->where('summary.total_target_today', 0)
            ->where('summary.completion_rate', 0)
            ->where('userPerformance.summary.total_tasks', 0)
            ->where('userPerformance.summary.completed_tasks', 0)
            ->where('userPerformance.summary.total_mushaf', 0)
            ->where('userPerformance.summary.completion_rate', 0)
            ->has('dailyTasks')
            ->has('warehouseUsers')
            ->has('recentActivities')
            ->has('performanceStats')
            ->has('stockInfo')
        );
});

it('handles AJAX refresh consistently with page load', function () {
    $task = DailyPackingTask::create([
        'user_id' => $this->warehouse->id,
        'tanggal_tugas' => today(),
        'total_target' => 100,
        'total_selesai' => 75,
        'sisa_kemarin' => 0,
        'status' => 'in_progress',
        'assigned_by' => $this->supervisor->id,
        'assigned_at' => now(),
    ]);
    
    // Initial page load
    $pageResponse = $this->get('/supervisor/warehouse-monitor');
    
    // AJAX refresh
    $ajaxResponse = $this->postJson('/supervisor/warehouse-monitor/refresh');
    
    // Both should return consistent data
    $pageResponse->assertInertia(fn (Assert $page) => $page
        ->where('summary.total_active_tasks', 1)
        ->where('summary.total_packed_today', 75)
    );
    
    $ajaxResponse->assertStatus(200)
        ->assertJson([
            'summary' => [
                'total_active_tasks' => 1,
                'total_packed_today' => 75
            ]
        ]);
});

it('verifies all data structures are initialized with proper types', function () {
    $response = $this->get('/supervisor/warehouse-monitor');
    
    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            // Check that arrays are returned as arrays, not null
            ->whereType('dailyTasks', 'array')
            ->whereType('warehouseUsers', 'array')
            ->whereType('recentActivities', 'array')
            
            // Check that objects have proper structure
            ->has('summary.total_active_tasks')
            ->has('summary.total_packed_today')
            ->has('summary.total_target_today')
            ->has('summary.completion_rate')
            
            // Check userPerformance has proper structure
            ->has('userPerformance.summary')
            ->has('userPerformance.summary.total_tasks')
            ->has('userPerformance.summary.completed_tasks')
            ->has('userPerformance.summary.total_mushaf')
            ->has('userPerformance.summary.completion_rate')
            
            // Check performanceStats exists
            ->has('performanceStats')
            
            // Check stockInfo exists
            ->has('stockInfo')
        );
});

it('calculates summary stats correctly', function () {
    // Create multiple tasks to test calculation
    DailyPackingTask::create([
        'user_id' => $this->warehouse->id,
        'tanggal_tugas' => today(),
        'total_target' => 100,
        'total_selesai' => 80,
        'sisa_kemarin' => 0,
        'status' => 'completed',
        'assigned_by' => $this->supervisor->id,
        'assigned_at' => now(),
        'completed_at' => now(),
    ]);
    
    $user2 = User::create([
        'name' => 'Warehouse User 2',
        'email' => 'warehouse2@test.com',
        'password' => bcrypt('password'),
        'role' => 'warehouse'
    ]);
    
    DailyPackingTask::create([
        'user_id' => $user2->id,
        'tanggal_tugas' => today(),
        'total_target' => 50,
        'total_selesai' => 30,
        'sisa_kemarin' => 0,
        'status' => 'in_progress',
        'assigned_by' => $this->supervisor->id,
        'assigned_at' => now(),
    ]);
    
    $response = $this->get('/supervisor/warehouse-monitor');
    
    $response->assertInertia(fn (Assert $page) => $page
        ->where('summary.total_active_tasks', 2)
        ->where('summary.total_packed_today', 110) // 80 + 30
        ->where('summary.total_target_today', 150) // 100 + 50
        // Completion rate should be 110/150 = 73.33
        ->where('summary.completion_rate', 73.33)
    );
});

it('returns proper data types for numeric values', function () {
    $response = $this->get('/supervisor/warehouse-monitor');
    
    $response->assertInertia(fn (Assert $page) => $page
        // Verify numeric fields are returned as numbers, not strings
        ->whereType('summary.total_active_tasks', 'integer')
        ->whereType('summary.total_packed_today', 'integer')
        ->whereType('summary.total_target_today', 'integer')
        ->whereType('summary.completion_rate', 'double')
        
        ->whereType('userPerformance.summary.total_tasks', 'integer')
        ->whereType('userPerformance.summary.completed_tasks', 'integer')
        ->whereType('userPerformance.summary.total_mushaf', 'integer')
        ->whereType('userPerformance.summary.completion_rate', 'double')
    );
});

it('handles edge cases without errors', function () {
    // Test with edge case data that might cause division by zero
    DailyPackingTask::create([
        'user_id' => $this->warehouse->id,
        'tanggal_tugas' => today(),
        'total_target' => 0, // Edge case: zero target
        'total_selesai' => 0,
        'sisa_kemarin' => 0,
        'status' => 'assigned',
        'assigned_by' => $this->supervisor->id,
        'assigned_at' => now(),
    ]);
    
    $response = $this->get('/supervisor/warehouse-monitor');
    
    // Should handle division by zero gracefully
    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.completion_rate', 0)
            ->where('userPerformance.summary.completion_rate', 0)
        );
});

it('ensures no null values in critical data structures', function () {
    $response = $this->get('/supervisor/warehouse-monitor');
    
    $response->assertInertia(function (Assert $page) {
        // Get the page data to inspect
        $data = $page->toArray()['props'];
        
        // Verify critical fields are never null
        expect($data['dailyTasks'])->not->toBeNull();
        expect($data['warehouseUsers'])->not->toBeNull();
        expect($data['recentActivities'])->not->toBeNull();
        expect($data['summary'])->not->toBeNull();
        expect($data['performanceStats'])->not->toBeNull();
        expect($data['userPerformance'])->not->toBeNull();
        expect($data['stockInfo'])->not->toBeNull();
        
        // Verify summary structure
        expect($data['summary']['total_active_tasks'])->not->toBeNull();
        expect($data['summary']['total_packed_today'])->not->toBeNull();
        expect($data['summary']['total_target_today'])->not->toBeNull();
        expect($data['summary']['completion_rate'])->not->toBeNull();
        
        // Verify userPerformance structure
        expect($data['userPerformance']['summary'])->not->toBeNull();
        expect($data['userPerformance']['summary']['total_tasks'])->not->toBeNull();
        expect($data['userPerformance']['summary']['completed_tasks'])->not->toBeNull();
        expect($data['userPerformance']['summary']['total_mushaf'])->not->toBeNull();
        expect($data['userPerformance']['summary']['completion_rate'])->not->toBeNull();
        
        return $page;
    });
});