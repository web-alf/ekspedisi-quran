<?php

namespace App\Rules;

use App\Models\WakafItem;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Doa wakif harus muat di label resi 100×150 mm (keputusan pemilik 28/10/2026).
 *
 * Doa lama yang sudah tersimpan lebih panjang tetap diterima SELAMA tidak diubah: form edit
 * mengirim ulang doa yang ada setiap kali nama wakif disimpan, dan menolaknya membuat donatur
 * lama tidak bisa diedit sama sekali. Doa lama itu dipotong di label (WakafItem::doaUntukLabel).
 */
class DoaMuatResi implements ValidationRule
{
    public function __construct(private ?string $nilaiTersimpan = null) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen(trim($value)) <= WakafItem::DOA_MAKS_KARAKTER) {
            return;
        }

        if ($this->nilaiTersimpan !== null && trim($value) === trim($this->nilaiTersimpan)) {
            return;
        }

        $fail('Doa maksimal '.WakafItem::DOA_MAKS_KARAKTER.' karakter agar muat di label resi.');
    }
}
