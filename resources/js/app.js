import './bootstrap';
import { createInertiaApp, router } from '@inertiajs/svelte'

// Leaflet CSS is now imported in app.css
import '../css/leaflet.css';

// Configure Inertia router to preserve scroll by default on pagination
router.on('before', (event) => {
  // Check if this is a pagination navigation on any admin page
  const url = typeof event.detail.visit.url === 'string' ? event.detail.visit.url : event.detail.visit.url.href;
  if (url && url.includes('page=') && url.includes('/admin/')) {
    // For pagination, preserve scroll position
    event.detail.visit.preserveScroll = true;
  }
});

createInertiaApp({
  resolve: name => {
    // Use dynamic imports for lazy loading pages
    const pages = import.meta.glob('./Pages/**/*.svelte')
    
    // Return a Promise that resolves to the component
    return pages[`./Pages/${name}.svelte`]()
  },
  setup({ el, App, props }) {
    new App({ target: el, props })
  },
})

// Refresh CSRF token on page navigation to prevent 419 errors
router.on('success', (event) => {
  // Update CSRF token in axios headers after each Inertia navigation
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (csrfToken && window.axios) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
  }
});

// Session timeout handling
let sessionTimeout = 30 * 60 * 1000; // 30 minutes in milliseconds
let lastActivity = Date.now();

function updateLastActivity() {
    lastActivity = Date.now();
}

function checkSessionTimeout() {
    if (Date.now() - lastActivity > sessionTimeout) {
        window.location.href = '/';
    }
}

// Update last activity on user actions
['click', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
    document.addEventListener(event, updateLastActivity);
});

// Check session timeout every minute
setInterval(checkSessionTimeout, 60000);

// File size error handling for Laravel 413 (Request Entity Too Large) errors
window.addEventListener('error', function(e) {
  // Check if it's a network error like file too large
  if (e && e.target && e.target.tagName && e.target.tagName.toLowerCase() === 'form') {
    const formError = document.createElement('div');
    formError.className = 'fixed top-0 left-0 w-full z-50 bg-red-50 text-red-700 p-4 text-center shadow-lg';
    formError.innerHTML = `
      <div class="max-w-3xl mx-auto p-4 bg-white rounded-lg shadow border border-red-200">
        <div class="flex items-start">
          <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mr-4">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-red-800 mb-2">⚠️ File terlalu besar</h3>
            <p class="mb-2">Ukuran file yang Anda upload melebihi batas maksimum yang diizinkan.</p>
            <ul class="list-disc list-inside text-sm space-y-1 mb-3">
              <li>Batas ukuran foto: <strong>maksimal 2MB</strong></li>
              <li>Batas ukuran dokumen: <strong>maksimal 5MB</strong></li>
              <li>Batas ukuran total seluruh file: <strong>maksimal 10MB</strong></li>
            </ul>
            <div class="mt-2 text-sm bg-yellow-50 p-3 rounded border border-yellow-200">
              <p class="font-medium text-yellow-800 mb-1">Tips:</p>
              <ul class="list-disc list-inside space-y-1 text-yellow-700">
                <li>Kompres file gambar menggunakan layanan online seperti TinyPNG</li>
                <li>Kurangi resolusi gambar sebelum mengupload</li>
                <li>Simpan dokumen dengan ukuran lebih kecil (mis. PDF terkompresi)</li>
              </ul>
            </div>
            <div class="mt-4 text-right">
              <button class="px-4 py-2 bg-red-600 text-white rounded-lg" onclick="this.parentElement.parentElement.parentElement.parentElement.remove()">Tutup Pesan</button>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(formError);
  }
});