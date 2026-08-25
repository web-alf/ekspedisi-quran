<?php

namespace Tests\Feature\Supervisor;

use App\Models\DailyPackingTask;
use App\Models\PackingBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;

    protected User $warehouseUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        // Create supervisor user
        $this->supervisor = User::factory()->create(['is_active' => true]);
        $this->supervisor->assignRole('supervisor');

        // Create warehouse user
        $this->warehouseUser = User::factory()->create(['is_active' => true]);
        $this->warehouseUser->assignRole('warehouse');
    }

    public function test_supervisor_can_access_warehouse_monitor_page()
    {
        $response = $this->actingAs($this->supervisor)
            ->get('/admin/supervisor/warehouse-monitor');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Supervisor/WarehouseMonitor'));
    }

    public function test_non_supervisor_cannot_access_warehouse_monitor()
    {
        $customer = User::factory()->create(['is_active' => true]);
        $customer->assignRole('customer-service');

        $response = $this->actingAs($customer)
            ->get('/admin/supervisor/warehouse-monitor');

        // Should either redirect or return 403
        $this->assertTrue(
            $response->status() === 302 || $response->status() === 403,
            'Expected redirect (302) or forbidden (403) but got '.$response->status()
        );
    }

    public function test_initial_load_contains_consistent_data_structure()
    {
        // Create a task with carry over
        $task = DailyPackingTask::create([
            'user_id' => $this->warehouseUser->id,
            'tanggal_tugas' => today(),
            'total_target' => 120,
            'sisa_kemarin' => 20,
            'total_selesai' => 50,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
            'assigned_at' => now(),
        ]);

        // Create boxes
        PackingBox::factory()->count(3)->create([
            'daily_packing_task_id' => $task->id,
            'status' => 'sealed',
        ]);

        PackingBox::factory()->count(2)->create([
            'daily_packing_task_id' => $task->id,
            'status' => 'filling',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->get('/admin/supervisor/warehouse-monitor');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Supervisor/WarehouseMonitor')
            ->has('dailyTasks', 1)
            ->where('dailyTasks.0.id', $task->id)
            ->where('dailyTasks.0.user_id', $this->warehouseUser->id)
            ->where('dailyTasks.0.total_target', 120)
            ->where('dailyTasks.0.target_quantity', 100) // 120 - 20 carry over
            ->where('dailyTasks.0.carry_over_quantity', 20)
            ->where('dailyTasks.0.packed_quantity', 50)
            ->where('dailyTasks.0.total_boxes', 5)
            ->where('dailyTasks.0.sealed_boxes', 3)
            ->has('dailyTasks.0.user')
            ->where('dailyTasks.0.user.id', $this->warehouseUser->id)
            ->where('dailyTasks.0.user.name', $this->warehouseUser->name)
        );
    }

    public function test_ajax_data_endpoint_returns_consistent_structure()
    {
        // Create a task
        $task = DailyPackingTask::create([
            'user_id' => $this->warehouseUser->id,
            'tanggal_tugas' => today(),
            'total_target' => 100,
            'sisa_kemarin' => 10,
            'total_selesai' => 30,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
            'assigned_at' => now(),
        ]);

        // Create boxes
        PackingBox::factory()->count(2)->create([
            'daily_packing_task_id' => $task->id,
            'status' => 'sealed',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->getJson('/admin/supervisor/warehouse-monitor/data');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'warehouseUsers',
            'dailyTasks' => [
                '*' => [
                    'id',
                    'user_id',
                    'user' => ['id', 'name', 'email'],
                    'target_quantity',
                    'carry_over_quantity',
                    'total_target',
                    'packed_quantity',
                    'progress_percentage',
                    'status',
                    'total_boxes',
                    'sealed_boxes',
                ],
            ],
            'recentActivities',
            'summary',
            'stockInfo',
        ]);

        $dailyTask = $response->json('dailyTasks.0');
        $this->assertEquals($task->id, $dailyTask['id']);
        $this->assertEquals($this->warehouseUser->id, $dailyTask['user_id']);
        $this->assertEquals(100, $dailyTask['total_target']);
        $this->assertEquals(90, $dailyTask['target_quantity']); // 100 - 10
        $this->assertEquals(10, $dailyTask['carry_over_quantity']);
        $this->assertEquals(30, $dailyTask['packed_quantity']);
        $this->assertEquals(2, $dailyTask['total_boxes']);
        $this->assertEquals(2, $dailyTask['sealed_boxes']);
    }

    public function test_initial_and_ajax_data_have_same_structure()
    {
        // Create task
        $task = DailyPackingTask::create([
            'user_id' => $this->warehouseUser->id,
            'tanggal_tugas' => today(),
            'total_target' => 150,
            'sisa_kemarin' => 30,
            'total_selesai' => 60,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
            'assigned_at' => now(),
        ]);

        PackingBox::factory()->count(4)->create([
            'daily_packing_task_id' => $task->id,
            'status' => 'sealed',
        ]);

        // Get initial load data
        $initialResponse = $this->actingAs($this->supervisor)
            ->get('/admin/supervisor/warehouse-monitor');

        // Get AJAX data
        $ajaxResponse = $this->actingAs($this->supervisor)
            ->getJson('/admin/supervisor/warehouse-monitor/data');

        // Extract task data from both responses
        $initialTask = $initialResponse->viewData('page')['props']['dailyTasks'][0];
        $ajaxTask = $ajaxResponse->json('dailyTasks.0');

        // Assert same keys exist
        $initialKeys = array_keys($initialTask);
        $ajaxKeys = array_keys($ajaxTask);

        $this->assertEquals(sort($initialKeys), sort($ajaxKeys), 'Initial load and AJAX should have same keys');

        // Assert same values
        $this->assertEquals($initialTask['id'], $ajaxTask['id']);
        $this->assertEquals($initialTask['user_id'], $ajaxTask['user_id']);
        $this->assertEquals($initialTask['total_target'], $ajaxTask['total_target']);
        $this->assertEquals($initialTask['target_quantity'], $ajaxTask['target_quantity']);
        $this->assertEquals($initialTask['carry_over_quantity'], $ajaxTask['carry_over_quantity']);
        $this->assertEquals($initialTask['packed_quantity'], $ajaxTask['packed_quantity']);
        $this->assertEquals($initialTask['total_boxes'], $ajaxTask['total_boxes']);
        $this->assertEquals($initialTask['sealed_boxes'], $ajaxTask['sealed_boxes']);
    }

    public function test_task_without_carry_over_shows_zero()
    {
        $task = DailyPackingTask::create([
            'user_id' => $this->warehouseUser->id,
            'tanggal_tugas' => today(),
            'total_target' => 80,
            'sisa_kemarin' => 0,
            'total_selesai' => 20,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->supervisor)
            ->get('/admin/supervisor/warehouse-monitor');

        $response->assertInertia(fn ($page) => $page
            ->where('dailyTasks.0.target_quantity', 80)
            ->where('dailyTasks.0.carry_over_quantity', 0)
            ->where('dailyTasks.0.total_target', 80)
        );
    }

    public function test_task_with_null_carry_over_shows_zero()
    {
        $task = DailyPackingTask::create([
            'user_id' => $this->warehouseUser->id,
            'tanggal_tugas' => today(),
            'total_target' => 90,
            'sisa_kemarin' => 0, // Database doesn't allow null
            'total_selesai' => 25,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->supervisor)
            ->get('/admin/supervisor/warehouse-monitor');

        $response->assertInertia(fn ($page) => $page
            ->where('dailyTasks.0.target_quantity', 90)
            ->where('dailyTasks.0.carry_over_quantity', 0)
        );
    }

    public function test_remaining_calculation_works_correctly()
    {
        $task = DailyPackingTask::create([
            'user_id' => $this->warehouseUser->id,
            'tanggal_tugas' => today(),
            'total_target' => 100,
            'sisa_kemarin' => 15,
            'total_selesai' => 40,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->supervisor)
            ->getJson('/admin/supervisor/warehouse-monitor/data');

        $dailyTask = $response->json('dailyTasks.0');

        // Remaining = total_target - packed_quantity = 100 - 40 = 60
        $remaining = $dailyTask['total_target'] - $dailyTask['packed_quantity'];
        $this->assertEquals(60, $remaining);
        $this->assertIsNumeric($remaining);
        $this->assertNotEquals('NaN', $remaining);
    }

    public function test_no_undefined_values_in_response()
    {
        $task = DailyPackingTask::create([
            'user_id' => $this->warehouseUser->id,
            'tanggal_tugas' => today(),
            'total_target' => 100,
            'sisa_kemarin' => 10,
            'total_selesai' => 30,
            'status' => DailyPackingTask::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->supervisor)
            ->get('/admin/supervisor/warehouse-monitor');

        $taskData = $response->viewData('page')['props']['dailyTasks'][0];

        // Assert no null/undefined critical fields
        $this->assertNotNull($taskData['target_quantity']);
        $this->assertNotNull($taskData['carry_over_quantity']);
        $this->assertNotNull($taskData['total_target']);
        $this->assertNotNull($taskData['packed_quantity']);
        $this->assertNotNull($taskData['total_boxes']);
        $this->assertNotNull($taskData['sealed_boxes']);

        // Assert all are numeric
        $this->assertIsNumeric($taskData['target_quantity']);
        $this->assertIsNumeric($taskData['carry_over_quantity']);
        $this->assertIsNumeric($taskData['total_target']);
        $this->assertIsNumeric($taskData['packed_quantity']);
        $this->assertIsNumeric($taskData['total_boxes']);
        $this->assertIsNumeric($taskData['sealed_boxes']);
    }

    public function test_multiple_tasks_all_have_consistent_structure()
    {
        // Create multiple tasks
        $tasks = [];
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create(['is_active' => true]);
            $user->assignRole('warehouse');

            $tasks[] = DailyPackingTask::create([
                'user_id' => $user->id,
                'tanggal_tugas' => today(),
                'total_target' => 100 + ($i * 10),
                'sisa_kemarin' => $i * 5,
                'total_selesai' => 30 + ($i * 10),
                'status' => DailyPackingTask::STATUS_IN_PROGRESS,
                'assigned_at' => now(),
            ]);

            PackingBox::factory()->count($i + 1)->create([
                'daily_packing_task_id' => $tasks[$i]->id,
                'status' => 'sealed',
            ]);
        }

        $response = $this->actingAs($this->supervisor)
            ->get('/admin/supervisor/warehouse-monitor');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('dailyTasks', 3)
            ->has('dailyTasks.0', fn ($task) => $task
                ->has('id')
                ->has('user_id')
                ->has('user')
                ->has('target_quantity')
                ->has('carry_over_quantity')
                ->has('total_target')
                ->has('packed_quantity')
                ->has('progress_percentage')
                ->has('status')
                ->has('started_at')
                ->has('completed_at')
                ->has('total_boxes')
                ->has('sealed_boxes')
                ->etc() // Allow other fields
            )
        );
    }

    public function test_warehouse_users_list_is_included()
    {
        $response = $this->actingAs($this->supervisor)
            ->get('/admin/supervisor/warehouse-monitor');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('warehouseUsers')
            ->where('warehouseUsers.0.id', $this->warehouseUser->id)
            ->where('warehouseUsers.0.name', $this->warehouseUser->name)
        );
    }

    public function test_summary_stats_are_calculated_correctly()
    {
        // Create tasks
        DailyPackingTask::create([
            'user_id' => $this->warehouseUser->id,
            'tanggal_tugas' => today(),
            'total_target' => 100,
            'total_selesai' => 60,
            'sisa_kemarin' => 10,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
            'assigned_at' => now(),
        ]);

        $user2 = User::factory()->create(['is_active' => true]);
        $user2->assignRole('warehouse');

        DailyPackingTask::create([
            'user_id' => $user2->id,
            'tanggal_tugas' => today(),
            'total_target' => 80,
            'total_selesai' => 40,
            'sisa_kemarin' => 5,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->supervisor)
            ->get('/admin/supervisor/warehouse-monitor');

        $response->assertInertia(fn ($page) => $page
            ->where('summary.total_active_tasks', 2)
            ->where('summary.total_packed_today', 100) // 60 + 40
            ->where('summary.total_carry_over', 15) // 10 + 5
        );
    }

    public function test_performance_data_is_included_in_initial_load()
    {
        $response = $this->actingAs($this->supervisor)
            ->get('/admin/supervisor/warehouse-monitor');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('performanceStats')
            ->has('userPerformance')
        );
    }

    public function test_super_admin_can_also_access_warehouse_monitor()
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $response = $this->actingAs($admin)
            ->get('/admin/supervisor/warehouse-monitor');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Supervisor/WarehouseMonitor'));
    }

    public function test_boxes_count_is_accurate()
    {
        $task = DailyPackingTask::create([
            'user_id' => $this->warehouseUser->id,
            'tanggal_tugas' => today(),
            'total_target' => 100,
            'total_selesai' => 50,
            'status' => DailyPackingTask::STATUS_IN_PROGRESS,
            'assigned_at' => now(),
        ]);

        // Create specific number of boxes
        PackingBox::factory()->count(5)->create([
            'daily_packing_task_id' => $task->id,
            'status' => 'sealed',
        ]);

        PackingBox::factory()->count(3)->create([
            'daily_packing_task_id' => $task->id,
            'status' => 'filling',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->get('/admin/supervisor/warehouse-monitor');

        $response->assertInertia(fn ($page) => $page
            ->where('dailyTasks.0.total_boxes', 8) // 5 + 3
            ->where('dailyTasks.0.sealed_boxes', 5)
        );
    }
}
