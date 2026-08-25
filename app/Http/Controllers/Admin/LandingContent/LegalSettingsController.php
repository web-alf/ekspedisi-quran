<?php

namespace App\Http\Controllers\Admin\LandingContent;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class LegalSettingsController extends Controller
{
    private $group = 'legal';

    public function index(Request $request)
    {
        $query = Setting::where('group', $this->group)
            ->orderBy('sort_order')
            ->orderBy('label');
        
        // Add search filter if provided
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                  ->orWhere('key', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        // Add status filter if provided
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }
        
        // Add visibility filter if provided
        if ($request->filled('visibility')) {
            if ($request->visibility === 'public') {
                $query->where('is_public', true);
            } elseif ($request->visibility === 'private') {
                $query->where('is_public', false);
            }
        }

        $settings = $query->paginate(25)->withQueryString();
        
        // Group settings for compatibility with frontend
        $groupedSettings = $settings->getCollection()->groupBy('group');

        return Inertia::render('Admin/Settings/LandingContent/Legal', [
            'settingsCollection' => $settings,
            'settingsData' => $groupedSettings,
            'group' => $this->group,
            'groupName' => $this->getGroupName(),
            'filters' => $request->only(['search', 'status', 'visibility'])
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.id' => 'required|exists:settings,id',
            'settings.*.value' => 'nullable'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $updated = 0;
        $errors = [];

        foreach ($request->settings as $settingData) {
            $setting = Setting::where('id', $settingData['id'])
                ->where('group', $this->group)
                ->first();
                
            if ($setting) {
                try {
                    // Handle JSON validation
                    if ($setting->type === 'json' && !empty($settingData['value'])) {
                        $jsonData = json_decode($settingData['value'], true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $errors[] = "Setting '{$setting->label}': Format JSON tidak valid";
                            continue;
                        }
                    }
                    
                    // Handle file uploads (if applicable)
                    if (in_array($setting->type, ['image', 'file']) && $request->hasFile("settings.{$setting->id}.value")) {
                        // Delete old file if exists
                        if ($setting->value && Storage::disk('public')->exists($setting->value)) {
                            Storage::disk('public')->delete($setting->value);
                        }
                        
                        $file = $request->file("settings.{$setting->id}.value");
                        $path = $file->store('settings', 'public');
                        $settingData['value'] = $path;
                    }
                    
                    $setting->update(['value' => $settingData['value']]);
                    $updated++;
                } catch (\Exception $e) {
                    $errors[] = "Setting '{$setting->label}': " . $e->getMessage();
                }
            }
        }

        if (!empty($errors)) {
            return back()->withErrors(['settings' => $errors])->withInput();
        }

        return back()->with('success', "{$updated} setting legal & kebijakan berhasil diperbarui");
    }

    private function getGroupName()
    {
        return 'Pengaturan Legal & Kebijakan';
    }
}