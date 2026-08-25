<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TestimonialController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Testimonial::class, 'testimonial');
    }

    public function index()
    {
        $testimonials = Testimonial::ordered()->paginate(10);

        // Get Testimonial-related settings
        $testimonialSettings = Setting::where('group', 'testimonial')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return Inertia::render('Admin/Testimonials/Index', [
            'testimonials' => $testimonials,
            'testimonialSettings' => $testimonialSettings,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Testimonials/Create', [
            'availableDefaultAvatars' => Testimonial::getAvailableDefaultAvatars(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'quote' => 'required|string',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('testimonials', 'public');
        }

        Testimonial::create($validated);

        return redirect()->route('admin.testimonials.index')
            ->with('success', 'Testimonial berhasil ditambahkan');
    }

    public function show(Testimonial $testimonial)
    {
        return redirect()->route('admin.testimonials.edit', $testimonial);
    }

    public function edit(Testimonial $testimonial)
    {
        return Inertia::render('Admin/Testimonials/Edit', [
            'testimonial' => $testimonial,
            'availableDefaultAvatars' => Testimonial::getAvailableDefaultAvatars(),
        ]);
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'quote' => 'required|string',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image
            if ($testimonial->image && Storage::disk('public')->exists($testimonial->image)) {
                Storage::disk('public')->delete($testimonial->image);
            }
            $validated['image'] = $request->file('image')->store('testimonials', 'public');
        }

        $testimonial->update($validated);

        return redirect()->route('admin.testimonials.index')
            ->with('success', 'Testimonial berhasil diperbarui');
    }

    public function destroy(Testimonial $testimonial)
    {
        // Delete image if exists
        if ($testimonial->image && Storage::disk('public')->exists($testimonial->image)) {
            Storage::disk('public')->delete($testimonial->image);
        }

        $testimonial->delete();

        return redirect()->route('admin.testimonials.index')
            ->with('success', 'Testimonial berhasil dihapus');
    }

    public function updateOrder(Request $request)
    {
        $this->authorize('update', Testimonial::class);
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:testimonials,id',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->items as $item) {
            Testimonial::where('id', $item['id'])
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Order updated successfully']);
    }

    public function toggleStatus(Testimonial $testimonial)
    {
        $this->authorize('toggle', $testimonial);
        $testimonial->update(['is_active' => ! $testimonial->is_active]);

        return back()->with('success', 'Status testimonial berhasil diubah');
    }

    public function updateSettings(Request $request)
    {
        $this->authorize('update', Testimonial::class);
        $request->validate([
            'settings' => 'required|array',
            'settings.*.id' => 'required|exists:settings,id',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($request->settings as $settingData) {
            $setting = Setting::find($settingData['id']);
            if ($setting) {
                $setting->update(['value' => $settingData['value']]);
            }
        }

        return back()->with('success', 'Settings testimonial berhasil diperbarui');
    }
}
