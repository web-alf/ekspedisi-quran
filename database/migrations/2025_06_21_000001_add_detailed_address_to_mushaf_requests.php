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
        Schema::table('mushaf_requests', function (Blueprint $table) {
            // Tambah fields alamat yang detail
            $table->string('provinsi')->nullable()->after('alamat_lengkap');
            $table->string('provinsi_id')->nullable()->after('provinsi')->comment('ID provinsi dari API Wilayah Indonesia');
            $table->string('kota_kabupaten')->nullable()->after('provinsi_id');
            $table->string('kota_kabupaten_id')->nullable()->after('kota_kabupaten')->comment('ID kota/kabupaten dari API Wilayah Indonesia');
            $table->string('kecamatan')->nullable()->after('kota_kabupaten_id');
            $table->string('kecamatan_id')->nullable()->after('kecamatan')->comment('ID kecamatan dari API Wilayah Indonesia');
            $table->string('kelurahan_desa')->nullable()->after('kecamatan_id');
            $table->string('kelurahan_desa_id')->nullable()->after('kelurahan_desa')->comment('ID kelurahan/desa dari API Wilayah Indonesia');
            $table->string('kode_pos')->nullable()->after('kelurahan_desa_id');
            $table->text('alamat_detail')->nullable()->after('kode_pos')->comment('Alamat detail: Jalan, RT/RW, Gang, No Rumah, dll');
            
            // Koordinat untuk mapping
            $table->decimal('latitude', 10, 8)->nullable()->after('alamat_detail')->comment('Koordinat latitude untuk mapping');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude')->comment('Koordinat longitude untuk mapping');
            
            // Tambah index untuk performa query geografis
            $table->index(['provinsi_id', 'kota_kabupaten_id']);
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mushaf_requests', function (Blueprint $table) {
            $table->dropIndex(['provinsi_id', 'kota_kabupaten_id']);
            $table->dropIndex(['latitude', 'longitude']);
            
            $table->dropColumn([
                'provinsi',
                'provinsi_id',
                'kota_kabupaten',
                'kota_kabupaten_id', 
                'kecamatan',
                'kecamatan_id',
                'kelurahan_desa',
                'kelurahan_desa_id',
                'kode_pos',
                'alamat_detail',
                'latitude',
                'longitude'
            ]);
        });
    }
};
