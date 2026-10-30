<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supervisor\SimpanTargetPeriodeRequest;
use App\Models\TargetPeriodeGudang;
use App\Services\TargetPeriodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Target mingguan/bulanan per gudang dari Manager Gudang (30/10/2026). Dipanggil modal
 * "Target Periode" di Monitor & Penugasan lewat axios, seperti Assign Target.
 */
class TargetPeriodeController extends Controller
{
    public function __construct(private TargetPeriodeService $targetPeriode) {}

    public function store(SimpanTargetPeriodeRequest $request): JsonResponse
    {
        $target = TargetPeriodeGudang::create([
            ...$request->dataTarget(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Target {$target->target} mushaf untuk {$target->gudang->nama} ({$target->labelPeriode()}) tersimpan.",
            'data' => $this->targetPeriode->ringkas($target),
        ], 201);
    }

    public function update(SimpanTargetPeriodeRequest $request, TargetPeriodeGudang $targetPeriode): JsonResponse
    {
        $targetPeriode->update($request->dataTarget());

        return response()->json([
            'success' => true,
            'message' => "Target {$targetPeriode->gudang->nama} ({$targetPeriode->labelPeriode()}) diperbarui.",
            'data' => $this->targetPeriode->ringkas($targetPeriode->fresh()),
        ]);
    }

    public function destroy(Request $request, TargetPeriodeGudang $targetPeriode): JsonResponse
    {
        abort_unless((bool) $request->user()?->bolehAturTargetPeriode(), 403, 'Hanya Manager Gudang yang bisa menghapus target periode.');

        $targetPeriode->delete();

        return response()->json(['success' => true, 'message' => 'Target periode dihapus.']);
    }
}
