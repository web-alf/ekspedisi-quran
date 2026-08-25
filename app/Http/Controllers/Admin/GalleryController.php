<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Setting;
use App\Traits\HandlesImageUpload;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GalleryController extends Controller
{
    use HandlesImageUpload;

    public function __construct()
    {
        $this->authorizeResource(Gallery::class, 'gallery');
    }

    public function index()
    {
        $galleries = Gallery::ordered()->paginate(12);

        // Get gallery-related settings
        $gallerySettings = Setting::where('group', 'gallery')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return Inertia::render('Admin/Galleries/Index', [
            'galleries' => $galleries,
            'gallerySettings' => $gallerySettings,
        ]);
    }

    public function show(Gallery $gallery)
    {
        return Inertia::render('Admin/Galleries/Show', [
            'gallery' => $gallery,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Galleries/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'caption' => 'nullable|string|max:500',
            'image' => ['required', 'image', ...$this->validateImageUpload(['max:5120'])], // 5MB max
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Ensure empty caption becomes null
        if (empty($validated['caption'])) {
            $validated['caption'] = null;
        }

        // Upload dan optimasi gambar
        if ($request->hasFile('image')) {
            $optimizationConfig = $this->getOptimizationConfig('gallery');
            $imagePath = $this->uploadOptimizedImage(
                $request->file('image'),
                'galleries',
                $optimizationConfig
            );

            if ($imagePath) {
                $validated['image'] = $imagePath;
            } else {
                return back()->withErrors(['image' => 'Gagal mengupload gambar. Silakan coba lagi.']);
            }
        }

        Gallery::create($validated);

        return redirect()->route('admin.galleries.index')
            ->with('success', 'Gallery berhasil ditambahkan dengan optimasi gambar otomatis');
    }

    public function edit(Gallery $gallery)
    {
        return Inertia::render('Admin/Galleries/Edit', [
            'gallery' => $gallery,
        ]);
    }

    public function update(Request $request, Gallery $gallery)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'caption' => 'nullable|string|max:500',
            'image' => ['nullable', 'image', ...$this->validateImageUpload(['max:5120'])],
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Ensure empty caption becomes null
        if (empty($validated['caption'])) {
            $validated['caption'] = null;
        }

        // Upload gambar baru dengan optimasi jika ada
        if ($request->hasFile('image')) {
            $optimizationConfig = $this->getOptimizationConfig('gallery');
            $imagePath = $this->replaceOptimizedImage(
                $request->file('image'),
                $gallery->image,
                'galleries',
                $optimizationConfig
            );

            if ($imagePath) {
                $validated['image'] = $imagePath;
            } else {
                return back()->withErrors(['image' => 'Gagal mengupload gambar. Silakan coba lagi.']);
            }
        }

        $gallery->update($validated);

        return redirect()->route('admin.galleries.index')
            ->with('success', 'Gallery berhasil diperbarui');
    }

    public function destroy(Gallery $gallery)
    {
        // Hapus gambar dengan thumbnail
        $this->deleteImageWithThumbnail($gallery->image);

        $gallery->delete();

        return redirect()->route('admin.galleries.index')
            ->with('success', 'Gallery berhasil dihapus');
    }

    public function toggleStatus(Gallery $gallery)
    {
        $this->authorize('update', $gallery);
        $gallery->update(['is_active' => ! $gallery->is_active]);

        return back()->with('success', 'Status gallery berhasil diubah');
    }

    public function updateOrder(Request $request)
    {
        $this->authorize('update', Gallery::class);
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:galleries,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->items as $item) {
            Gallery::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return back()->with('success', 'Urutan gallery berhasil diperbarui');
    }

    public function updateSettings(Request $request)
    {
        $this->authorize('update', Gallery::class);
        $request->validate([
            'settings' => 'required|array',
            'settings.*.id' => 'required|exists:settings,id',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($request->settings as $settingData) {
            $setting = Setting::find($settingData['id']);
            if ($setting) {
                // Handle file uploads untuk image settings dengan optimasi
                if ($setting->type === 'image' && $request->hasFile("settings.{$setting->id}.file")) {
                    $file = $request->file("settings.{$setting->id}.file");
                    $optimizationConfig = $this->getOptimizationConfig('logo');

                    $imagePath = $this->replaceOptimizedImage(
                        $file,
                        $setting->value,
                        'settings',
                        $optimizationConfig
                    );

                    if ($imagePath) {
                        $setting->update(['value' => $imagePath]);
                    }
                } else {
                    // Regular text/boolean/etc updates
                    $setting->update(['value' => $settingData['value']]);
                }
            }
        }

        return back()->with('success', 'Settings galeri berhasil diperbarui');
    }
}
