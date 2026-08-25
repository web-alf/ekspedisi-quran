<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Gallery;
use App\Models\Faq;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate testimonials from JSON
        $testimonialSetting = Setting::where('key', 'landing_testimonials')->first();
        if ($testimonialSetting && $testimonialSetting->value) {
            try {
                $testimonials = json_decode($testimonialSetting->value, true);
                if (is_array($testimonials)) {
                    foreach ($testimonials as $index => $testimonial) {
                        Testimonial::firstOrCreate(
                            ['name' => $testimonial['name'] ?? 'Unknown'],
                            [
                                'location' => $testimonial['location'] ?? '',
                                'quote' => $testimonial['testimonial'] ?? $testimonial['quote'] ?? '',
                                'image' => $testimonial['image'] ?? null,
                                'sort_order' => $index + 1,
                                'is_active' => true,
                            ]
                        );
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Failed to migrate testimonials: ' . $e->getMessage());
            }
        }

        // Migrate gallery from JSON
        $gallerySetting = Setting::where('key', 'landing_gallery')->first();
        if ($gallerySetting && $gallerySetting->value) {
            try {
                $galleries = json_decode($gallerySetting->value, true);
                if (is_array($galleries)) {
                    foreach ($galleries as $index => $gallery) {
                        Gallery::firstOrCreate(
                            ['title' => $gallery['caption'] ?? 'Gallery Image ' . ($index + 1)],
                            [
                                'caption' => $gallery['caption'] ?? '',
                                'image' => ltrim($gallery['src'] ?? '', '/'),
                                'category' => 'landing',
                                'sort_order' => $index + 1,
                                'is_active' => true,
                            ]
                        );
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Failed to migrate galleries: ' . $e->getMessage());
            }
        }

        // Migrate FAQs from JSON
        $faqSetting = Setting::where('key', 'landing_faqs')->first();
        if ($faqSetting && $faqSetting->value) {
            try {
                $faqs = json_decode($faqSetting->value, true);
                if (is_array($faqs)) {
                    foreach ($faqs as $index => $faq) {
                        Faq::firstOrCreate(
                            ['question' => $faq['question'] ?? 'Question ' . ($index + 1)],
                            [
                                'answer' => $faq['answer'] ?? '',
                                'category' => 'general',
                                'sort_order' => $index + 1,
                                'is_active' => true,
                            ]
                        );
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Failed to migrate FAQs: ' . $e->getMessage());
            }
        }

        // After successful migration, you might want to update or remove the JSON settings
        // Setting::whereIn('key', ['landing_testimonials', 'landing_gallery', 'landing_faqs'])->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is primarily for data transfer
        // We don't want to delete the transferred data on rollback
        // If needed, you can implement logic to restore JSON data from the tables
    }
};