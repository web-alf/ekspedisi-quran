# Update Instructions for Public Tracking Result Page

## 1. Update the Svelte component at `resources/js/Pages/Public/TrackingResult.svelte`

### Add to the props section:
```javascript
export let certificateUrls = [];
```

### Add Certificate Section (add this after the main tracking information):
```svelte
<!-- Certificate Download Section -->
{#if certificateUrls && certificateUrls.length > 0}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
        <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
            <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
            <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/>
        </svg>
        Sertifikat Wakaf
    </h3>
    
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
        <p class="text-sm text-green-800">
            <strong>Selamat!</strong> Sertifikat wakaf Anda telah tersedia. Klik tombol di bawah untuk mengunduh.
        </p>
    </div>
    
    <div class="space-y-3">
        {#each certificateUrls as cert}
        <div class="border rounded-lg p-4 bg-gray-50">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <h4 class="font-medium text-gray-900">Batch: {cert.batch_code}</h4>
                    <p class="text-sm text-gray-600">Nomor Sertifikat: {cert.nomor_sertifikat}</p>
                    <p class="text-xs text-gray-500">
                        Dibuat: {new Date(cert.generated_at).toLocaleDateString('id-ID', { 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric' 
                        })}
                    </p>
                </div>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-2">
                <a 
                    href={cert.download_url}
                    target="_blank"
                    class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download Sertifikat
                </a>
                
                <button
                    on:click={() => copyToClipboard(cert.download_url)}
                    class="inline-flex items-center justify-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    Copy Link
                </button>
            </div>
        </div>
        {/each}
    </div>
    
    <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
        <p class="text-sm text-blue-800">
            <strong>Catatan:</strong> Link download berlaku selama 24 jam. Jika link kedaluwarsa, silakan hubungi admin untuk mendapatkan link baru.
        </p>
    </div>
</div>
{/if}
```

### Add the copyToClipboard function:
```javascript
// Copy to clipboard function
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Link sertifikat berhasil disalin ke clipboard!');
    }).catch(err => {
        console.error('Failed to copy:', err);
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('Link sertifikat berhasil disalin ke clipboard!');
    });
}
```

## 2. How it works:

1. **Customer tracks their order** by entering their no_resi
2. **If certificates exist** for that donatur, they will see certificate download links
3. **Links are valid for 24 hours** - perfect for customer use
4. **PDF is generated on-demand** when they click download
5. **No server storage** - efficient and scalable

## 3. Customer Experience:

1. Go to tracking page: `https://yourdomain.com/tracking`
2. Enter their no_resi (tracking number)
3. See their tracking info + certificate download links
4. Click "Download Sertifikat" to get their certificate
5. Can also copy the link to share or save

This provides a seamless customer experience where they can both track their order AND download their certificate from the same place!