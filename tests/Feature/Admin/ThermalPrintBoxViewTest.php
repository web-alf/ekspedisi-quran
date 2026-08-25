<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class ThermalPrintBoxViewTest extends TestCase
{
    /**
     * Ensure the box thermal print preview page does not render the box contents table.
     */
    public function test_preview_box_page_does_not_show_contents_table(): void
    {
        // Bypass permission middleware for this route to simplify the test
        if (class_exists(\Spatie\Permission\Middlewares\PermissionMiddleware::class)) {
            $this->withoutMiddleware(\Spatie\Permission\Middlewares\PermissionMiddleware::class);
        }

        $response = $this->get('/admin/thermal-print/box/preview');

        $response->assertSuccessful();
        // Should still show the general box info section
        $response->assertSee('Informasi Box');
        // The "Isi Box" section should be removed
        $response->assertDontSee('Isi Box (');
        $response->assertDontSee('contents-list');
    }

    public function test_preview_box_page_shows_task_date(): void
    {
        if (class_exists(\Spatie\Permission\Middlewares\PermissionMiddleware::class)) {
            $this->withoutMiddleware(\Spatie\Permission\Middlewares\PermissionMiddleware::class);
        }

        $response = $this->get('/admin/thermal-print/box/preview');

        $response->assertSuccessful();
        // Section header restored
        $response->assertSee('Petugas Packing');
        // Should render the date for the task (from sample data)
        $response->assertSee(now()->format('Y-m-d'));
    }
}
