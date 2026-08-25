# WhatsApp wa.me Mobile Support

## Overview
wa.me adalah layanan URL shortener resmi dari WhatsApp yang mendukung semua platform dengan optimization khusus untuk mobile devices.

## Dukungan Platform

### Mobile Devices (iOS & Android)
- **Behavior**: Membuka WhatsApp app langsung jika terinstall
- **Fallback**: Redirect ke App Store/Play Store jika app tidak terinstall
- **Format**: `https://wa.me/6281234567890`
- **With Message**: `https://wa.me/6281234567890?text=Hello%20World`

### Desktop/Web
- **Behavior**: Membuka WhatsApp Web atau Desktop app
- **Format**: `https://wa.me/6281234567890` atau `https://api.whatsapp.com/send/`
- **Requirement**: User harus login ke WhatsApp Web

## URL Formats Comparison

### 1. Basic wa.me (Recommended untuk mobile)
```
https://wa.me/6281234567890
```
- ✅ Paling simple dan universal
- ✅ Optimal untuk mobile
- ✅ Auto-detect device type

### 2. wa.me dengan pesan
```
https://wa.me/6281234567890?text=Hello%20World
```
- ✅ Pre-fill pesan
- ✅ Encoding otomatis
- ✅ Mobile-friendly

### 3. API WhatsApp (Untuk desktop)
```
https://api.whatsapp.com/send/?phone=6281234567890&text=Hello&type=phone_number&app_absent=0
```
- ✅ Lebih banyak parameter kontrol
- ✅ Better untuk WhatsApp Web
- ❌ Sedikit lebih kompleks

## Implementation dalam Project

### Current Implementation
```javascript
// Format nomor telepon untuk WhatsApp
function formatPhoneForWhatsApp(phone) {
  if (!phone) return '6281234567890';
  
  let cleanPhone = phone.replace(/\D/g, '');
  
  // Convert Indonesian formats
  if (cleanPhone.startsWith('08')) {
    cleanPhone = '62' + cleanPhone.substring(1);
  }
  // ... other conversions
  
  return cleanPhone;
}

// Create optimal URL berdasarkan device
function createWhatsAppUrl(phone, message = '') {
  const formattedPhone = formatPhoneForWhatsApp(phone);
  const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  
  if (message) {
    if (isMobile) {
      return `https://wa.me/${formattedPhone}?text=${encodeURIComponent(message)}`;
    } else {
      return `https://api.whatsapp.com/send/?phone=${formattedPhone}&text=${encodeURIComponent(message)}&type=phone_number&app_absent=0`;
    }
  } else {
    return `https://wa.me/${formattedPhone}`;
  }
}
```

### Usage Examples

#### 1. Simple Contact Button
```svelte
<a href="https://wa.me/{formatPhoneForWhatsApp(settings.contact_phone)}" target="_blank">
  Contact WhatsApp
</a>
```

#### 2. With Pre-filled Message
```javascript
const message = "Halo, saya ingin bertanya tentang layanan Anda";
const waUrl = createWhatsApp(settings.contact_phone, message);
window.location.href = waUrl; // Mobile
// atau
window.open(waUrl, '_blank'); // Desktop
```

## Mobile Optimization Features

### 1. Device Detection
- Otomatis detect mobile vs desktop
- Different URL format untuk optimal experience
- Fallback handling jika app tidak terinstall

### 2. Deep Linking
- Mobile: Direct ke WhatsApp app
- iOS: Melalui Universal Links
- Android: Melalui Intent system

### 3. Progressive Enhancement
```javascript
// Check WhatsApp app availability
if (navigator.userAgent.includes('WhatsApp')) {
  // User sedang di WhatsApp in-app browser
  // Use different approach
}
```

## Best Practices

### 1. Phone Number Format
- ✅ **Always use international format**: `6281234567890`
- ❌ **Avoid local format**: `081234567890`
- ❌ **Avoid formatted numbers**: `+62 812-3456-7890`

### 2. Message Encoding
- ✅ **Use encodeURIComponent()** untuk pesan
- ✅ **Keep messages concise** untuk mobile
- ✅ **Test dengan karakter khusus** dan emoji

### 3. User Experience
- ✅ **Open dalam same tab** di mobile
- ✅ **Open dalam new tab** di desktop
- ✅ **Provide fallback** jika WhatsApp tidak tersedia

### 4. Error Handling
```javascript
function openWhatsApp(phone, message = '') {
  try {
    const url = createWhatsAppUrl(phone, message);
    const isMobile = /Mobile|Android|iPhone/i.test(navigator.userAgent);
    
    if (isMobile) {
      window.location.href = url;
    } else {
      window.open(url, '_blank');
    }
  } catch (error) {
    console.error('Failed to open WhatsApp:', error);
    // Fallback: copy phone number to clipboard
    navigator.clipboard.writeText(formatPhoneForWhatsApp(phone));
    alert('WhatsApp link tidak dapat dibuka. Nomor telah disalin ke clipboard.');
  }
}
```

## Testing

### Mobile Testing
1. **iOS Safari**: Test dengan iPhone/iPad
2. **Android Chrome**: Test dengan Android device
3. **WhatsApp In-app Browser**: Test dari dalam WhatsApp
4. **Different WhatsApp versions**: Test dengan versi berbeda

### Desktop Testing
1. **Chrome**: Test WhatsApp Web integration
2. **Firefox**: Test popup blocker handling
3. **Safari**: Test macOS WhatsApp app integration
4. **Edge**: Test Windows integration

## Troubleshooting

### Common Issues

#### 1. Link tidak membuka WhatsApp
- **Cause**: Format nomor salah
- **Solution**: Pastikan format internasional (+62)

#### 2. Pesan tidak ter-prefill
- **Cause**: Encoding issue atau URL terlalu panjang
- **Solution**: Gunakan `encodeURIComponent()` dan batasi panjang pesan

#### 3. Desktop tidak buka WhatsApp Web
- **Cause**: Popup blocker atau user belum login
- **Solution**: Instruksikan user untuk allow popup dan login WhatsApp Web

## Conclusion

wa.me sangat mendukung mobile dan merupakan cara terbaik untuk membuat WhatsApp links yang universal. Dengan implementation yang tepat, users dapat langsung terhubung ke WhatsApp dari website tanpa hambatan teknis.

## References
- [WhatsApp Business API Documentation](https://developers.facebook.com/docs/whatsapp)
- [wa.me URL Scheme](https://faq.whatsapp.com/5913398998672934)
- [WhatsApp Click to Chat](https://faq.whatsapp.com/196924045043849)
