<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\CertificateTemplate;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing certificate templates field_positions
        // Replace 'mushaf_count' with 'hijri_date'
        
        $templates = CertificateTemplate::all();
        
        foreach ($templates as $template) {
            $positions = $template->field_positions;
            
            if (is_array($positions) && isset($positions['mushaf_count'])) {
                // Copy mushaf_count settings to hijri_date
                $positions['hijri_date'] = $positions['mushaf_count'];
                
                // Remove old mushaf_count
                unset($positions['mushaf_count']);
                
                // Update template
                $template->update(['field_positions' => $positions]);
                
                echo "Updated template: {$template->name} (ID: {$template->id})\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert hijri_date back to mushaf_count
        
        $templates = CertificateTemplate::all();
        
        foreach ($templates as $template) {
            $positions = $template->field_positions;
            
            if (is_array($positions) && isset($positions['hijri_date'])) {
                // Copy hijri_date settings back to mushaf_count
                $positions['mushaf_count'] = $positions['hijri_date'];
                
                // Remove hijri_date
                unset($positions['hijri_date']);
                
                // Update template
                $template->update(['field_positions' => $positions]);
                
                echo "Reverted template: {$template->name} (ID: {$template->id})\n";
            }
        }
    }
};
