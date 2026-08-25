import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { svelte } from '@sveltejs/vite-plugin-svelte';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js'
            ],
            refresh: true,
        }),
        svelte({
            compilerOptions: {
                dev: process.env.NODE_ENV === 'development',
            }
        }),
    ],
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        https: false, // Disable HTTPS to match Laravel HTTP
        hmr: {
            host: '127.0.0.1',
            port: 5173
        },
        cors: {
            origin: [
                'http://127.0.0.1:8000',
                'http://localhost:8000',
                'http://ekspedisi-quran.test',
                'https://ekspedisi-quran.test'
            ],
            credentials: true
        }
    },
    build: {
        manifest: true,
        outDir: 'public/build',
        // Add performance optimizations
        target: 'es2015',
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
            },
        },
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // **CRITICAL FIX**: Prevent CSS and virtual modules from creating JS dependencies
                    if (id.includes('?') || id.includes('.css') || id.startsWith('\0')) {
                        return null; // Let Vite handle these naturally
                    }

                    // **STEP 1: Pure vendor dependencies (must be completely independent)**
                    if (id.includes('node_modules')) {
                        // Axios and basic utilities - lowest level
                        if (id.includes('axios') || 
                            id.includes('clsx') || 
                            id.includes('tailwind-merge')) {
                            return 'vendor';
                        }

                        // Large third-party libraries - separate chunks
                        if (id.includes('chart.js')) {
                            return 'chart';
                        }
                        if (id.includes('leaflet')) {
                            return 'leaflet';
                        }
                        if (id.includes('tinymce') || id.includes('@tinymce')) {
                            return 'tinymce';
                        }
                        if (id.includes('html5-qrcode') || id.includes('qr-scanner')) {
                            return 'qr-scanner';
                        }
                        if (id.includes('qrcode') && !id.includes('html5-qrcode')) {
                            return 'qrcode-generator';
                        }
                        
                        // Framework level (depends only on vendor)
                        if (id.includes('@inertiajs') || id.includes('svelte')) {
                            return 'framework';
                        }
                        
                        // All other node_modules (depends only on vendor)
                        return 'vendor';
                    }

                    // **STEP 2: Application-level chunks (depend on vendor/framework)**
                    if (id.includes('resources/js/')) {
                        // Core utilities first (minimal dependencies)
                        if (id.includes('/utils/') || id.includes('/stores/')) {
                            return 'app-utils';
                        }

                        // Layouts (framework level)
                        if (id.includes('/Layouts/')) {
                            return 'layouts';
                        }

                        // Components by type
                        if (id.includes('/Components/Certificate/')) {
                            return 'components-certificate';
                        }
                        if (id.includes('/Components/Warehouse/')) {
                            return 'components-warehouse';
                        }
                        if (id.includes('/Components/')) {
                            return 'components-shared';
                        }

                        // Admin pages (feature-based chunks)
                        if (id.includes('/Pages/Admin/')) {
                            if (id.includes('Dashboard') || id.includes('Performance') || id.includes('BulkOperations')) {
                                return 'admin-dashboard';
                            }
                            if (id.includes('Certificate') || id.includes('Sertifikat')) {
                                return 'admin-certificates';
                            }
                            if (id.includes('BoxTracking') || id.includes('Pengiriman') || id.includes('ScanQR') || id.includes('GenerateQR')) {
                                return 'admin-warehouse';
                            }
                            if (id.includes('Users') || id.includes('Roles') || id.includes('Permissions')) {
                                return 'admin-users';
                            }
                            if (id.includes('Settings') || id.includes('Galleries') || id.includes('Testimonials') || id.includes('Faqs')) {
                                return 'admin-content';
                            }
                            if (id.includes('Donatur') || id.includes('MushafRequest')) {
                                return 'admin-donors';
                            }
                            return 'admin-misc';
                        }

                        // Other page types
                        if (id.includes('/Pages/Warehouse/')) {
                            return 'warehouse';
                        }
                        if (id.includes('/Pages/Supervisor/')) {
                            return 'supervisor';
                        }
                        if (id.includes('/Pages/Public/') || id.includes('Landing.svelte')) {
                            return 'public';
                        }

                        // Main app entry and other core app files
                        return 'app';
                    }

                    // **STEP 3: Default handling**
                    // For any other files, return null to let Vite decide
                    return null;
                }
            }
        },
        // Raise chunk size warning limit to reduce noise for intentionally large chunks
        chunkSizeWarningLimit: 1500
    },
    optimizeDeps: {
        include: [
            '@inertiajs/svelte',
            'svelte',
            'axios',
            'leaflet'
        ],
        // Exclude large libraries that should be lazy loaded
        exclude: [
            'chart.js',
            '@tinymce/tinymce-svelte',
            'html5-qrcode',
            'qr-scanner'
        ]
    },
    resolve: {
        alias: {
            '@': '/resources/js',
        }
    }
});
