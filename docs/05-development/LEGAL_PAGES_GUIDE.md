# 📄 Legal Pages - Installation & Usage Guide

## 🚀 Quick Start

### 1. Run Database Seeder
```bash
# Seed legal settings (jika belum pernah)
php artisan db:seed --class=LegalSettingsSeeder

# Atau seed semua jika fresh install
php artisan migrate --seed
```

### 2. Access Pages
- **Privacy Policy**: http://localhost/privacy-policy
- **Terms of Service**: http://localhost/terms-of-service

### 3. Manage Content
1. Login to admin panel
2. Go to "Pengaturan Sistem"
3. Select "Legal & Kebijakan" category
4. Edit content as needed

## 📋 Content Management

### Admin Panel Settings

| Setting Key | Description | Type |
|-------------|-------------|------|
| `legal_privacy_policy` | Privacy Policy content (HTML) | Textarea |
| `legal_privacy_policy_updated` | Last updated date | Text |
| `legal_terms_of_service` | Terms of Service content (HTML) | Textarea |
| `legal_terms_of_service_updated` | Last updated date | Text |
| `legal_contact_email` | Legal contact email | Text |
| `legal_data_retention_period` | Data retention period | Text |
| `legal_cookies_enabled` | Enable cookies | Boolean |

### Content Guidelines

#### Privacy Policy Sections
1. **Informasi yang Dikumpulkan** - Data collection practices
2. **Penggunaan Informasi** - How data is used
3. **Berbagi Informasi** - Data sharing policies
4. **Keamanan Data** - Security measures
5. **Hak Pengguna** - User rights under privacy laws
6. **Cookies** - Cookie usage policy
7. **Perubahan Kebijakan** - Policy update procedures
8. **Kontak** - Contact information for privacy concerns

#### Terms of Service Sections
1. **Penerimaan Syarat** - Agreement to terms
2. **Tentang Layanan** - Service description
3. **Eligibilitas** - Who can use the service
4. **Proses Permohonan** - How to request services
5. **Verifikasi dan Persetujuan** - Approval process
6. **Pengiriman** - Shipping terms
7. **Kewajiban Penerima** - Recipient obligations
8. **Larangan** - Prohibited uses
9. **Pembatasan Tanggung Jawab** - Liability limitations
10. **Hukum yang Berlaku** - Governing law

## 🔧 Customization

### Adding New Legal Pages

1. **Create Controller Method**
```php
public function newLegalPage()
{
    $settings = Setting::where('is_public', true)
        ->where('is_active', true)
        ->whereIn('group', ['landing', 'contact', 'social', 'general', 'legal'])
        ->pluck('value', 'key');

    $content = Setting::get('legal_new_page_content', $this->getDefaultContent());

    return Inertia::render('Public/NewLegalPage', [
        'settings' => $settings,
        'content' => $content,
        'lastUpdated' => Setting::get('legal_new_page_updated', now()->format('d F Y'))
    ]);
}
```

2. **Add Route**
```php
Route::get('/new-legal-page', [LegalController::class, 'newLegalPage'])->name('legal.new-page');
```

3. **Create Svelte Page**
Copy and modify existing privacy policy or terms page structure.

4. **Add Settings**
Add new settings in LegalSettingsSeeder for the new page content.

### Custom Styling

Update the CSS in the Svelte components:

```css
/* Custom legal page styling */
:global(.legal-content h2) {
    @apply text-3xl font-bold text-gray-900 mt-10 mb-6 first:mt-0;
}

:global(.legal-content .highlight) {
    @apply bg-yellow-100 px-2 py-1 rounded;
}
```

## 🔗 Footer Integration

The footer automatically displays legal page links. To customize:

```svelte
<!-- In Footer.svelte -->
<div class="flex space-x-6 mt-4 md:mt-0">
    <a href="/privacy-policy" class="text-gray-400 hover:text-white text-sm transition-colors duration-300">
        Kebijakan Privasi
    </a>
    <a href="/terms-of-service" class="text-gray-400 hover:text-white text-sm transition-colors duration-300">
        Syarat & Ketentuan
    </a>
    <!-- Add more legal links here -->
</div>
```

## 🛡️ Security Best Practices

### Content Sanitization
When allowing HTML content, implement sanitization:

```php
use HTMLPurifier;
use HTMLPurifier_Config;

public function sanitizeContent($content) 
{
    $config = HTMLPurifier_Config::createDefault();
    $config->set('HTML.AllowedElements', 'h1,h2,h3,h4,h5,h6,p,ul,ol,li,strong,em,a');
    $config->set('HTML.AllowedAttributes', 'a.href');
    
    $purifier = new HTMLPurifier($config);
    return $purifier->purify($content);
}
```

### Rate Limiting
Adjust rate limits if needed:

```php
// In routes/web.php
Route::middleware('throttle:50,1')->group(function () {
    Route::get('/privacy-policy', [LegalController::class, 'privacyPolicy']);
    Route::get('/terms-of-service', [LegalController::class, 'termsOfService']);
});
```

## 🧪 Testing

### Run Tests
```bash
# Run all legal page tests
php artisan test --filter=LegalPagesTest

# Run specific test
php artisan test --filter=privacy_policy_page_loads_successfully
```

### Manual Testing Checklist

#### Functionality
- [ ] Privacy Policy page loads without errors
- [ ] Terms of Service page loads without errors
- [ ] Footer links work correctly
- [ ] Admin can edit content through settings
- [ ] Changes reflect immediately on frontend
- [ ] Rate limiting works (test with many requests)

#### SEO & Accessibility
- [ ] Meta tags are present and correct
- [ ] Page titles are descriptive
- [ ] Heading hierarchy is proper (H1 → H2 → H3)
- [ ] Links have proper hover states
- [ ] Pages are mobile responsive
- [ ] Loading speed is acceptable

#### Content
- [ ] All sections are present
- [ ] Contact information is correct
- [ ] Last updated dates display properly
- [ ] HTML content renders correctly
- [ ] No broken links or formatting issues

## 🔄 Updates & Maintenance

### Updating Content
1. Go to admin panel → Pengaturan Sistem → Legal & Kebijakan
2. Edit the content in textarea fields
3. Update the "last updated" date
4. Save changes

### Regular Maintenance
- Review content quarterly for legal compliance
- Update contact information as needed
- Monitor for broken links
- Check page performance regularly

### Version Control
Consider implementing content versioning for legal pages:

```php
// Add to Setting model
public function versions()
{
    return $this->hasMany(SettingVersion::class);
}

// Before updating, save version
SettingVersion::create([
    'setting_id' => $setting->id,
    'content' => $setting->value,
    'version' => $setting->versions->count() + 1,
    'created_by' => auth()->id()
]);
```

## 📞 Support

For questions or issues:
- Check existing tests for expected behavior
- Review error logs in `storage/logs/`
- Verify database settings are correct
- Test with fresh browser cache

## 🎯 Next Steps

Consider adding:
1. **Cookie Consent Banner** - GDPR compliance
2. **Data Export Tool** - User data download
3. **Legal Compliance Dashboard** - Admin monitoring
4. **Multi-language Support** - International users
5. **PDF Generation** - Downloadable versions

---

**Happy coding!** 🚀 Your legal pages are now ready to provide transparency and build trust with users.
