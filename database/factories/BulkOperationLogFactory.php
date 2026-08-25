<?php

namespace Database\Factories;

use App\Models\BulkOperationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BulkOperationLog>
 */
class BulkOperationLogFactory extends Factory
{
    protected $model = BulkOperationLog::class;

    public function definition(): array
    {
        return [
            'operation_type' => $this->faker->randomElement(['bulk_update', 'bulk_delete', 'bulk_generate_qr', 'bulk_seal']),
            'box_code' => 'KB-'.$this->faker->date('Ymd').'-'.$this->faker->userName().'-A5-'.$this->faker->numberBetween(1, 99),
            'box_seal_code' => 'SEAL-'.$this->faker->uuid(),
            'user_id' => User::factory(),
            'items_count' => $this->faker->numberBetween(1, 50),
            'pengiriman_ids' => [],
            'old_status_id' => null,
            'new_status_id' => null,
            'old_address' => null,
            'new_address' => null,
            'notes' => $this->faker->sentence(),
            'qr_data' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'operation_timestamp' => now(),
            'processing_time_ms' => $this->faker->numberBetween(50, 5000),
        ];
    }
}