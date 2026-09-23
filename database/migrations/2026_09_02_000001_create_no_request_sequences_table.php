<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Race-condition safe sequence table untuk nomor permintaan mushaf (REQ-YYYY-XXXXX).
     *
     * Menggantikan generateNoRequest() yang melakukan check-then-insert non-atomic,
     * yang bisa duplikat (dan memicu QueryException) saat dua request publik masuk paralel.
     */
    public function up(): void
    {
        Schema::create('no_request_sequences', function (Blueprint $table) {
            $table->id();
            $table->year('year')->unique()->comment('Tahun untuk sequence (YYYY)');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        // Seed sequence dari data no_request yang sudah ada agar nomor tidak dimulai dari 1 lagi.
        // Kompatibel SQLite (test) dan MySQL (produksi): strftime untuk SQLite, YEAR untuk MySQL.
        $yearExpr = DB::getDriverName() === 'sqlite' ? "strftime('%Y', created_at)" : 'YEAR(created_at)';
        $years = DB::table('mushaf_requests')
            ->selectRaw("{$yearExpr} as year, COUNT(*) as total")
            ->groupBy(DB::raw($yearExpr))
            ->get();

        foreach ($years as $row) {
            DB::table('no_request_sequences')->insertOrIgnore([
                'year' => $row->year,
                'last_number' => $row->total,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('no_request_sequences');
    }
};
