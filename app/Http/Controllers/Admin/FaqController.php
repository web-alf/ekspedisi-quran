<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FaqController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Faq::class, 'faq');
    }

    public function index()
    {
        $faqs = Faq::ordered()->paginate(15);

        // Get FAQ-related settings
        $faqSettings = Setting::where('group', 'faq')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return Inertia::render('Admin/Faqs/Index', [
            'faqs' => $faqs,
            'faqSettings' => $faqSettings,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Faqs/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        Faq::create($validated);

        return redirect()->route('admin.faqs.index')
            ->with('success', 'FAQ berhasil ditambahkan');
    }

    public function edit(Faq $faq)
    {
        return Inertia::render('Admin/Faqs/Edit', [
            'faq' => $faq,
        ]);
    }

    public function update(Request $request, Faq $faq)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $faq->update($validated);

        return redirect()->route('admin.faqs.index')
            ->with('success', 'FAQ berhasil diperbarui');
    }

    public function destroy(Faq $faq)
    {
        $faq->delete();

        return redirect()->route('admin.faqs.index')
            ->with('success', 'FAQ berhasil dihapus');
    }

    public function toggleStatus(Faq $faq)
    {
        $this->authorize('update', $faq);
        $faq->update(['is_active' => ! $faq->is_active]);

        return back()->with('success', 'Status FAQ berhasil diubah');
    }

    public function updateOrder(Request $request)
    {
        $this->authorize('update', Faq::class);
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:faqs,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->items as $item) {
            Faq::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return back()->with('success', 'Urutan FAQ berhasil diperbarui');
    }

    public function updateSettings(Request $request)
    {
        $this->authorize('update', Faq::class);
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

        return back()->with('success', 'Settings FAQ berhasil diperbarui');
    }
}
