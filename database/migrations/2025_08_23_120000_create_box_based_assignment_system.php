<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create daily_packing_task_targets table (skip if exists)
        if (!Schema::hasTable('daily_packing_task_targets')) {
            Schema::create('daily_packing_task_targets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('daily_packing_task_id')->constrained()->onDelete('cascade');
                $table->foreignId('jenis_quran_id')->constrained('jenis_quran')->onDelete('cascade');
                $table->integer('target_boxes')->default(0); // Number of boxes assigned
                $table->integer('target_quantity'); // Total items expected
                $table->integer('completed_quantity')->default(0); // Items completed
                $table->integer('box_capacity'); // Capacity per box for this jenis
                $table->boolean('is_shared_box')->default(false);
                $table->string('shared_box_code')->nullable(); // Reference to shared box
                $table->json('assignment_metadata')->nullable(); // Extra assignment info
                $table->timestamps();

                $table->unique(['daily_packing_task_id', 'jenis_quran_id'], 'unique_task_jenis');
                $table->index(['daily_packing_task_id', 'is_shared_box'], 'idx_task_shared');
            });
        }

        // Create shared_box_assignments table
        Schema::create('shared_box_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packing_box_id')->constrained()->onDelete('cascade');
            $table->foreignId('daily_packing_task_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('allocated_items'); // Items allocated to this user
            $table->integer('completed_items')->default(0); // Items completed by this user
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('progress_metadata')->nullable(); // Track detailed progress
            $table->timestamps();

            $table->unique(['packing_box_id', 'user_id'], 'unique_box_user');
            $table->index(['user_id', 'completed_at']);
            $table->index(['packing_box_id', 'completed_items']);
        });

        // Enhance existing packing_boxes table
        Schema::table('packing_boxes', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('jenis_quran_id')->constrained('users')->onDelete('set null');
            $table->enum('assignment_type', ['individual', 'shared'])->default('individual')->after('assigned_user_id');
            $table->boolean('is_shared_box')->default(false)->after('assignment_type');
            $table->foreignId('created_by_supervisor')->nullable()->after('is_shared_box')->constrained('users')->onDelete('set null');
            $table->date('target_completion_date')->nullable()->after('created_by_supervisor');
            $table->json('box_metadata')->nullable()->after('target_completion_date'); // Extra box info

            $table->index(['assigned_user_id', 'status'], 'idx_user_status');
            $table->index(['assignment_type', 'is_shared_box'], 'idx_type_shared');
            $table->index(['target_completion_date', 'status'], 'idx_date_status');
        });

        // Enhance existing daily_packing_tasks table
        Schema::table('daily_packing_tasks', function (Blueprint $table) {
            $table->enum('assignment_method', ['flat', 'target_only', 'mixed_box_based'])->default('target_only')->after('status');
            $table->json('box_breakdown')->nullable()->after('assignment_method'); // Store original box assignment
            $table->boolean('has_shared_boxes')->default(false)->after('box_breakdown');
            $table->integer('total_boxes_assigned')->default(0)->after('has_shared_boxes');
            $table->integer('total_boxes_completed')->default(0)->after('total_boxes_assigned');
            $table->timestamp('first_scan_at')->nullable()->after('started_at');

            $table->index(['assignment_method', 'has_shared_boxes'], 'idx_method_shared');
            $table->index(['tanggal_tugas', 'assignment_method'], 'idx_date_method');
        });

        // Insert default assignment method into settings table if exists
        if (Schema::hasTable('settings')) {
            DB::table('settings')->insertOrIgnore([
                'key' => 'box_assignment_enabled',
                'value' => 'false',
                'description' => 'Enable box-based assignment system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign key constraints first
        Schema::table('daily_packing_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'assignment_method',
                'box_breakdown',
                'has_shared_boxes',
                'total_boxes_assigned',
                'total_boxes_completed',
                'first_scan_at',
            ]);
        });

        Schema::table('packing_boxes', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
            $table->dropForeign(['created_by_supervisor']);
            $table->dropColumn([
                'assigned_user_id',
                'assignment_type',
                'is_shared_box',
                'created_by_supervisor',
                'target_completion_date',
                'box_metadata',
            ]);
        });

        Schema::dropIfExists('shared_box_assignments');
        Schema::dropIfExists('daily_packing_task_targets');

        // Remove the setting if it exists
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'box_assignment_enabled')->delete();
        }
    }
};
