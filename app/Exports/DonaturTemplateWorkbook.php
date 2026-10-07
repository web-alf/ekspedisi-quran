<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Berkas templat impor donatur: lembar data + lembar petunjuk dalam satu berkas.
 *
 * Lembar data sengaja diletakkan pertama supaya berkasnya terbuka langsung di tempat
 * tim entry harus mengisi.
 */
class DonaturTemplateWorkbook implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new DonaturTemplateExport,
            new DonaturTemplatePetunjukExport,
        ];
    }
}
