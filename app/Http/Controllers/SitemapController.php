<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
        
        // Static pages
        $staticPages = [
            ['url' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['url' => '/tentang', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['url' => '/program', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['url' => '/kontak', 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['url' => '/wakaf', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['url' => '/tracking', 'priority' => '0.7', 'changefreq' => 'daily'],
        ];

        foreach ($staticPages as $page) {
            $sitemap .= $this->buildUrlEntry(
                url($page['url']),
                Carbon::now()->toISOString(),
                $page['changefreq'],
                $page['priority']
            );
        }

        // Add dynamic content (if any)
        // TODO: Add blog posts, news, etc. when available
        
        $sitemap .= '</urlset>' . PHP_EOL;

        return response($sitemap, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    private function buildUrlEntry(string $url, string $lastmod, string $changefreq, string $priority): string
    {
        return sprintf(
            '  <url>%s    <loc>%s</loc>%s    <lastmod>%s</lastmod>%s    <changefreq>%s</changefreq>%s    <priority>%s</priority>%s  </url>%s',
            PHP_EOL,
            htmlspecialchars($url),
            PHP_EOL,
            $lastmod,
            PHP_EOL,
            $changefreq,
            PHP_EOL,
            $priority,
            PHP_EOL,
            PHP_EOL
        );
    }
}
