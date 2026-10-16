# 📸 Camera Tidak Muncul - Quick Fix

## ✅ Solusi Cepat

### 1. **Clear Cache & Rebuild** (Paling Sering Berhasil)

```bash
# 1. Clear Laravel cache
php artisan optimize:clear

# 2. Rebuild frontend assets
npm run build

# 3. Restart Laravel Herd
herd restart ekspedisi-quran
```

### 2. **Hard Refresh Browser**

Setelah rebuild, lakukan **hard refresh** di browser:

- **Windows/Linux**: `Ctrl + F5` atau `Ctrl + Shift + R`
- **Mac**: `Cmd + Shift + R`
- **Alternative**: Clear browser cache manual:
  - Chrome: `Ctrl/Cmd + Shift + Delete` → Clear cached images and files

---

## 🧪 Test Camera dengan Test Page

Akses test page untuk memastikan camera API bekerja:

```
http://ekspedisi-quran.test/test-camera.html
```

**Fitur Test Page:**
- ✅ Test browser API support
- ✅ List available cameras
- ✅ Start/stop camera
- ✅ Capture photos
- ✅ Detailed console logs

**Jika test page berfungsi** = Camera API OK, masalah di component
**Jika test page gagal** = Camera API/permission issue

---

## 🔍 Troubleshooting Berdasarkan Gejala

### Gejala 1: Tombol "Mode Kamera" Tidak Muncul

**Penyebab**: Build assets belum ter-update

**Solusi**:
```bash
npm run build
php artisan optimize:clear
```

Refresh browser dengan `Ctrl+F5`

---

### Gejala 2: Tombol Ada Tapi Klik Tidak Respon

**Penyebab**: JavaScript error

**Cara Cek**:
1. Buka Developer Tools (F12)
2. Tab **Console**
3. Cari error merah

**Solusi Umum**:
```bash
# Rebuild frontend
npm run build

# Clear browser cache
# Chrome: Ctrl+Shift+Delete
```

---

### Gejala 3: Klik Mode Kamera, Tapi Tidak Ada Tombol "Buka Kamera"

**Penyebab**: Component CameraCapture tidak loaded

**Solusi**:
```bash
# Check if component exists
ls resources/js/Components/CameraCapture.svelte

# If exists, rebuild
npm run build
php artisan optimize:clear
```

Hard refresh browser (Ctrl+F5)

---

### Gejala 4: Tombol "Buka Kamera" Ada, Tapi Klik Tidak Muncul Video

**Penyebab**: Camera permission atau HTTPS issue

**Cara Cek Permission**:

**Chrome:**
1. Klik icon 🔒 di address bar
2. Check Camera permission
3. Pastikan "Allow"

**Firefox:**
1. Klik icon di address bar
2. Check Camera permission

**Safari:**
1. Safari → Settings for This Website
2. Camera → Allow

**Solusi HTTPS**:
```bash
# Laravel Herd sudah otomatis HTTPS
# Pastikan menggunakan:
http://ekspedisi-quran.test  # Bukan IP address
```

---

### Gejala 5: Error "Permission Denied"

**Penyebab**: User block camera permission

**Solusi Chrome**:
1. Klik 🔒 di address bar
2. Site settings
3. Camera → Allow
4. Refresh page

**Solusi Firefox**:
1. Klik icon di address bar
2. Click "×" di samping camera blocked
3. Click "Allow"
4. Refresh page

---

### Gejala 6: Error "Device Not Found"

**Penyebab**:
- Camera tidak ada/tidak terdeteksi
- Camera dipakai aplikasi lain

**Solusi**:
1. **Close aplikasi lain** yang pakai camera:
   - Zoom, Skype, Teams, Google Meet
   - WhatsApp Desktop, Telegram

2. **Restart browser** setelah close aplikasi

3. **Test dengan aplikasi camera lain** untuk pastikan camera bekerja

4. **Restart computer** jika perlu

---

## 🎯 Quick Checklist

Jalankan checklist ini secara berurutan:

- [ ] **Rebuild assets**: `npm run build`
- [ ] **Clear cache**: `php artisan optimize:clear`
- [ ] **Hard refresh**: `Ctrl+F5` atau `Cmd+Shift+R`
- [ ] **Test camera API**: Akses `http://ekspedisi-quran.test/test-camera.html`
- [ ] **Check browser console** (F12) untuk error
- [ ] **Verify HTTPS**: Pastikan URL pakai `https://` atau `.test` domain
- [ ] **Check camera permission**: Allow camera di browser
- [ ] **Close aplikasi lain** yang pakai camera
- [ ] **Try different browser** (Chrome, Firefox, Edge)

---

## 🌐 Browser Compatibility

| Browser | Min Version | Status |
|---------|-------------|--------|
| Chrome | 53+ | ✅ Recommended |
| Firefox | 36+ | ✅ Good |
| Safari | 11+ | ✅ Good |
| Edge | 79+ | ✅ Good |

**Recommended**: Chrome atau Edge (Chromium-based)

---

## 🔧 Manual Debug dengan Console

Buka browser console (F12) dan test:

### 1. Test API Support
```javascript
// Check if API exists
console.log('MediaDevices:', navigator.mediaDevices);
console.log('getUserMedia:', navigator.mediaDevices?.getUserMedia);

// Check secure context
console.log('Secure Context:', window.isSecureContext);
console.log('Protocol:', window.location.protocol);
```

### 2. Test Camera Access
```javascript
// Try to access camera
navigator.mediaDevices.getUserMedia({ video: true })
    .then(stream => {
        console.log('✅ Camera works!');
        console.log('Track:', stream.getVideoTracks()[0]);
        // Stop stream
        stream.getTracks().forEach(t => t.stop());
    })
    .catch(err => {
        console.error('❌ Camera error:', err.name, err.message);
    });
```

### 3. List Available Cameras
```javascript
// List all cameras
navigator.mediaDevices.enumerateDevices()
    .then(devices => {
        const cameras = devices.filter(d => d.kind === 'videoinput');
        console.log('Total cameras:', cameras.length);
        cameras.forEach((c, i) => {
            console.log(`Camera ${i+1}:`, c.label, c.deviceId);
        });
    });
```

---

## 📞 Jika Masih Tidak Berhasil

Kumpulkan informasi berikut:

1. **Screenshot browser console** (F12 → Console tab)
2. **Screenshot halaman** (tampilkan URL)
3. **Browser info**: Nama + versi (contoh: Chrome 120)
4. **OS info**: Windows 11 / macOS Sonoma / dll
5. **URL yang diakses**: Copy paste URL lengkap
6. **Result dari test page**: Screenshot `/test-camera.html`

---

## ✨ Expected Behavior (Kalau Sukses)

1. **Buka halaman update status**: `/admin/pengiriman/{id}/update-status-form`
2. **Klik toggle button**: "📁 Mode Upload" → berubah jadi "📸 Mode Kamera"
3. **Muncul tombol**: "📸 Buka Kamera"
4. **Klik "Buka Kamera"**:
   - Browser minta permission camera
   - Klik "Allow"
   - Video preview muncul
5. **Klik tombol capture** (lingkaran putih besar)
6. **Photo muncul di preview**
7. **Submit form** → photo uploaded

---

**Last Updated**: 2025-10-11
**Test Page**: http://ekspedisi-quran.test/test-camera.html
**Documentation**: docs/CAMERA_TROUBLESHOOTING.md
