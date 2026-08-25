<?php

use App\Http\Controllers\Supervisor\WarehouseMonitorController;
use App\Models\DailyPackingTask;
use App\Models\PackingBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->supervisor = User::factory()->create([
        'name' => 'Test Supervisor',
        'role' => 'supervisor'
    ]);
    
    $this->warehouse1 = User::factory()->create([
        'name' => 'Warehouse User 1',
        'role' => 'warehouse'
    ]);
    
    $this->warehouse2 = User::factory()->create([
        'name' => 'Warehouse User 2',
        'role' => 'warehouse'
    ]);
    
    $this->actingAs($this->supervisor);
});

it('displays warehouse monitor dashboard with all required data', function () {
    // Create test data
    $task1 = DailyPackingTask::factory()->create([
        'user_id' => $this->warehouse1->id,
        'target_mushaf' => 100,
        'packed_mushaf' => 75,
        'status' => 'in_progress',
        'date' => today()
    ]);
    
    $task2 = DailyPackingTask::factory()->create([
        'user_id' => $this->warehouse2->id,
        'target_mushaf' => 80,
        'packed_mushaf' => 80,
        'status' => 'completed',
        'date' => today()
    ]);
    
    PackingBox::factory()->create([
        'daily_packing_task_id' => $task1->id,
        'mushaf_count' => 25,
        'status' => 'sealed'
    ]);
    
    $response = $this->get('/supervisor/warehouse-monitor');
    
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

it('provides correct summary statistics', function () {
    DailyPackingTask::factory()->create([
        'user_id' => $this->warehouse1->id,
        'target_mushaf' => 100,
        'packed_mushaf' => 50,
        'status' => 'in_progress',
        'date' => today()
    ]);
    
    DailyPackingTask::factory()->create([
        'user_id' => $this->warehouse2->id,
        'target_mushaf' => 80,
        'packed_mushaf' => 80,
        'status' => 'completed',
        'date' => today()
    ]);
    
    $response = $this->get('/supervisor/warehouse-monitor');
    
    $response->assertInertia(fn (Assert $page) => $page
        ->where('summary.total_active_tasks', 2)
        ->where('summary.total_packed_today', 130)
        ->where('summary.total_target_today', 180)
        ->where('summary.completion_rate', 72.22)
    );
});

it('calculates user performance correctly', function () {
    // Create tasks for user performance calculation
    DailyPackingTask::factory()->create([
        'user_id' => $this->warehouse1->id,
        'target_mushaf' => 100,
        'packed_mushaf' => 100,
        'status' => 'completed',
        'date' => today()
    ]);
    
    DailyPackingTask::factory()->create([
        'user_id' => $this->warehouse1->id,
        'target_mushaf' => 80,
        'packed_mushaf' => 40,
        'status' => 'in_progress',
        'date' => today()->subDay()
    ]);
    
    $response = $this->get('/supervisor/warehouse-monitor');
    
    $response->assertInertia(fn (Assert $page) => $page
        ->where('userPerformance.summary.total_tasks', 2)
        ->where('userPerformance.summary.completed_tasks', 1)
        ->where('userPerformance.summary.total_mushaf', 140)
        ->where('userPerformance.summary.completion_rate', 50.0)
    );
});

it('handles empty data gracefully', function () {
    // No data in database
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
        );
});

it('refreshes data via AJAX endpoint', function () {
    DailyPackingTask::factory()->create([
        'user_id' => $this->warehouse1->id,
        'target_mushaf' => 50,
        'packed_mushaf' => 25,
        'status' => 'in_progress',
        'date' => today()
    ]);
    
    $response = $this->postJson('/supervisor/warehouse-monitor/refresh');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'dailyTasks',
            'summary' => [
                'total_active_tasks',
                'total_packed_today', 
                'total_target_today',
                'completion_rate'
            ],
            'performanceStats' => [
                'monthly'
            ],
            'userPerformance' => [
                'summary' => [
                    'total_tasks',
                    'completed_tasks',
                    'total_mushaf',
                    'completion_rate'
                ]
            ]
        ]);
});

it('requires supervisor role to access warehouse monitor', function () {
    $warehouseUser = User::factory()->create(['role' => 'warehouse']);
    $this->actingAs($warehouseUser);
    
    $response = $this->get('/supervisor/warehouse-monitor');
    
    $response->assertStatus(403);
});

it('handles database errors gracefully', function () {
    // Mock database error by creating invalid relationship
    $task = DailyPackingTask::factory()->create([
        'user_id' => 999999, // Non-existent user
        'date' => today()
    ]);
    
    $response = $this->get('/supervisor/warehouse-monitor');
    
    // Should still return 200 with fallback data
    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->has('summary')
            ->has('userPerformance')
        );
});

it('eager loads relationships to prevent N+1 queries', function () {
    // Create multiple tasks with boxes to test eager loading
    $task1 = DailyPackingTask::factory()->create([
        'user_id' => $this->warehouse1->id,
        'date' => today()
    ]);
    
    $task2 = DailyPackingTask::factory()->create([
        'user_id' => $this->warehouse2->id,
        'date' => today()
    ]);
    
    PackingBox::factory()->create(['daily_packing_task_id' => $task1->id]);
    PackingBox::factory()->create(['daily_packing_task_id' => $task1->id]);
    PackingBox::factory()->create(['daily_packing_task_id' => $task2->id]);
    
    // Enable query log to check for N+1
    \DB::enableQueryLog();
    
    $response = $this->get('/supervisor/warehouse-monitor');
    
    $queries = \DB::getQueryLog();
    \DB::disableQueryLog();
    
    // Should be minimal queries due to eager loading
    expect(count($queries))->toBeLessThan(10);
    $response->assertStatus(200);
});

it('formats numbers correctly in response', function () {
    DailyPackingTask::factory()->create([
        'user_id' => $this->warehouse1->id,
        'target_mushaf' => 1000,
        'packed_mushaf' => 750,
        'date' => today()
    ]);
    
    $response = $this->get('/supervisor/warehouse-monitor');
    
    $response->assertInertia(fn (Assert $page) => $page
        ->where('summary.total_packed_today', 750)
        ->where('summary.total_target_today', 1000)
        ->where('summary.completion_rate', 75.0)
    );
});

it('calculates monthly performance stats correctly', function () {
    // Create tasks across different dates this month
    DailyPackingTask::factory()->create([
        'user_id' => $this->warehouse1->id,
        'packed_mushaf' => 100,
        'date' => today()->startOfMonth()
    ]);
    
    DailyPackingTask::factory()->create([
        'user_id' => $this->warehouse1->id,
        'packed_mushaf' => 150,
        'date' => today()->startOfMonth()->addDays(5)
    ]);
    
    DailyPackingTask::factory()->create([
        'user_id' => $this->warehouse2->id,
        'packed_mushaf' => 80,
        'date' => today()->startOfMonth()->addDays(10)
    ]);
    
    $response = $this->get('/supervisor/warehouse-monitor');
    
    $response->assertInertia(fn (Assert $page) => $page
        ->has('performanceStats.monthly')
        ->where('performanceStats.monthly.total_mushaf', 330)
    );
});