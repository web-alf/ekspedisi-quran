/**
 * SEO utility functions for managing page titles and meta data
 */

/**
 * Generate page title with app suffix
 * @param {string} pageTitle - The specific page title
 * @param {string} appName - The application name (default: 'Ekspedisi Quran')
 * @returns {string} - Formatted title
 */
export function formatPageTitle(pageTitle = null, appName = "Ekspedisi Qur'an") {
    if (!pageTitle) {
        return appName;
    }
    return `${pageTitle} - ${appName}`;
}

/**
 * Common page titles for the application
 */
export const pageTitles = {
    home: 'Beranda',
    about: 'Tentang Kami',
    programs: 'Program Kami',
    contact: 'Kontak',
    tracking: 'Lacak Pengiriman',
    wakaf: 'Wakaf Quran',
    mushafRequest: 'Permintaan Mushaf',
    privacyPolicy: 'Kebijakan Privasi',
    termsOfService: 'Syarat dan Ketentuan',
    
    // Admin titles
    adminDashboard: 'Dashboard Admin',
    adminUsers: 'Manajemen Pengguna',
    adminRoles: 'Manajemen Role',
    adminPermissions: 'Manajemen Izin',
    adminShipments: 'Manajemen Pengiriman',
    adminDonors: 'Manajemen Donatur',
    adminSettings: 'Pengaturan Sistem',
    adminBoxTracking: 'Tracking Box',
    adminCertificates: 'Manajemen Sertifikat',
    adminQR: 'Manajemen QR Code',
    
    // Warehouse titles
    warehouseDashboard: 'Dashboard Warehouse',
    warehousePacking: 'Packing Station',
    warehouseScanner: 'Scanner Box',
    warehousePerformance: 'Laporan Performa',
    
    // Public titles
    publicTracking: 'Lacak Resi Pengiriman',
    publicMushafRequest: 'Form Permintaan Mushaf',
};

/**
 * Generate meta description for pages
 * @param {string} page - Page identifier
 * @param {Object} data - Additional data for dynamic descriptions
 * @returns {string} - Meta description
 */
export function generateMetaDescription(page, data = {}) {
    const descriptions = {
        home: 'Platform wakaf dan distribusi mushaf Al-Quran untuk seluruh Indonesia',
        about: 'Pelajari lebih lanjut tentang misi dan visi Ekspedisi Quran dalam menyebarkan mushaf Al-Quran ke seluruh Indonesia.',
        programs: 'Jelajahi berbagai program wakaf Quran yang tersedia dan pilih cara terbaik untuk berkontribusi.',
        contact: 'Hubungi tim Ekspedisi Quran untuk informasi lebih lanjut tentang program wakaf dan distribusi mushaf.',
        tracking: 'Lacak status pengiriman mushaf Quran Anda dengan mudah menggunakan nomor resi.',
        wakaf: 'Berpartisipasi dalam program wakaf Quran dan bantu menyebarkan Al-Quran ke seluruh Indonesia.',
        mushafRequest: 'Ajukan permintaan mushaf Al-Quran untuk lembaga atau komunitas Anda.',
        privacyPolicy: 'Kebijakan privasi Ekspedisi Quran dalam penanganan data pribadi pengguna.',
        termsOfService: 'Syarat dan ketentuan penggunaan layanan Ekspedisi Quran.',
    };
    
    return descriptions[page] || 'Platform wakaf dan distribusi mushaf Al-Quran untuk seluruh Indonesia.';
}