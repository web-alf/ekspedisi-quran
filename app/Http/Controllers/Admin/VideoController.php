<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Video;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VideoController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Video::class, 'video');
    }

    public function index()
    {
        $videos = Video::ordered()->paginate(12);

        // Get video-related settings
        $videoSettings = Setting::where('group', 'video')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return Inertia::render('Admin/Videos/Index', [
            'videos' => $videos,
            'videoSettings' => $videoSettings,
        ]);
    }

    public function show(Video $video)
    {
        return Inertia::render('Admin/Videos/Show', [
            'video' => $video,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Videos/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'video_url' => 'required|url|max:500',
            'caption' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Validate YouTube URL
        if (! $this->isValidYoutubeUrl($validated['video_url'])) {
            return back()->withErrors(['video_url' => 'URL harus berupa link YouTube yang valid.']);
        }

        // Ensure empty fields become null, sort_order defaults to 0
        if (empty($validated['caption'])) {
            $validated['caption'] = null;
        }
        if (empty($validated['category'])) {
            $validated['category'] = null;
        }
        if (empty($validated['sort_order'])) {
            $validated['sort_order'] = 0;
        }

        $validated['video_type'] = 'youtube';

        Video::create($validated);

        return redirect()->route('admin.videos.index')
            ->with('success', 'Video berhasil ditambahkan');
    }

    public function edit(Video $video)
    {
        return Inertia::render('Admin/Videos/Edit', [
            'video' => $video,
        ]);
    }

    public function update(Request $request, Video $video)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'video_url' => 'required|url|max:500',
            'caption' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Validate YouTube URL
        if (! $this->isValidYoutubeUrl($validated['video_url'])) {
            return back()->withErrors(['video_url' => 'URL harus berupa link YouTube yang valid.']);
        }

        // Ensure empty fields become null, sort_order defaults to 0
        if (empty($validated['caption'])) {
            $validated['caption'] = null;
        }
        if (empty($validated['category'])) {
            $validated['category'] = null;
        }
        if (empty($validated['sort_order'])) {
            $validated['sort_order'] = 0;
        }

        $video->update($validated);

        return redirect()->route('admin.videos.index')
            ->with('success', 'Video berhasil diperbarui');
    }

    public function destroy(Video $video)
    {
        $video->delete();

        return redirect()->route('admin.videos.index')
            ->with('success', 'Video berhasil dihapus');
    }

    public function toggleStatus(Video $video)
    {
        $this->authorize('update', $video);
        $video->update(['is_active' => ! $video->is_active]);

        return back()->with('success', 'Status video berhasil diubah');
    }

    public function updateOrder(Request $request)
    {
        $this->authorize('update', Video::class);
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:videos,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->items as $item) {
            Video::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return back()->with('success', 'Urutan video berhasil diperbarui');
    }

    public function updateSettings(Request $request)
    {
        $this->authorize('update', Video::class);
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

        return back()->with('success', 'Settings video berhasil diperbarui');
    }

    /**
     * Validate if URL is a valid YouTube URL
     */
    private function isValidYoutubeUrl(string $url): bool
    {
        $patterns = [
            '/^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=[^\s&]+)/',
            '/^(https?:\/\/)?(www\.)?(youtube\.com\/embed\/[^\s?]+)/',
            '/^(https?:\/\/)?(youtu\.be\/[^\s?]+)/',
            '/^(https?:\/\/)?(www\.)?(youtube\.com\/v\/[^\s?]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }
}
