<?php

namespace Database\Factories;

use App\Models\MushafRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MushafRequest>
 */
class MushafRequestFactory extends Factory
{
    protected $model = MushafRequest::class;

    public function definition(): array
    {
        $a5 = $this->faker->numberBetween(0, 50);
        $a6 = $this->faker->numberBetween(0, 50);
        $iqra = $this->faker->numberBetween(0, 50);

        return [
            'no_request' => 'REQ-' . date('Y') . '-' . str_pad((string) $this->faker->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'nama_lembaga' => $this->faker->company(),
            'kategori_lembaga' => $this->faker->randomElement([
                'Pondok Pesantren', 'Rumah Tahfidz/Rumah Qur\'an', 'TPQ/TPA/Madin', 'Sekolah/Madrasah',
                'Masjid/Mushola/Majelis Taklim/Jamaah Masjid', 'Masyarakat/Jamaah Alfatihah', 'Yayasan', 'Lembaga Lainnya',
            ]),
            'alamat_lengkap' => $this->faker->address(),
            'provinsi' => $this->faker->randomElement(['Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'DKI Jakarta', 'Aceh']),
            'kota_kabupaten' => $this->faker->city(),
            'kode_pos' => $this->faker->postcode(),
            'latitude' => $this->faker->randomFloat(6, -10.0, 5.0),
            'longitude' => $this->faker->randomFloat(6, 95.0, 141.0),
            'nama_pengurus_1' => $this->faker->name(),
            'jabatan_pengurus_1' => 'Pengurus',
            'whatsapp_pengurus_1' => $this->faker->e164PhoneNumber(),
            'nama_pengurus_2' => $this->faker->name(),
            'jabatan_pengurus_2' => 'Pengurus',
            'whatsapp_pengurus_2' => $this->faker->e164PhoneNumber(),
            'urgensi_request' => $this->faker->randomElement(['tinggi', 'sedang', 'rendah']),
            'sumber_info' => $this->faker->randomElement(['Instagram', 'Facebook', 'WhatsApp', 'Tiktok', 'Website']),
            // jumlah_mushaf is total A5 + A6 per migration comments
            'jumlah_mushaf' => $a5 + $a6,
            'jumlah_mushaf_a5' => $a5,
            'jumlah_mushaf_a6' => $a6,
            'jumlah_iqra' => $iqra,
            'jenis_mushaf_diminta' => ['A5', 'A6', 'IQRA'],
            'status' => $this->faker->randomElement(['pending', 'approved', 'processed', 'completed']),
        ];
    }

    public function completed(): self
    {
        return $this->state(fn () => [
            'status' => 'completed',
        ]);
    }

    public function withCoordinates(): self
    {
        return $this->state(fn () => [
            'latitude' => $this->faker->randomFloat(6, -10.0, 5.0),
            'longitude' => $this->faker->randomFloat(6, 95.0, 141.0),
        ]);
    }
}

