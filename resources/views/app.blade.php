<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
        <!-- SEO Meta Tags -->
        <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
        <link rel="canonical" href="{{ url()->current() }}">
        <link rel="sitemap" type="application/xml" href="{{ url('/sitemap.xml') }}">
        
        <title inertia>{{ \App\Models\Setting::where('key', 'seo_site_title')->where('is_active', true)->value('value') ?? config('app.name', 'Ekspedisi Quran') }}</title>

        <!-- Favicon & Icons -->
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon-ekspedisi-quran.svg') }}">
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicon-ekspedisi-quran.svg') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.ico') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon.ico') }}">
        <meta name="theme-color" content="#10b981">

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="{{ \App\Models\Setting::where('key', 'seo_site_title')->where('is_active', true)->value('value') ?? config('app.name', 'Ekspedisi Quran') }}">
        <meta property="og:description" content="{{ \App\Models\Setting::where('key', 'seo_site_description')->where('is_active', true)->value('value') ?? 'Platform wakaf dan distribusi mushaf Al-Quran untuk seluruh Indonesia' }}">
        <meta property="og:image" content="{{ asset(\App\Models\Setting::where('key', 'seo_og_image')->where('is_active', true)->value('value') ?? 'images/og-image.jpg') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:site_name" content="{{ \App\Models\Setting::where('key', 'seo_site_title')->where('is_active', true)->value('value') ?? config('app.name', 'Ekspedisi Quran') }}">
        <meta property="og:locale" content="id_ID">

        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="{{ url()->current() }}">
        <meta property="twitter:title" content="{{ \App\Models\Setting::where('key', 'seo_site_title')->where('is_active', true)->value('value') ?? config('app.name', 'Ekspedisi Quran') }}">
        <meta property="twitter:description" content="{{ \App\Models\Setting::where('key', 'seo_site_description')->where('is_active', true)->value('value') ?? 'Platform wakaf dan distribusi mushaf Al-Quran untuk seluruh Indonesia' }}">
        <meta property="twitter:image" content="{{ asset(\App\Models\Setting::where('key', 'seo_og_image')->where('is_active', true)->value('value') ?? 'images/og-image.jpg') }}">

        <!-- Additional meta tags -->
        <meta name="description" content="{{ \App\Models\Setting::where('key', 'seo_site_description')->where('is_active', true)->value('value') ?? 'Platform wakaf dan distribusi mushaf Al-Quran untuk seluruh Indonesia' }}">
        <meta name="author" content="{{ \App\Models\Setting::where('key', 'seo_author')->where('is_active', true)->value('value') ?? config('app.name', 'Ekspedisi Quran') }}">
        
        <!-- Structured Data -->
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "Organization",
            "name": "{{ \App\Models\Setting::where('key', 'seo_site_title')->where('is_active', true)->value('value') ?? config('app.name', 'Ekspedisi Quran') }}",
            "description": "{{ \App\Models\Setting::where('key', 'seo_site_description')->where('is_active', true)->value('value') ?? 'Platform wakaf dan distribusi mushaf Al-Quran untuk seluruh Indonesia' }}",
            "url": "{{ url('/') }}",
            "logo": "{{ asset('images/logo-ekspedisi-quran.webp') }}",
            "sameAs": [
                "{{ \App\Models\Setting::where('key', 'seo_instagram_url')->where('is_active', true)->value('value') ?? 'https://www.instagram.com/ekspedisiquran/' }}",
                "{{ \App\Models\Setting::where('key', 'seo_facebook_url')->where('is_active', true)->value('value') ?? 'https://www.facebook.com/ekspedisiquran/' }}"
            ]
        }
        </script>

        <!-- Resource Hints -->
        <link rel="dns-prefetch" href="//fonts.bunny.net">
        <link rel="dns-prefetch" href="//fonts.googleapis.com">
        <link rel="dns-prefetch" href="//fonts.gstatic.com">
        
        <!-- Preload Critical Resources -->
        <link rel="preload" href="{{ asset('images/logo-ekspedisi-quran.webp') }}" as="image" type="image/webp">
        <link rel="preload" href="{{ asset('images/hero-ekspedisi-quran.webp') }}" as="image" type="image/webp">
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
