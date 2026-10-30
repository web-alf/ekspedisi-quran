<?php

namespace App\Http\Requests\Supervisor;

use App\Models\JenisQuran;
use App\Models\TargetPeriodeGudang;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Membuat atau mengubah target periode satu gudang. Hanya Manager Gudang (PJ) dan
 * super-admin — SPV menerima target, bukan menetapkannya.
 *
 * `tanggal_acuan` = tanggal mana pun di dalam periode; rentangnya (Senin–Minggu atau satu
 * bulan kalender) dihitung server. `jenis` = nilai pilihan tugas ("1:1", "5:-"), kosong =
 * semua jenis.
 */
class SimpanTargetPeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->bolehAturTargetPeriode();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $nilaiJenis = collect(JenisQuran::pilihanTugasPacking())->pluck('nilai')->all();

        return [
            'gudang_id' => ['required', 'integer', Rule::exists('gudang', 'id')->where('is_active', true)],
            'tipe' => ['required', Rule::in([TargetPeriodeGudang::TIPE_MINGGUAN, TargetPeriodeGudang::TIPE_BULANAN])],
            'tanggal_acuan' => ['required', 'date'],
            'jenis' => ['nullable', 'string', Rule::in($nilaiJenis)],
            'target' => ['required', 'integer', 'min:1', 'max:100000'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gudang_id.required' => 'Pilih gudang.',
            'gudang_id.exists' => 'Gudang tidak ditemukan atau tidak aktif.',
            'tipe.required' => 'Pilih periode mingguan atau bulanan.',
            'tipe.in' => 'Periode harus mingguan atau bulanan.',
            'tanggal_acuan.required' => 'Pilih minggu atau bulannya.',
            'jenis.in' => 'Jenis mushaf tidak ditemukan atau tidak aktif.',
            'target.required' => 'Isi jumlah target.',
            'target.min' => 'Target minimal 1 mushaf.',
            'target.max' => 'Target maksimal 100.000 mushaf.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                [, $selesai] = $this->rentang();

                if ($selesai->lt(today())) {
                    $validator->errors()->add('tanggal_acuan', 'Periode yang sudah lewat tidak bisa diberi target.');

                    return;
                }

                $sudahAda = TargetPeriodeGudang::query()
                    ->where('gudang_id', $this->integer('gudang_id'))
                    ->where('tipe', $this->input('tipe'))
                    ->whereDate('tanggal_mulai', $this->rentang()[0])
                    ->where('kunci_jenis', $this->kunciJenis())
                    ->when($this->route('targetPeriode'), fn ($q, TargetPeriodeGudang $t) => $q->whereKeyNot($t->id))
                    ->exists();

                if ($sudahAda) {
                    $validator->errors()->add('tanggal_acuan', 'Gudang ini sudah punya target untuk periode dan jenis yang sama. Ubah target yang ada.');
                }
            },
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function rentang(): array
    {
        return TargetPeriodeGudang::rentang($this->input('tipe'), $this->input('tanggal_acuan'));
    }

    public function kunciJenis(): string
    {
        return $this->filled('jenis') ? (string) $this->input('jenis') : TargetPeriodeGudang::KUNCI_SEMUA_JENIS;
    }

    /**
     * Kolom yang disimpan, sudah termasuk rentang dan jenis hasil penguraian.
     *
     * @return array<string, mixed>
     */
    public function dataTarget(): array
    {
        [$mulai, $selesai] = $this->rentang();
        $jenisId = null;
        $terjemahan = null;

        if ($this->filled('jenis')) {
            [$id, $varian] = explode(':', (string) $this->input('jenis'));
            $jenisId = (int) $id;
            $terjemahan = match ($varian) {
                '1' => true,
                '0' => false,
                default => null,
            };
        }

        return [
            'gudang_id' => $this->integer('gudang_id'),
            'tipe' => $this->input('tipe'),
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $selesai->toDateString(),
            'jenis_quran_id' => $jenisId,
            'terjemahan' => $terjemahan,
            'kunci_jenis' => $this->kunciJenis(),
            'target' => $this->integer('target'),
            'catatan' => $this->input('catatan'),
        ];
    }
}
