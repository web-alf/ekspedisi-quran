module.exports = {
  ci: {
    collect: {
      url: [
        'http://localhost:8000',
        'http://localhost:8000/admin/dashboard',
        'http://localhost:8000/admin/pengiriman',
        'http://localhost:8000/admin/donatur',
        'http://localhost:8000/warehouse/dashboard',
        'http://localhost:8000/public/tracking'
      ],
      startServerCommand: 'php artisan serve --port=8000',
      startServerReadyPattern: 'Development Server.*started',
      startServerReadyTimeout: 30000,
      numberOfRuns: 3,
      settings: {
        chromeFlags: '--no-sandbox --headless',
        preset: 'desktop',
        throttling: {
          rttMs: 40,
          throughputKbps: 10240,
          cpuSlowdownMultiplier: 1,
          requestLatencyMs: 0,
          downloadThroughputKbps: 0,
          uploadThroughputKbps: 0
        },
        skipAudits: [
          'canonical',
          'robots-txt',
          'tap-targets',
          'content-width'
        ]
      }
    },
    assert: {
      preset: 'lighthouse:recommended',
      assertions: {
        'categories:performance': ['error', { minScore: 0.8 }],
        'categories:accessibility': ['error', { minScore: 0.9 }],
        'categories:best-practices': ['error', { minScore: 0.85 }],
        'categories:seo': ['error', { minScore: 0.8 }],
        
        // Core Web Vitals
        'first-contentful-paint': ['error', { maxNumericValue: 2500 }],
        'largest-contentful-paint': ['error', { maxNumericValue: 4000 }],
        'cumulative-layout-shift': ['error', { maxNumericValue: 0.25 }],
        'total-blocking-time': ['error', { maxNumericValue: 300 }],
        
        // Performance budgets
        'resource-summary:script:size': ['error', { maxNumericValue: 500000 }], // 500KB
        'resource-summary:stylesheet:size': ['error', { maxNumericValue: 100000 }], // 100KB
        'resource-summary:image:size': ['error', { maxNumericValue: 1000000 }], // 1MB
        'resource-summary:total:size': ['error', { maxNumericValue: 2000000 }], // 2MB
        
        // Network requests
        'resource-summary:total:count': ['error', { maxNumericValue: 50 }],
        'unused-css-rules': ['error', { maxNumericValue: 50000 }],
        'unused-javascript': ['error', { maxNumericValue: 100000 }],
        
        // Images
        'modern-image-formats': 'error',
        'uses-optimized-images': 'error',
        'uses-responsive-images': 'error',
        
        // JavaScript
        'unminified-javascript': 'error',
        'render-blocking-resources': 'error',
        'uses-rel-preconnect': 'error'
      }
    },
    upload: {
      target: 'temporary-public-storage'
    },
    server: {
      port: 9001,
      storage: './lhci_reports'
    }
  }
};
