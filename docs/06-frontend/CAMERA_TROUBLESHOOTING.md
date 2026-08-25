# 📸 Camera Troubleshooting Guide

## Masalah: Camera tidak muncul untuk upload dokumentasi

### Checklist Cepat

1. ✅ **Cek browser console untuk error**
   - Buka Developer Tools (F12)
   - Lihat tab Console
   - Cari error merah

2. ✅ **Pastikan menggunakan HTTPS atau localhost**
   - Camera API hanya bekerja di:
     - `https://` URL
     - `http://localhost`
     - `http://127.0.0.1`
     - `http://ekspedisi-quran.test` (Laravel Herd)

3. ✅ **Cek permission camera**
   - Browser akan minta izin camera
   - Pastikan klik "Allow" / "Izinkan"

---

## Solusi Berdasarkan Masalah

### 1. Toggle Button Tidak Muncul

**Gejala:** Tidak ada tombol "📸 Mode Kamera" atau "📁 Mode Upload"

**Penyebab:**
- Build belum di-run
- Component tidak ter-import

**Solusi:**
```bash
npm run build
php artisan optimize:clear
```

Refresh browser (Ctrl+F5 untuk hard refresh)

---

### 2. Toggle Button Ada, Tapi Klik Tidak Berfungsi

**Gejala:** Button ada tapi tidak terjadi apa-apa saat diklik

**Penyebab:** JavaScript error

**Solusi:**
1. Buka browser console (F12)
2. Cari error merah
3. Screenshot dan kirim ke developer

**Debug Mode:**
```javascript
// Tambahkan di console browser
console.log('useCameraMode:', window.useCameraMode);
```

---

### 3. Mode Kamera Aktif, Tapi Camera Tidak Muncul

**Gejala:** Sudah toggle ke "📸 Mode Kamera" tapi tidak ada tombol "Buka Kamera"

**Penyebab:**
- Component CameraCapture tidak ter-load
- Import path salah

**Solusi:**
```bash
# Clear cache
php artisan config:clear
php artisan view:clear
php artisan cache:clear

# Rebuild frontend
npm run build

# Restart dev server (jika menggunakan)
npm run dev
```

---

### 4. Tombol "Buka Kamera" Ada, Tapi Klik Tidak Ada Respon

**Gejala:** Button "Buka Kamera" ada, tapi klik tidak terjadi apa-apa

**Penyebab:**
- Camera permission belum diminta
- Browser tidak support getUserMedia
- Camera sedang digunakan aplikasi lain

**Solusi:**

**A. Cek Browser Support:**
```javascript
// Test di console browser
if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    console.log('✅ Camera API supported');
} else {
    console.log('❌ Camera API NOT supported');
}
```

**B. Test Camera Manual:**
```javascript
// Test akses camera di console
navigator.mediaDevices.getUserMedia({ video: true })
    .then(stream => {
        console.log('✅ Camera accessible');
        stream.getTracks().forEach(track => track.stop());
    })
    .catch(error => {
        console.error('❌ Camera error:', error);
    });
```

**C. Cek Permission:**
- Chrome: `chrome://settings/content/camera`
- Firefox: Click icon di address bar → Permissions
- Safari: Safari menu → Settings → Websites → Camera

---

### 5. Error: "NotAllowedError: Permission denied"

**Gejala:** Console menampilkan error permission denied

**Penyebab:** User klik "Block" saat diminta camera permission

**Solusi:**

**Chrome:**
1. Click icon 🔒 di address bar
2. Klik "Site settings"
3. Cari "Camera"
4. Ubah ke "Allow"
5. Refresh page

**Firefox:**
1. Click icon di address bar
2. Click "×" di samping camera blocked
3. Click "Allow"
4. Refresh page

**Safari:**
1. Safari menu → Settings for This Website
2. Camera → Allow
3. Refresh page

---

### 6. Error: "NotFoundError: Requested device not found"

**Gejala:** Error device not found

**Penyebab:**
- Camera tidak ada / tidak terdeteksi
- Camera sedang digunakan aplikasi lain
- Driver camera bermasalah

**Solusi:**

**A. Cek Camera Aktif:**
- Windows: Device Manager → Cameras
- Mac: System Preferences → Security & Privacy → Camera
- Linux: `ls /dev/video*`

**B. Tutup Aplikasi Lain:**
- Zoom, Skype, Teams, dll
- Test dengan aplikasi camera lain
- Restart computer jika perlu

**C. Cek Device List:**
```javascript
// List available cameras
navigator.mediaDevices.enumerateDevices()
    .then(devices => {
        const cameras = devices.filter(d => d.kind === 'videoinput');
        console.log('Available cameras:', cameras);
    });
```

---

### 7. Error: "NotReadableError: Could not start video source"

**Gejala:** Camera found tapi tidak bisa start

**Penyebab:**
- Camera sedang digunakan
- Hardware error

**Solusi:**
1. Close semua tab browser yang menggunakan camera
2. Close aplikasi lain yang menggunakan camera
3. Restart browser
4. Restart computer
5. Coba browser lain

---

### 8. Camera Muncul Tapi Gambar Black/Blank

**Gejala:** Video element ada tapi hitam

**Penyebab:**
- Video stream tidak ter-attach
- CSS issue
- Hardware issue

**Solusi:**

**A. Debug Video Element:**
```javascript
// Check di console
const video = document.querySelector('video');
console.log('Video element:', video);
console.log('Video srcObject:', video?.srcObject);
console.log('Video readyState:', video?.readyState);
```

**B. Check CSS:**
- Inspect video element
- Pastikan tidak ada `display: none` atau `visibility: hidden`
- Check z-index

---

### 9. HTTPS Requirement Issue

**Gejala:** Error "getUserMedia is not defined" atau "Camera API not available"

**Penyebab:** Bukan HTTPS

**Solusi:**

**Development:**
```bash
# Laravel Herd sudah otomatis HTTPS
# Jika tidak, gunakan:
php artisan serve --host=localhost

# Atau gunakan ngrok
ngrok http 8000
```

**Production:**
- Pastikan SSL certificate terpasang
- Force HTTPS di .htaccess atau nginx config

---

### 10. Mobile Safari Specific Issues

**Gejala:** Tidak jalan di iPhone/iPad

**Penyebab:** Safari mobile punya quirks khusus

**Solusi:**

**A. Pastikan attribute video lengkap:**
```html
<video autoplay playsinline muted></video>
```

**B. iOS requires user gesture:**
- Camera harus start dari user click, bukan auto-start

**C. Permission di iOS:**
- Settings → Safari → Camera
- Settings → [Your App] → Camera

---

## Debug Checklist

### 1. Browser Console Debugging

```javascript
// 1. Check camera API available
console.log('MediaDevices:', navigator.mediaDevices);

// 2. Check component loaded
console.log('CameraCapture component:', document.querySelector('[data-camera-capture]'));

// 3. Check state
console.log('useCameraMode:', /* check from Svelte devtools */);

// 4. Test camera access
navigator.mediaDevices.getUserMedia({ video: true })
    .then(stream => {
        console.log('✅ Camera works!');
        stream.getTracks().forEach(t => t.stop());
    })
    .catch(err => console.error('❌ Camera error:', err));

// 5. List devices
navigator.mediaDevices.enumerateDevices()
    .then(devices => {
        console.log('All devices:', devices);
        console.log('Cameras:', devices.filter(d => d.kind === 'videoinput'));
    });
```

### 2. Network Debugging

```bash
# Check if using HTTPS
echo $SITE_URL

# Check Laravel Herd
herd status

# Check if port accessible
curl -I http://ekspedisi-quran.test
```

### 3. Build Debugging

```bash
# Check if component exists
ls resources/js/Components/CameraCapture.svelte

# Check build output
npm run build | grep CameraCapture

# Check compiled assets
ls public/build/assets/ | grep admin
```

---

## Quick Fix Commands

```bash
# 1. Clear all cache
php artisan optimize:clear

# 2. Rebuild assets
npm run build

# 3. Clear browser cache
# Chrome: Ctrl+Shift+Delete
# Firefox: Ctrl+Shift+Delete
# Safari: Cmd+Option+E

# 4. Hard refresh
# Ctrl+F5 (Windows/Linux)
# Cmd+Shift+R (Mac)

# 5. Restart Herd
herd restart ekspedisi-quran
```

---

## Test URL

```
# Test page:
http://ekspedisi-quran.test/admin/pengiriman/{id}/update-status-form

# Replace {id} with actual pengiriman ID
```

---

## Browser Compatibility

| Browser | Version | Status |
|---------|---------|--------|
| Chrome | 53+ | ✅ Full Support |
| Firefox | 36+ | ✅ Full Support |
| Safari | 11+ | ✅ Full Support |
| Edge | 79+ | ✅ Full Support |
| Opera | 40+ | ✅ Full Support |
| iOS Safari | 11+ | ✅ Full Support |
| Chrome Android | 53+ | ✅ Full Support |
| Samsung Internet | 6.0+ | ✅ Full Support |

---

## Known Issues

### Issue 1: Laravel Mix vs Vite
Jika menggunakan Laravel Mix (bukan Vite), path import mungkin berbeda.

**Solution:** Pastikan menggunakan Vite (sudah default di Laravel 12)

### Issue 2: Inertia Version
Inertia v1 vs v2 punya perbedaan handling component.

**Current:** Menggunakan Inertia v2 (correct)

### Issue 3: Svelte Version
Svelte 3 vs 4 punya perbedaan syntax.

**Current:** Menggunakan Svelte 4 (correct)

---

## Masih Tidak Jalan?

### 1. Screenshot & Info

Kirimkan:
- Screenshot halaman
- Screenshot browser console (F12)
- Browser + version
- OS + version
- URL yang diakses

### 2. Test Simplified Version

Buat test page sederhana:

```html
<!-- test-camera.html -->
<!DOCTYPE html>
<html>
<head>
    <title>Camera Test</title>
</head>
<body>
    <h1>Camera Test</h1>
    <video id="video" autoplay playsinline style="width: 100%; max-width: 640px;"></video>
    <br>
    <button onclick="startCamera()">Start Camera</button>
    <button onclick="stopCamera()">Stop Camera</button>

    <script>
        let stream = null;

        async function startCamera() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                document.getElementById('video').srcObject = stream;
                console.log('✅ Camera started!');
            } catch (error) {
                console.error('❌ Error:', error);
                alert('Error: ' + error.message);
            }
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
                console.log('Camera stopped');
            }
        }
    </script>
</body>
</html>
```

Akses: `http://ekspedisi-quran.test/test-camera.html`

Jika test page ini jalan, berarti masalahnya di component Svelte.
Jika test page ini tidak jalan, berarti masalahnya di browser/system.

---

## Contact Support

Jika masih tidak terselesaikan:

1. Kumpulkan info debugging:
   - Browser console screenshot
   - Network tab screenshot
   - Component inspector screenshot
   - Browser + OS info

2. Create issue di GitHub dengan template:

```markdown
### Problem
Camera tidak muncul di update dokumentasi

### Environment
- Browser: Chrome 120
- OS: Windows 11
- Laravel: 12
- URL: http://ekspedisi-quran.test

### Console Errors
[paste screenshot atau text]

### Steps Tried
1. Clear cache ✅
2. Rebuild assets ✅
3. Check permissions ✅
4. ...

### Additional Info
[any other relevant info]
```

---

**Last Updated:** 2025-10-11
**Version:** 1.0.0
