{{--
    Standalone utility styles for error pages.

    Error pages render outside the Vite bundle, so they cannot rely on the app's
    compiled Tailwind build. They previously loaded Tailwind from a CDN, which
    the site CSP blocks (style-src 'self' 'unsafe-inline' fonts.bunny.net
    fonts.googleapis.com) — leaving every error page unstyled. Only the utility
    classes actually used by these views are defined here.
--}}
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    a { text-decoration: none; }

    /* Layout */
    .flex { display: flex; }
    .inline-flex { display: inline-flex; }
    .flex-col { flex-direction: column; }
    .items-center { align-items: center; }
    .items-start { align-items: flex-start; }
    .justify-center { justify-content: center; }
    .justify-between { justify-content: space-between; }
    .mx-auto { margin-left: auto; margin-right: auto; }
    .min-h-screen { min-height: 100vh; }
    .overflow-hidden { overflow: hidden; }
    .text-center { text-align: center; }
    .w-full { width: 100%; }
    .list-disc { list-style-type: disc; }
    .list-inside { list-style-position: inside; }
    .space-y-1 > * + * { margin-top: 0.25rem; }
    .gap-4 { gap: 1rem; }
    .transition-colors { transition: background-color .15s ease, border-color .15s ease, color .15s ease; }

    /* Width / height */
    .w-5 { width: 1.25rem; } .h-5 { height: 1.25rem; }
    .w-7 { width: 1.75rem; } .h-7 { height: 1.75rem; }
    .w-12 { width: 3rem; } .h-12 { height: 3rem; }
    .w-20 { width: 5rem; } .h-20 { height: 5rem; }

    /* Max width */
    .max-w-md { max-width: 28rem; }
    .max-w-2xl { max-width: 42rem; }

    /* Padding / margin */
    .p-4 { padding: 1rem; } .p-6 { padding: 1.5rem; }
    .px-5 { padding-left: 1.25rem; padding-right: 1.25rem; }
    .px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
    .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
    .py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
    .mb-2 { margin-bottom: 0.5rem; } .mb-4 { margin-bottom: 1rem; }
    .mb-6 { margin-bottom: 1.5rem; } .mb-8 { margin-bottom: 2rem; }
    .mr-2 { margin-right: 0.5rem; } .mr-4 { margin-right: 1rem; }

    /* Border radius */
    .rounded-lg { border-radius: 0.5rem; }
    .rounded-xl { border-radius: 0.75rem; }
    .rounded-full { border-radius: 9999px; }

    /* Borders & shadow */
    .border { border: 1px solid currentColor; }
    .border-gray-200 { border-color: #e5e7eb; }
    .border-yellow-200 { border-color: #fde68a; }
    .shadow-lg { box-shadow: 0 10px 15px -3px rgb(0 0 0 / .1), 0 4px 6px -4px rgb(0 0 0 / .1); }

    /* Typography */
    .font-medium { font-weight: 500; }
    .font-semibold { font-weight: 600; }
    .font-bold { font-weight: 700; }
    .text-xl { font-size: 1.25rem; line-height: 1.75rem; }
    .text-2xl { font-size: 1.5rem; line-height: 2rem; }
    .text-9xl { font-size: 8rem; line-height: 1; }

    /* Text colours */
    .text-white { color: #fff; }
    .text-gray-200 { color: #e5e7eb; }
    .text-gray-400 { color: #9ca3af; }
    .text-gray-600 { color: #4b5563; }
    .text-gray-800 { color: #1f2937; }
    .text-red-100 { color: #fee2e2; }
    .text-red-500 { color: #ef4444; }
    .text-orange-100 { color: #ffedd5; }
    .text-orange-500 { color: #f97316; }
    .text-purple-100 { color: #f3e8ff; }
    .text-purple-500 { color: #a855f7; }
    .text-yellow-500 { color: #eab308; }
    .text-yellow-700 { color: #a16207; }
    .text-yellow-800 { color: #854d0e; }

    /* Background colours */
    .bg-white { background-color: #fff; }
    .bg-gray-50 { background-color: #f9fafb; }
    .bg-gray-100 { background-color: #f3f4f6; }
    .bg-gray-600 { background-color: #4b5563; }
    .bg-red-50 { background-color: #fef2f2; }
    .bg-red-100 { background-color: #fee2e2; }
    .bg-red-600 { background-color: #dc2626; }
    .bg-orange-50 { background-color: #fff7ed; }
    .bg-purple-50 { background-color: #faf5ff; }
    .bg-yellow-50 { background-color: #fefce8; }
    .bg-yellow-100 { background-color: #fef9c3; }
    .bg-yellow-600 { background-color: #ca8a04; }

    /* Hover */
    .hover\:bg-gray-700:hover { background-color: #374151; }
    .hover\:bg-red-700:hover { background-color: #b91c1c; }
    .hover\:bg-yellow-700:hover { background-color: #a16207; }

    @media (min-width: 640px) {
        .sm\:flex-row { flex-direction: row; }
    }
</style>
