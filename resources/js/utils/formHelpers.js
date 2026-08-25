/**
 * Get input classes with error state
 * @param {Object} errors - Error object from Laravel validation
 * @param {string} field - Field name to check for errors
 * @param {string} baseClasses - Base CSS classes for the input
 * @param {string} errorClasses - Additional CSS classes when there's an error
 * @returns {string} Combined CSS classes
 */
export function getInputClasses(errors = {}, field = '', baseClasses = '', errorClasses = 'border-red-500') {
  const hasError = errors[field] && errors[field].length > 0;
  return hasError ? `${baseClasses} ${errorClasses}` : baseClasses;
}

/**
 * Get first error message for a field
 * @param {Object} errors - Error object from Laravel validation
 * @param {string} field - Field name to get error for
 * @returns {string|null} First error message or null
 */
export function getErrorMessage(errors = {}, field = '') {
  if (!errors[field]) return null;
  return Array.isArray(errors[field]) ? errors[field][0] : errors[field];
}

/**
 * Check if field has any errors
 * @param {Object} errors - Error object from Laravel validation
 * @param {string} field - Field name to check
 * @returns {boolean} True if field has errors
 */
export function hasError(errors = {}, field = '') {
  return errors[field] && errors[field].length > 0;
}

/**
 * Get all error messages as flat array
 * @param {Object} errors - Error object from Laravel validation
 * @returns {Array} Array of all error messages
 */
export function getAllErrors(errors = {}) {
  const allErrors = [];
  Object.values(errors).forEach(errorValue => {
    if (Array.isArray(errorValue)) {
      allErrors.push(...errorValue);
    } else {
      allErrors.push(errorValue);
    }
  });
  return allErrors;
}

/**
 * Standard input classes for forms
 */
export const INPUT_CLASSES = {
  base: 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent transition-colors',
  error: 'border-red-500 focus:ring-red-500',
  success: 'border-green-500 focus:ring-green-500'
};

/**
 * Standard button classes
 */
export const BUTTON_CLASSES = {
  primary: 'bg-[#eb3434] text-white px-4 py-2 rounded-lg hover:bg-red-600 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors',
  secondary: 'bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors',
  danger: 'bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors',
  success: 'bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors'
};

/**
 * Get proper image URL handling both static assets and uploaded files
 * @param {string|null} imagePath - Image path from settings or database
 * @param {string} fallbackPath - Fallback static image path
 * @returns {string} Proper image URL
 */
export function getImageUrl(imagePath, fallbackPath = '') {
  // If no image path provided, use fallback
  if (!imagePath) {
    return fallbackPath;
  }
  
  // If it's already a full URL (starts with http/https), return as-is
  if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
    return imagePath;
  }
  
  // If it starts with /, it's a static asset in public directory
  if (imagePath.startsWith('/')) {
    return imagePath;
  }
  
  // If it starts with images/, it's a static asset in public/images directory
  if (imagePath.startsWith('images/')) {
    return `/${imagePath}`;
  }
  
  // Otherwise, it's an uploaded file in storage
  return `/storage/${imagePath}`;
}
