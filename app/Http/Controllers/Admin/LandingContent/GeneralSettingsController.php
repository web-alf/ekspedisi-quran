<?php

namespace App\Http\Controllers\Admin\LandingContent;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class GeneralSettingsController extends Controller
{
    private $group = 'general';

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

        return Inertia::render('Admin/Settings/LandingContent/General', [
            'settingsCollection' => $settings,  // Changed name to avoid conflict with global settings
            'settingsData' => $groupedSettings,
            'group' => $this->group,
            'groupName' => $this->getGroupName(),
            'filters' => $request->only(['search', 'status', 'visibility'])
        ]);
    }

    public function update(Request $request)
    {
        \Log::info('General settings update request', [
            'request_data' => $request->all(),
            'files' => array_keys($request->allFiles()),
            'is_multipart' => $request->isMethod('POST') && str_contains($request->header('Content-Type'), 'multipart/form-data'),
        ]);

        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.id' => 'required|exists:settings,id',
            'settings.*.value' => 'nullable'
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed', ['errors' => $validator->errors()]);
            return back()->withErrors($validator)->withInput();
        }

        $updated = 0;
        $errors = [];

        foreach ($request->settings as $settingData) {
            try {
                $setting = Setting::where('id', $settingData['id'])
                    ->where('group', $this->group)
                    ->first();
                    
                if (!$setting) {
                    $errors[] = "Setting with ID {$settingData['id']} not found in group {$this->group}";
                    continue;
                }

                // Handle JSON validation
                if ($setting->type === 'json' && !empty($settingData['value'])) {
                    $jsonData = json_decode($settingData['value'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[] = "Setting '{$setting->label}': Format JSON tidak valid";
                        continue;
                    }
                }
                
                // Handle file uploads (if applicable)
                $fileUploaded = false;
                if (in_array($setting->type, ['image', 'file'])) {
                    $fileKey = "file_{$setting->id}";
                    
                    if ($request->hasFile($fileKey)) {
                        // Delete old file if exists
                        if ($setting->value && Storage::disk('public')->exists($setting->value)) {
                            Storage::disk('public')->delete($setting->value);
                        }
                        
                        $file = $request->file($fileKey);
                        $path = $file->store('settings', 'public');
                        $settingData['value'] = $path;
                        $fileUploaded = true;
                        
                        \Log::info('File uploaded successfully', [
                            'setting_id' => $setting->id,
                            'file_key' => $fileKey,
                            'original_name' => $file->getClientOriginalName(),
                            'stored_path' => $path
                        ]);
                    }
                }
                
                // Update setting if file was uploaded, or for non-file types, or if keeping existing value for images
                if ($fileUploaded || !in_array($setting->type, ['image', 'file']) || isset($settingData['value'])) {
                    $setting->update(['value' => $settingData['value']]);
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors[] = "Setting '{$setting->label}': " . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            return back()->withErrors(['settings' => $errors])->withInput();
        }

        return back()->with('success', "{$updated} setting umum berhasil diperbarui");
    }

    private function getGroupName()
    {
        return 'Pengaturan Umum';
    }
}