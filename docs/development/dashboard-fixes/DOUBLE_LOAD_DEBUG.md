# Debug Charts Double Loading Issue

## 🕵️ **Root Cause Analysis:**

Dari console logs yang Anda berikan:
```
Chart.js loaded successfully
Initializing charts...
[4 charts created successfully]
Charts initialized: (4) [...]
Initializing charts...  ← DUPLICATE!
[4 charts created again]
Charts initialized: (4) [...]  ← DUPLICATE!
```

### **Possible Causes:**

#### 1. **Reactive Statement Double Trigger**
```javascript
// Problem: Data changes twice during page load
$: if (chartsLoaded && Chart && chartsData && !chartsInitialized) {
  // This reactive block might run twice if chartsData changes
}
```

#### 2. **Component Re-mount**
- Svelte component mungkin di-mount dua kali
- Router navigation bisa trigger double mount

#### 3. **Data Stream Issues**  
- `chartsData` prop berubah dua kali saat page load
- Auto-refresh conflict dengan initial load

## 🔧 **Applied Fixes:**

### **Fix 1: Enhanced Debugging**
```javascript
$: if (chartsLoaded && Chart && chartsData && !chartsInitialized) {
  console.log('Reactive check:', { 
    chartsLoaded, hasChart: !!Chart, hasData: !!chartsData, 
    chartsInitialized, dataChanged, dataNotEmpty
  });
  // This will show us EXACTLY when and why reactive runs
}
```

### **Fix 2: Timeout Protection**
```javascript
let initializationTimeout = null;

// Clear existing timeout before setting new one
if (initializationTimeout) {
  clearTimeout(initializationTimeout);
  console.log('Cleared existing initialization timeout');
}
```

### **Fix 3: Double-check Prevention**
```javascript
initializationTimeout = setTimeout(() => {
  if (!chartsInitialized) { 
    console.log('About to initialize charts...');
    initializeCharts();
    chartsInitialized = true;
  } else {
    console.log('Charts already initialized, skipping...');
  }
}, 300);
```

### **Fix 4: Initialization Guard**
```javascript
function initializeCharts() {
  if (!Chart || !chartsLoaded || chartsInitialized) {
    console.log('Charts already initialized or Chart.js not ready');
    return;
  }
  // Proceed with initialization
}
```

## 🎯 **Next Debug Steps:**

### **Step 1: Check New Console Output**
Should now see:
```
Chart.js loaded successfully
Reactive check: { chartsLoaded: true, hasChart: true, ... }
About to initialize charts...
Initializing charts...
[Charts created]
Charts initialized: (4) [...]

// Should NOT see duplicate "Reactive check" or "About to initialize"
```

### **Step 2: Identify Root Cause**
If still double-loading, check:
```javascript
// Add this in browser console to monitor data changes:
let dataWatcher = setInterval(() => {
  console.log('Data state:', {
    chartsLoaded: window.chartsLoaded,
    chartsInitialized: window.chartsInitialized,
    hasChartsData: !!window.chartsData
  });
}, 1000);

// Stop watching after 10 seconds:
setTimeout(() => clearInterval(dataWatcher), 10000);
```

### **Step 3: Manual Control Test**
```javascript
// Force reset and single initialization:
window.chartsInitialized = false;
window.chartInstances = {};
// Then trigger manual refresh to see if it happens once
```

## 🚨 **Expected Fixed Behavior:**

### ✅ **Single Load Process:**
1. Page loads → `chartsLoaded = true`
2. Data arrives → Reactive check (once)
3. Timeout triggers → Initialize once
4. `chartsInitialized = true` → Blocks future inits

### ✅ **Data Updates:**
1. Auto-refresh → Data changes
2. `chartsInitialized = true` → Skip initialization
3. Update function → Smooth chart updates

### ❌ **No More Double Loading:**
- Reactive statement should run only once per page load
- Timeout should clear previous attempts
- Guard conditions should prevent duplicate initialization

## 📊 **Monitor These Patterns:**

| Event | Expected Log | Problem Log |
|-------|-------------|-------------|
| Page Load | Single "Reactive check" | Multiple "Reactive check" |
| Data Arrive | "About to initialize..." | Multiple initialization calls |
| Charts Created | One set of 4 charts | Two sets of 4 charts |
| Auto Refresh | "Updating charts data" | "Initializing charts..." |

Coba test lagi dan lihat console output yang baru - sekarang kita akan tahu persis kapan dan mengapa charts di-load dua kali! 🔍
