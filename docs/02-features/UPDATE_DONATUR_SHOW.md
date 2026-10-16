# Update Instructions for Donatur Show Page

## 1. Update the Svelte component at `resources/js/Pages/Admin/Donatur/Show.svelte`

### Add to the props section:
```javascript
export let certificateUrls = {};
```

### Add the copyToClipboard function after the other functions:
```javascript
// Copy to clipboard function
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Link sertifikat berhasil disalin!');
    }).catch(err => {
        console.error('Failed to copy:', err);
    });
}
```

### Add Certificate Section in the sidebar (before the Actions section):
```svelte
<!-- Certificates -->
{#if Object.keys(certificateUrls).length > 0}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Sertifikat Wakaf</h3>
    
    <div class="space-y-3">
        {#each Object.entries(certificateUrls) as [batchId, cert]}
        <div class="border rounded-lg p-3 bg-gray-50">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <p class="text-sm font-medium text-gray-900">{cert.batch_code}</p>
                    <p class="text-xs text-gray-500">No: {cert.nomor_sertifikat}</p>
                </div>
                <span class="text-xs text-gray-500">
                    {new Date(cert.generated_at).toLocaleDateString('id-ID')}
                </span>
            </div>
            <a 
                href={cert.download_url}
                target="_blank"
                class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800"
            >
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Download Sertifikat
            </a>
            <button
                on:click={() => copyToClipboard(cert.download_url)}
                class="ml-3 text-sm text-gray-600 hover:text-gray-800"
            >
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
                Copy Link
            </button>
        </div>
        {/each}
    </div>
</div>
{/if}
```

## 2. To see the certificate links:

1. Go to Admin Dashboard → Donatur
2. Click on any donatur to view details
3. Certificate links will appear in the right sidebar if certificates exist for that donatur

## 3. The certificate download URL format:
```
https://yourdomain.com/certificate/download/{token}
```

This URL:
- Is valid for 24 hours
- Can be shared with the donatur
- Generates the PDF on-demand when clicked
- Does not store any files on the server
```