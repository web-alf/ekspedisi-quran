<?php

use App\Models\Setting;
use App\Services\LandingSectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\LandingSectionOrderSeeder::class);
});

it('passes section order to landing page', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Landing')
        ->has('sectionOrder')
        ->where('sectionOrder.0.id', 'hero')
    );
});

it('uses default order when landing_section_order setting is missing', function () {
    Setting::where('key', 'landing_section_order')->delete();

    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Landing')
        ->has('sectionOrder')
        ->where('sectionOrder', LandingSectionRegistry::getDefaultOrder())
    );
});

it('hides disabled sections from landing page', function () {
    $order = LandingSectionRegistry::getDefaultOrder();
    $order[1]['enabled'] = false; // Disable 'about' section

    Setting::where('key', 'landing_section_order')->update([
        'value' => json_encode($order),
    ]);

    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Landing')
        ->where('sectionOrder', fn ($sections) => ! collect($sections)->contains(fn ($s) => $s['id'] === 'about' && $s['enabled'])
        )
    );
});

it('reflects custom section order on landing page', function () {
    $order = LandingSectionRegistry::getDefaultOrder();
    // Move FAQ to the top after hero
    $faq = collect($order)->firstWhere('id', 'faq');
    $newOrder = array_values(array_filter($order, fn ($s) => $s['id'] !== 'faq'));
    array_splice($newOrder, 1, 0, [$faq]);

    Setting::where('key', 'landing_section_order')->update([
        'value' => json_encode($newOrder),
    ]);

    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Landing')
        ->where('sectionOrder.1.id', 'faq')
    );
});
