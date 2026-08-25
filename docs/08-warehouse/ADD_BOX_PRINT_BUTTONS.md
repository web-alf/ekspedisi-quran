# Manual Fix: Add Print Buttons for Box Labels

## Issue
The BoxTracking/Index.svelte file has root ownership and cannot be edited automatically. The thermal print system for box labels exists but is not exposed in the UI.

## Files Requiring Manual Edit

### `/resources/js/Pages/Admin/BoxTracking/Index.svelte`

**Permission Issue**: File owned by root, needs manual permission fix

**Change Required**: Add print button functionality to the actions column

#### Step 1: Add print function to script section
Add this function after the existing functions (around line 75):

```javascript
function printBoxLabel(boxId) {
  window.open(`/admin/thermal-print/box/${boxId}`, '_blank');
}
```

#### Step 2: Update the actions column in the table
Replace the current actions cell (around lines 281-290):

**FROM:**
```svelte
<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
  {#if canRead}
    <button
      on:click={() => viewBoxDetail(box.id)}
      class="text-[#eb3434] hover:text-red-700 font-medium"
    >
      Detail
    </button>
  {/if}
</td>
```

**TO:**
```svelte
<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
  <div class="flex space-x-2 justify-end">
    {#if canRead}
      <button
        on:click={() => viewBoxDetail(box.id)}
        class="text-[#eb3434] hover:text-red-700 font-medium"
      >
        Detail
      </button>
    {/if}
    <button
      on:click={() => printBoxLabel(box.id)}
      class="text-blue-600 hover:text-blue-800 font-medium"
      title="Print Label Kerdus"
    >
      Print
    </button>
  </div>
</td>
```

## System Information

✅ **Thermal Print System Already Exists**:
- Backend: `/app/Http/Controllers/Admin/ThermalPrintController.php`
- Routes: `/admin/thermal-print/box/{packingBox}`
- Template: `/resources/views/admin/thermal-print/box-label-100x150.blade.php`
- Features: QR codes, box info, contents list, professional 100×150mm format

## Manual Steps Required

1. **Fix file permissions:**
   ```bash
   sudo chown agus:staff /Users/agus/Herd/ekspedisi-quran/resources/js/Pages/Admin/BoxTracking/Index.svelte
   ```

2. **Apply the code changes above**

3. **Test the print functionality:**
   - Go to Admin → Box Tracking
   - Click "Print" button on any box
   - Verify thermal print label opens in new window

## Expected Result

After applying these changes:
- Each box in the tracking table will have a "Print" button
- Clicking Print opens the thermal label in a new window  
- The label includes QR code, box details, contents, and is formatted for 100×150mm thermal printers
- Users can print professional box labels directly from the tracking interface

## Additional Fix Needed: Box Detail Page

### `/resources/js/Pages/Admin/BoxTracking/Show.svelte`

**Permission Issue**: File owned by root, needs manual permission fix

**Change Required**: Update the existing `printBoxLabel()` function to use thermal print endpoint

#### Replace the entire `printBoxLabel()` function (around lines 37-125):

**FROM:**
```javascript
function printBoxLabel() {
  const printContent = `
    <!DOCTYPE html>
    <html>
      // ... very long HTML content for old print layout ...
  `;
  
  const printWindow = window.open('', '_blank');
  printWindow.document.write(printContent);
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
  }, 500);
}
```

**TO:**
```javascript
function printBoxLabel() {
  window.open(`/admin/thermal-print/box/${box.id}`, '_blank');
}
```

This will replace the old A4 print layout with the new thermal print system.

## Files That Need Permission Fix

Run this command to fix file permissions:
```bash
sudo chown -R agus:staff /Users/agus/Herd/ekspedisi-quran/resources/js/Pages/Admin/BoxTracking/
```