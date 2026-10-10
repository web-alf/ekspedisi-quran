<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PerbaruiPengaturanWhatsAppRequest;
use App\Http\Requests\Admin\PerbaruiTemplateWhatsAppRequest;
use App\Http\Requests\Admin\UjiKirimWhatsAppRequest;
use App\Jobs\WhatsApp\KirimWhatsAppJob;
use App\Models\WhatsAppNotification;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\StarSenderClient;
use App\Services\WhatsApp\WhatsAppTemplateService;
use App\Support\PerPage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppController extends Controller
{
    public function __construct(
        private readonly WhatsAppTemplateService $templateService,
    ) {}

    public function index(): Response
    {
        $setting = WhatsAppSetting::active();

        return Inertia::render('Admin/WhatsApp/Index', [
            'pengaturan' => $setting ? [
                'id' => $setting->id,
                'provider' => $setting->provider,
                'base_url' => $setting->base_url,
                'sender_number' => $setting->sender_number,
                'is_active' => (bool) $setting->is_active,
                'delay_seconds' => (int) $setting->delay_seconds,
                'max_per_minute' => (int) $setting->max_per_minute,
                'quiet_hours_start' => $setting->quiet_hours_start,
                'quiet_hours_end' => $setting->quiet_hours_end,
                // Hanya STATUS terisi yang dikirim ke frontend. Nilai kuncinya
                // tidak pernah meninggalkan server.
                'kunci_terisi' => filled($setting->api_key),
            ] : null,
            'templates' => WhatsAppTemplate::query()->orderBy('id')->get()->map(fn (WhatsAppTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'title' => $t->title,
                'category' => $t->category,
                'content' => $t->content,
                'variables' => $t->variables ?? [],
                'is_active' => (bool) $t->is_active,
                'description' => $t->description,
                'contoh' => $this->templateService->susun($t, $this->templateService->dataContoh()),
            ])->values(),
            'notifikasi' => WhatsAppNotification::query()
                ->with(['donatur:id,nama_donatur', 'pengiriman:id,no_resi'])
                ->orderByDesc('id')
                ->paginate(PerPage::resolve(request()))
                ->through(fn (WhatsAppNotification $n) => [
                    'id' => $n->id,
                    'event_key' => $n->event_key,
                    'recipient' => $n->recipient,
                    'recipient_name' => $n->recipient_name ?? $n->donatur?->nama_donatur,
                    'no_resi' => $n->pengiriman?->no_resi,
                    'body' => $n->body,
                    'status' => $n->status,
                    'attempts' => (int) $n->attempts,
                    'error_message' => $n->error_message,
                    'sent_at' => $n->sent_at?->format('d/m/Y H:i'),
                    'created_at' => $n->created_at?->format('d/m/Y H:i'),
                ]),
            'ringkasan' => [
                'menunggu' => WhatsAppNotification::query()->menunggu()->count(),
                'terkirim' => WhatsAppNotification::query()->terkirim()->count(),
                'gagal' => WhatsAppNotification::query()->gagal()->count(),
            ],
            'perPage' => PerPage::resolve(request()),
            'perPageOptions' => PerPage::OPTIONS,
        ]);
    }

    public function perbaruiPengaturan(PerbaruiPengaturanWhatsAppRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $setting = WhatsAppSetting::active() ?? new WhatsAppSetting;

        $setting->provider = 'starsender';
        $setting->base_url = $data['base_url'];
        $setting->sender_number = $data['sender_number'] ?? null;
        $setting->is_active = (bool) ($data['is_active'] ?? false);
        $setting->delay_seconds = $data['delay_seconds'];
        $setting->max_per_minute = $data['max_per_minute'];
        $setting->quiet_hours_start = $data['quiet_hours_start'] ?? null;
        $setting->quiet_hours_end = $data['quiet_hours_end'] ?? null;

        // Kolom kosong = jangan ubah. Halaman tidak pernah menampilkan nilainya,
        // jadi menganggapnya "kosongkan" akan menghapus kunci setiap kali ada
        // yang menyunting pengaturan lain.
        if (filled($data['api_key'] ?? null)) {
            $setting->api_key = $data['api_key'];
        }

        $setting->save();

        return back()->with('success', 'Pengaturan notifikasi WhatsApp disimpan.');
    }

    public function ujiKirim(UjiKirimWhatsAppRequest $request): RedirectResponse
    {
        $setting = WhatsAppSetting::active();

        if (! $setting?->siapKirim()) {
            return back()->with('error', 'Isi kunci API StarSender dan aktifkan notifikasi sebelum menguji.');
        }

        $nomor = (string) $request->validated('nomor');
        $pesan = $request->validated('pesan')
            ?: 'Uji sambungan notifikasi Ekspedisi Quran. Kalau pesan ini sampai, sambungannya sudah benar.';

        $klien = StarSenderClient::dari($setting);

        $cek = $klien->cekNomor($nomor);

        if ($cek['sukses'] && ($cek['mentah']['data']['status'] ?? false) !== true) {
            return back()->with('error', 'Nomor itu tidak terdaftar WhatsApp menurut StarSender, jadi pesan uji tidak dikirim.');
        }

        $hasil = $klien->kirimTeks($nomor, $pesan);

        if (! $hasil['sukses']) {
            return back()->with('error', 'Gagal mengirim pesan uji: '.$hasil['pesan']);
        }

        return back()->with('success', 'Pesan uji berhasil dikirim. Periksa WhatsApp di nomor tujuan.');
    }

    public function perbaruiTemplate(PerbaruiTemplateWhatsAppRequest $request, WhatsAppTemplate $template): RedirectResponse
    {
        $template->update([
            'title' => $request->validated('title'),
            'content' => $request->validated('content'),
            'is_active' => (bool) ($request->validated('is_active') ?? false),
        ]);

        return back()->with('success', 'Template diperbarui.');
    }

    public function kirimUlang(WhatsAppNotification $notification): RedirectResponse
    {
        if (! auth()->user()->can(PermissionEnum::WHATSAPP_NOTIFICATIONS_SEND->value)) {
            abort(403);
        }

        if ($notification->sudahSelesai()) {
            return back()->with('error', 'Pesan ini sudah terkirim.');
        }

        $notification->update([
            'status' => 'menunggu',
            'error_message' => null,
        ]);

        KirimWhatsAppJob::dispatch($notification->id);

        return back()->with('success', 'Pesan dimasukkan kembali ke antrean.');
    }
}
