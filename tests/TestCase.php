<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Fake storage untuk testing
        Storage::fake('local');
        
        // Create basic test data instead of using seeders to avoid dependency issues
        $this->createBasicTestData();
    }
    
    /**
     * Create basic test data for testing
     */
    protected function createBasicTestData()
    {
        try {
            // Create basic status pengiriman
            if (\Schema::hasTable('status_pengiriman')) {
                \DB::table('status_pengiriman')->insert([
                    'nama' => 'Pending',
                    'slug' => 'pending',
                    'deskripsi' => 'Status pending',
                    'warna' => 'gray',
                    'urutan' => 1,
                    'is_active' => true,
                    'is_final' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                \DB::table('status_pengiriman')->insert([
                    'nama' => 'Processing',
                    'slug' => 'processing',
                    'deskripsi' => 'Status processing',
                    'warna' => 'blue',
                    'urutan' => 2,
                    'is_active' => true,
                    'is_final' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                \DB::table('status_pengiriman')->insert([
                    'nama' => 'Delivered',
                    'slug' => 'delivered',
                    'deskripsi' => 'Status delivered',
                    'warna' => 'green',
                    'urutan' => 3,
                    'is_active' => true,
                    'is_final' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // Create basic jenis quran
            if (\Schema::hasTable('jenis_quran')) {
                \DB::table('jenis_quran')->insert([
                    'kode_jenis' => 'A5',
                    'nama_jenis' => 'Al-Quran A5',
                    'deskripsi' => 'Al-Quran ukuran A5',
                    'harga' => 50000,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                \DB::table('jenis_quran')->insert([
                    'kode_jenis' => 'A6',
                    'nama_jenis' => 'Al-Quran A6',
                    'deskripsi' => 'Al-Quran ukuran A6',
                    'harga' => 35000,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                \DB::table('jenis_quran')->insert([
                    'kode_jenis' => 'IQRA',
                    'nama_jenis' => 'IQRA',
                    'deskripsi' => 'Buku IQRA',
                    'harga' => 25000,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Ignore if tables don't exist or other errors
        }
    }

    /**
     * Create authenticated user for testing
     */
    protected function createUser($role = 'admin', $attributes = [])
    {
        $user = \App\Models\User::factory()->create($attributes);
        
        // Assign role if exists
        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            try {
                $user->assignRole($role);
            } catch (\Exception $e) {
                // Role doesn't exist, skip
            }
        }
        
        return $user;
    }

    /**
     * Act as authenticated user
     */
    protected function actingAsUser($role = 'admin', $attributes = [])
    {
        $user = $this->createUser($role, $attributes);
        return $this->actingAs($user);
    }
}
