<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PackingBox;
use App\Models\DailyPackingTask;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;

class WarehousePolicy
{
    use HandlesAuthorization;

    /**
     * Check if user can access warehouse features
     */
    public function accessWarehouse(User $user): bool
    {
        $canAccess = $user->can('warehouse.dashboard')
            || $user->can('warehouse.packing.view')
            || $user->can('warehouse.tasks.view')
            || $user->can('warehouse.boxes.view')
            || $user->can('supervisor.warehouse.monitor');
        
        // Log access attempts for security audit
        if (!$canAccess) {
            Log::warning('Unauthorized warehouse access attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $user->role,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);
        }
        
        return $canAccess;
    }

    /**
     * Check if user can view packing operations
     */
    public function viewPacking(User $user): bool
    {
        return $this->accessWarehouse($user);
    }

    /**
     * Check if user can scan and pack items
     */
    public function scanItems(User $user): bool
    {
        $canScan = $user->can('warehouse.packing.scan') || $user->can('warehouse.box.update_any');

        // Additional check: operational users must have active task
        if ($canScan && $user->can('warehouse.packing.scan')) {
            $todayTask = $user->getTodayTask();
            $canScan = $todayTask && !$todayTask->is_completed;
            
            if (!$canScan) {
                Log::info('Warehouse user attempted to scan without active task', [
                    'user_id' => $user->id,
                    'has_task' => $todayTask ? true : false,
                    'task_completed' => $todayTask?->is_completed
                ]);
            }
        }
        
        return $canScan;
    }

    /**
     * Check if user can access specific packing box
     */
    public function accessBox(User $user, PackingBox $box): bool
    {
        // Explicit permission can override box ownership
        if ($user->can('warehouse.box.update_any') || $user->can('supervisor.warehouse.monitor')) {
            return true;
        }

        // Warehouse users can only access their own boxes
        if ($user->can('warehouse.boxes.view')) {
            $userTask = $user->getTodayTask();
            $canAccess = $userTask && $userTask->packingBoxes()->where('id', $box->id)->exists();
            
            if (!$canAccess) {
                Log::warning('Unauthorized box access attempt', [
                    'user_id' => $user->id,
                    'box_id' => $box->id,
                    'box_code' => $box->kode_kerdus,
                    'user_has_task' => $userTask ? true : false
                ]);
            }
            
            return $canAccess;
        }
        
        return false;
    }

    /**
     * Check if user can update box status
     */
    public function updateBoxStatus(User $user, PackingBox $box): bool
    {
        // Global override for users granted explicit permission
        if ($user->can('warehouse.box.update_any')) {
            return true;
        }

        // Must be able to access the box first
        if (!$this->accessBox($user, $box)) {
            return false;
        }
        
        // Additional business rule: can't update sealed boxes (unless supervisor)
        $isSealed = (bool) ($box->is_sealed ?? false);
        if (!$isSealed && isset($box->status)) {
            $isSealed = $box->status === 'sealed';
        }
        if ($isSealed && !$user->can('warehouse.box.update_sealed') && !$user->can('warehouse.box.update_any') && !$user->can('supervisor.warehouse.monitor')) {
            Log::warning('Attempt to update sealed box status', [
                'user_id' => $user->id,
                'box_id' => $box->id,
                'box_code' => $box->kode_kerdus,
                'box_status' => $box->status
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Check if user can seal boxes
     */
    public function sealBox(User $user, PackingBox $box): bool
    {
        return $this->accessBox($user, $box);
    }

    /**
     * Check if user can view warehouse scanner
     */
    public function viewScanner(User $user): bool
    {
        return $this->accessWarehouse($user) || $user->can('warehouse.qr.scan') || $user->can('qr.scan');
    }

    /**
     * Check if user can upload documentation files
     */
    public function uploadDocumentation(User $user): bool
    {
        $canUpload = $user->can('warehouse.dashboard')
            || $user->can('warehouse.packing.view')
            || $user->can('warehouse.qr.scan')
            || $user->can('warehouse.box.update_any')
            || $user->can('supervisor.warehouse.monitor');
        
        // Log all upload attempts for security audit
        Log::info('Documentation upload authorization check', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'authorized' => $canUpload,
            'ip_address' => request()->ip()
        ]);
        
        return $canUpload;
    }

    /**
     * Check if user can view their own packing history
     */
    public function viewOwnHistory(User $user): bool
    {
        return $user->can('warehouse.packing.view') || $user->can('warehouse.dashboard') || $user->can('supervisor.warehouse.monitor');
    }

    /**
     * Check if user can view all users' packing history
     */
    public function viewAllHistory(User $user): bool
    {
        return $user->can('supervisor.warehouse.monitor') || $user->can('supervisor.performance.view') || $user->can('warehouse.box.update_any');
    }

    /**
     * Check if user can assign tasks to others
     */
    public function assignTasks(User $user): bool
    {
        return $user->can('supervisor.warehouse.assign');
    }
}
