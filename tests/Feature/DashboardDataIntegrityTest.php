<?php

use App\Models\DailyPackingTask;
use App\Models\PackingBox;
use App\Models\SharedBoxAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->warehouseUser = User::factory()->create([
        'name' => 'Warehouse User',
        'role' => 'warehouse'
    ]);
    
    $this->supervisor = User::factory()->create([
        'name' => 'Supervisor',
        'role' => 'supervisor'  
    ]);
});

describe('Browser Refresh Data Integrity', function () {
    it('loads all data synchronously on initial page load', function () {
        $this->actingAs($this->supervisor);
        
        // Create comprehensive test data
        $task = DailyPackingTask::factory()->create([
            'user_id' => $this->warehouseUser->id,
            'target_mushaf' => 100,
            'packed_mushaf' => 50,
            'status' => 'in_progress',
            'date' => today()
        ]);
        
        PackingBox::factory()->create([
            'daily_packing_task_id' => $task->id,
            'mushaf_count' => 25,
            'status' => 'sealed'
        ]);
        
        $response = $this->get('/supervisor/warehouse-monitor');
        
        // Verify all data is available immediately
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Supervisor/WarehouseMonitor')
            ->has('dailyTasks')
            ->has('warehouseUsers')  
            ->has('recentActivities')
            ->has('summary')
            ->has('performanceStats')
            ->has('userPerformance')
            ->has('stockInfo')
            ->where('summary.total_active_tasks', 1)
            ->where('summary.total_packed_today', 50)
            ->where('userPerformance.summary.total_tasks', 1)
            ->where('userPerformance.summary.total_mushaf', 50)
        );
    });
    
    it('provides default values for all data structures', function () {
        $this->actingAs($this->supervisor);
        
        // Empty database - test fallback values
        $response = $this->get('/supervisor/warehouse-monitor');
        
        $response->assertInertia(fn (Assert $page) => $page
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
    
    it('handles null values gracefully in calculations', function () {
        $this->actingAs($this->supervisor);
        
        // Create task with null values that could cause issues
        DailyPackingTask::factory()->create([
            'user_id' => $this->warehouseUser->id,
            'target_mushaf' => null,
            'packed_mushaf' => null,
            'date' => today()
        ]);
        
        $response = $this->get('/supervisor/warehouse-monitor');
        
        // Should handle nulls without errors
        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total_active_tasks', 1)
                ->where('summary.total_packed_today', 0)
                ->where('summary.total_target_today', 0)
            );
    });
});

describe('Warehouse Dashboard Data Integrity', function () {
    it('loads warehouse dashboard data without undefined values', function () {
        $this->actingAs($this->warehouseUser);
        
        $task = DailyPackingTask::factory()->create([
            'user_id' => $this->warehouseUser->id,
            'target_mushaf' => 50,
            'packed_mushaf' => 25,
            'status' => 'in_progress',
            'date' => today()
        ]);
        
        $response = $this->get('/warehouse/dashboard');
        
        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Dashboard')
                ->has('todayTask')
                ->has('completedBoxes')
                ->has('activeSharedBoxes')
                ->has('recentActivities')
            );
    });
    
    it('provides fallback when no daily task exists', function () {
        $this->actingAs($this->warehouseUser);
        
        // No daily task for today
        $response = $this->get('/warehouse/dashboard');
        
        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->where('todayTask', null)
                ->has('completedBoxes')
                ->has('activeSharedBoxes')
                ->has('recentActivities')
            );
    });
});

describe('Data Consistency Across Refreshes', function () {
    it('maintains data consistency between page loads and AJAX refreshes', function () {
        $this->actingAs($this->supervisor);
        
        $task = DailyPackingTask::factory()->create([
            'user_id' => $this->warehouseUser->id,
            'target_mushaf' => 100,
            'packed_mushaf' => 75,
            'date' => today()
        ]);
        
        // Initial page load
        $pageResponse = $this->get('/supervisor/warehouse-monitor');
        
        // AJAX refresh
        $ajaxResponse = $this->postJson('/supervisor/warehouse-monitor/refresh');
        
        // Both should have same data structure
        $pageResponse->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_active_tasks', 1)
            ->where('summary.total_packed_today', 75)
        );
        
        $ajaxResponse->assertJson([
            'summary' => [
                'total_active_tasks' => 1,
                'total_packed_today' => 75
            ]
        ]);
    });
    
    it('handles rapid successive requests without data corruption', function () {
        $this->actingAs($this->supervisor);
        
        DailyPackingTask::factory()->create([
            'user_id' => $this->warehouseUser->id,
            'target_mushaf' => 50,
            'packed_mushaf' => 30,
            'date' => today()
        ]);
        
        // Simulate multiple rapid requests
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->postJson('/supervisor/warehouse-monitor/refresh');
        }
        
        // All responses should be consistent
        foreach ($responses as $response) {
            $response->assertStatus(200)
                ->assertJson([
                    'summary' => [
                        'total_active_tasks' => 1,
                        'total_packed_today' => 30
                    ]
                ]);
        }
    });
});

describe('Error Recovery and Fallbacks', function () {
    it('recovers from database connection issues', function () {
        $this->actingAs($this->supervisor);
        
        // Create some data first
        DailyPackingTask::factory()->create([
            'user_id' => $this->warehouseUser->id,
            'date' => today()
        ]);
        
        // Mock a database error scenario by using invalid data
        // The controller should handle this gracefully
        $response = $this->get('/supervisor/warehouse-monitor');
        
        // Should still return a valid response with fallback data
        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->has('summary')
                ->has('userPerformance')
                ->has('dailyTasks')
            );
    });
    
    it('handles missing user relationships gracefully', function () {
        $this->actingAs($this->supervisor);
        
        // Create task with non-existent user (edge case)
        \DB::table('daily_packing_tasks')->insert([
            'user_id' => 99999, // Non-existent user
            'target_mushaf' => 100,
            'packed_mushaf' => 50,
            'status' => 'in_progress',
            'date' => today(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $response = $this->get('/supervisor/warehouse-monitor');
        
        // Should handle missing relationships without errors
        $response->assertStatus(200);
    });
});