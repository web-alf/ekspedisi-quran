/**
 * Assign Target Utility Functions
 * Handle target assignment for warehouse staff
 */

/**
 * Assign daily target to warehouse user
 */
export async function assignTarget(userId, target) {
  try {
    const response = await fetch('/admin/supervisor/assign-target', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({
        user_id: userId,
        target: target
      })
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || 'Gagal assign target');
    }

    return {
      success: true,
      data: data.data,
      message: data.message
    };

  } catch (error) {
    console.error('Assign target error:', error);
    return {
      success: false,
      message: error.message || 'Terjadi kesalahan saat assign target'
    };
  }
}

/**
 * Get warehouse users list
 */
export async function getWarehouseUsers() {
  try {
    const response = await fetch('/admin/supervisor/warehouse-users', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      }
    });

    if (!response.ok) {
      throw new Error('Gagal mengambil data warehouse users');
    }

    const data = await response.json();
    return {
      success: true,
      users: data.users || []
    };

  } catch (error) {
    console.error('Get warehouse users error:', error);
    return {
      success: false,
      message: error.message || 'Terjadi kesalahan saat mengambil data users',
      users: []
    };
  }
}

/**
 * Show success notification
 */
export function showSuccessNotification(message) {
  // Create inline notification instead of floating
  const notification = document.createElement('div');
  notification.className = 'mb-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg';
  notification.innerHTML = `
    <div class="flex items-center">
      <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
      </svg>
      <span class="font-medium">${message}</span>
    </div>
  `;

  // Insert at the beginning of content area instead of floating
  const contentArea = document.querySelector('main, [role="main"], .main-content') || document.body;
  const firstChild = contentArea.firstElementChild;
  if (firstChild) {
    contentArea.insertBefore(notification, firstChild);
  } else {
    contentArea.appendChild(notification);
  }

  // Auto-remove after delay
  setTimeout(() => {
    if (notification.parentNode) {
      notification.remove();
    }
  }, 5000);
}

/**
 * Show error notification
 */
export function showErrorNotification(message) {
  // Create inline notification instead of floating
  const notification = document.createElement('div');
  notification.className = 'mb-4 bg-red-500 text-white px-6 py-4 rounded-lg shadow-lg';
  notification.innerHTML = `
    <div class="flex items-center">
      <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
      </svg>
      <span class="font-medium">${message}</span>
    </div>
  `;

  // Insert at the beginning of content area instead of floating
  const contentArea = document.querySelector('main, [role="main"], .main-content') || document.body;
  const firstChild = contentArea.firstElementChild;
  if (firstChild) {
    contentArea.insertBefore(notification, firstChild);
  } else {
    contentArea.appendChild(notification);
  }

  // Auto-remove after delay
  setTimeout(() => {
    if (notification.parentNode) {
      notification.remove();
    }
  }, 6000);
}

/**
 * Format target display with carry-over info
 */
export function formatTargetDisplay(customTarget, totalTarget, carryOver = 0) {
  if (carryOver > 0) {
    return `${customTarget} + ${carryOver} carry-over = ${totalTarget} mushaf`;
  }
  return `${totalTarget} mushaf`;
}

/**
 * Validate target input
 */
export function validateTarget(target) {
  const errors = [];
  
  if (!target) {
    errors.push('Target tidak boleh kosong');
  } else if (isNaN(target) || target < 1) {
    errors.push('Target minimal 1 mushaf');
  } else if (target > 200) {
    errors.push('Target maksimal 200 mushaf');
  }
  
  return {
    isValid: errors.length === 0,
    errors
  };
}

/**
 * Get recommended target based on user performance
 */
export function getRecommendedTarget(userPerformance = {}) {
  const defaultTarget = 80;
  
  if (!userPerformance.avg_daily_achieved) {
    return defaultTarget;
  }
  
  const avgAchieved = userPerformance.avg_daily_achieved;
  const achievementRate = userPerformance.achievement_rate || 0;
  
  // If user consistently achieves their target, suggest slightly higher
  if (achievementRate >= 90) {
    return Math.min(200, Math.ceil(avgAchieved * 1.1));
  }
  
  // If user struggles, suggest lower target
  if (achievementRate < 70) {
    return Math.max(40, Math.ceil(avgAchieved * 0.9));
  }
  
  // Otherwise, stick to their average achievement
  return Math.ceil(avgAchieved);
}