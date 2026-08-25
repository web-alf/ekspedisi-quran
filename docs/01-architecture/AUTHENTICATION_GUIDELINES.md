# Authentication Guidelines

## CSRF Token Handling untuk Semua Role User

Untuk memastikan keamanan aplikasi, semua fungsi logout dan operasi authentication harus menggunakan CSRF token yang proper. Aplikasi ini mendukung multiple user roles:

- **super-admin**: Full access
- **warehouse**: Staff gudang  
- **supervisor**: Supervisor gudang
- **customer-service**: Customer service
- **courier**: Kurir

## Penggunaan Utility Functions

### Import Auth Utilities

```javascript
import { logout, getCsrfToken, isAuthenticated, getCurrentUserRole, hasRole, hasAnyRole } from '../utils/auth.js';
```

### Logout Function (Wajib untuk semua role)

**❌ Jangan gunakan:**
```javascript
// SALAH - tidak menggunakan form submission
router.post('/logout', { _token: token });

// SALAH - tidak ada CSRF protection
window.location.href = '/logout';
```

**✅ Gunakan:**
```javascript
import { logout } from '../utils/auth.js';

function handleLogout() {
    logout(); // Otomatis menangani CSRF token
}
```

### Authentication Checks

```javascript
import { isAuthenticated, getCurrentUserRole, hasRole, hasAnyRole } from '../utils/auth.js';

// Check if user is authenticated
if (isAuthenticated($page.props)) {
    // User is logged in
}

// Get user role
const userRole = getCurrentUserRole($page.props);

// Check specific role
if (hasRole($page.props, 'warehouse')) {
    // User is warehouse staff
}

// Check multiple roles
if (hasAnyRole($page.props, ['warehouse', 'supervisor'])) {
    // User is warehouse staff or supervisor
}
```

### CSRF Token untuk API Calls

```javascript
import { getCsrfToken } from '../utils/auth.js';

const response = await fetch('/api/some-endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken()
    },
    body: JSON.stringify(data)
});
```

## Role-Based Access Control

### Frontend Components

Semua component harus mengecek role user sebelum menampilkan UI tertentu:

```svelte
<script>
    import { hasRole, hasAnyRole } from '../utils/auth.js';
    import { page } from '@inertiajs/svelte';
    
    $: userRole = $page.props.auth?.user?.role;
</script>

{#if hasRole($page.props, 'super-admin')}
    <AdminPanel />
{/if}

{#if hasAnyRole($page.props, ['warehouse', 'supervisor'])}
    <WarehouseFeatures />
{/if}
```

### Backend Controllers

Controllers harus selalu memvalidasi role dan permission:

```php
// Check role
if (!$user->hasRole('warehouse')) {
    abort(403, 'Unauthorized');
}

// Check permission
if (!$user->can('warehouse.dashboard')) {
    abort(403, 'Unauthorized');
}
```

## Security Best Practices

1. **Selalu gunakan CSRF protection** untuk semua form submissions
2. **Validasi role di backend** - jangan hanya mengandalkan frontend
3. **Logout menggunakan POST method** dengan CSRF token
4. **Session invalidation** saat logout
5. **Regenerate session token** setelah login/logout

## Testing Logout untuk Semua Role

Untuk memastikan logout bekerja untuk semua role:

1. Login sebagai setiap role (super-admin, warehouse, supervisor, dll)
2. Test logout dari halaman berbeda
3. Pastikan tidak ada error 419 (CSRF) 
4. Pastikan redirect ke landing page setelah logout
5. Pastikan session benar-benar ter-invalidate

## File yang Harus Menggunakan Auth Utils

- `resources/js/Layouts/AdminLayout.svelte` ✅
- `resources/js/Pages/Admin/Dashboard.svelte` ✅
- Semua page/component yang memiliki logout functionality
- Semua component yang perlu authentication check

## Troubleshooting

### Error 419 (CSRF Token Mismatch)
- Pastikan menggunakan `logout()` function dari utils
- Pastikan meta tag CSRF ada di HTML
- Jangan gunakan Inertia `router.post()` untuk logout

### Session tidak ter-invalidate
- Pastikan menggunakan form submission untuk logout
- Pastikan backend controller memanggil `Auth::guard('web')->logout()`

### User masih bisa akses setelah logout
- Pastikan `$request->session()->invalidate()` dipanggil
- Pastikan `$request->session()->regenerateToken()` dipanggil