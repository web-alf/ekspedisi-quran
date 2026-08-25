<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove all testimonial, gallery, and FAQ related settings
        DB::table('settings')->whereIn('key', [
            // Main JSON settings
            'landing_testimonials',
            'landing_gallery', 
            'landing_faqs',
            
            // Enable/disable settings
            'landing_testimonials_enabled',
            'landing_gallery_enabled',
            'landing_faqs_enabled',
            
            // Gallery specific settings
            'landing_gallery_title',
            'landing_gallery_subtitle',
            'landing_gallery_image_1',
            'landing_gallery_image_1_caption',
            'landing_gallery_image_2',
            'landing_gallery_image_2_caption',
            'landing_gallery_image_3',
            'landing_gallery_image_3_caption',
            'landing_gallery_image_4',
            'landing_gallery_image_4_caption',
            'landing_gallery_image_5',
            'landing_gallery_image_5_caption',
            'landing_gallery_image_6',
            'landing_gallery_image_6_caption',
            'landing_gallery_layout',
            'landing_gallery_autoplay',
            'landing_gallery_show_captions',
            'landing_gallery_lightbox'
        ])->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is irreversible since we're migrating to dedicated tables
        // The data has been moved to testimonials, galleries, and faqs tables
    }
};
