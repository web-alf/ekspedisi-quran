# Dashboard Charts Debugging Guide

## 🚨 **Charts Berkedip/Tidak Muncul - Solusi:**

### **Root Cause Analysis:**
1. **Chart Re-initialization Loop** - Charts di-destroy dan di-create ulang terus-menerus
2. **Reactive Statement Overflow** - Data changes trigger infinite updates
3. **Canvas Context Issues** - Multiple charts fighting for same canvas
4. **Chart.js Loading Race Condition** - Library belum fully loaded

### **Fixed Issues:**

#### ✅ **1. Prevented Unnecessary Re-renders**
```javascript
// BEFORE (BERKEDIP):
afterUpdate(() => {
  if (chartsLoaded && Chart) {
    initializeCharts(); // ALWAYS runs = flicker!
  }
});

// AFTER (STABLE):
let lastChartsDataString = '';
$: if (chartsLoaded && Chart && chartsData && !chartsInitialized) {
  const currentChartsDataString = JSON.stringify(chartsData);
  if (currentChartsDataString !== lastChartsDataString) {
    // Only initialize once on first load
    initializeCharts();
    chartsInitialized = true;
  }
}
```

#### ✅ **2. Smart Chart Updates (No Recreation)**
```javascript
// Update existing charts without destroying them
function updateChartsData() {
  if (chartInstances.monthlyShipments) {
    chartInstances.monthlyShipments.data.labels = newLabels;
    chartInstances.monthlyShipments.data.datasets[0].data = newData;
    chartInstances.monthlyShipments.update('none'); // No animation = smooth
  }
}
```

#### ✅ **3. Enhanced Error Handling & Logging**
```javascript
function createMonthlyShipmentsChart() {
  const canvas = document.getElementById('monthlyShipmentsChart');
  if (!canvas || !chartsData?.monthlyShipments?.length) {
    console.log('Canvas or data not available');
    return;
  }

  try {
    // Create chart with reduced animation
    chartInstances.monthlyShipments = new Chart(ctx, {
      // ... config
      options: {
        animation: { duration: 1000 } // Reduced from 1500ms
      }
    });
    console.log('Chart created successfully');
  } catch (error) {
    console.error('Chart creation failed:', error);
  }
}
```

#### ✅ **4. Proper Chart.js Loading**
```javascript
async function loadChartJS() {
  if (!window.Chart) {
    const script = document.createElement('script');
    script.onload = () => {
      Chart = window.Chart;
      chartsLoaded = true;
      // Initialize only ONCE after loading
      setTimeout(() => initializeCharts(), 100);
    };
    script.onerror = () => console.error('Failed to load Chart.js');
    document.head.appendChild(script);
  }
}
```

### **🔧 Debugging Steps:**

#### **Step 1: Check Console Logs**
```javascript
// Add to browser console:
console.log('Charts Loaded:', chartsLoaded);
console.log('Charts Initialized:', chartsInitialized);
console.log('Chart Instances:', Object.keys(chartInstances));
console.log('Charts Data:', chartsData);
```

#### **Step 2: Monitor Chart Lifecycle**
```javascript
// Charts should show these logs in sequence:
// ✅ "Chart.js loaded successfully"
// ✅ "Initializing charts..."
// ✅ "Monthly shipments chart created successfully"
// ✅ "Charts initialized: ['monthlyShipments', 'monthlyRequests', ...]"

// 🚨 RED FLAGS:
// ❌ Multiple "Initializing charts..." (indicates loop)
// ❌ "Canvas or data not available" (data/DOM issues)
// ❌ Chart creation errors
```

#### **Step 3: Verify Data Structure**
```javascript
// Expected chartsData format:
{
  monthlyShipments: [
    { month: "Jan 2024", count: 45 },
    { month: "Feb 2024", count: 52 }
  ],
  monthlyMushafRequests: [
    { month: "Jan 2024", count: 12 }
  ],
  statusDistribution: [
    { name: "Selesai", count: 150 }
  ],
  dailyActivities: [
    { shipments: 5, requests: 3 }
  ]
}
```

#### **Step 4: Force Chart Refresh**
```javascript
// Manual refresh for testing:
function forceRefreshCharts() {
  Object.values(chartInstances).forEach(chart => chart?.destroy());
  chartInstances = {};
  chartsInitialized = false;
  setTimeout(() => initializeCharts(), 500);
}
```

### **📊 Performance Optimizations:**

#### **1. Reduced Animation Duration**
- Monthly Shipments: 1500ms → 1000ms
- Status Distribution: 2000ms → 1500ms  
- Daily Activities: 1500ms → 1000ms

#### **2. Smart Update Strategy**
- Initial Load: Full chart creation
- Data Updates: Update data only, no recreation
- Manual Refresh: Destroy + recreate

#### **3. Memory Management**
- Proper chart cleanup in onDestroy
- Canvas context validation
- Instance tracking

### **🎯 Expected Behavior:**

#### **✅ Charts Should:**
- Load once on page load
- Update smoothly on data refresh
- Show loading states
- Handle empty data gracefully
- Respond to manual refresh

#### **❌ Charts Should NOT:**
- Flicker or disappear repeatedly
- Recreate on every data change
- Show multiple loading states
- Crash on empty data
- Conflict with each other

### **🚨 Troubleshooting Commands:**

```bash
# 1. Check browser console for errors
# 2. Verify Chart.js CDN is accessible
curl -I https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js

# 3. Test with sample data
```

### **✨ Final Result:**
- **No more flickering charts** ✅
- **Smooth data updates** ✅  
- **Proper error handling** ✅
- **Better performance** ✅
- **Real-time updates without recreation** ✅

Charts should now load once and update smoothly without any visual glitches!
