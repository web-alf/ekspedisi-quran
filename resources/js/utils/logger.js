/**
 * Environment-aware logger utility
 * Only logs in development mode
 */

const isDevelopment = () => {
  return import.meta.env.DEV || import.meta.env.MODE === 'development';
};

export const logger = {
  log: (...args) => {
    if (isDevelopment()) {
      console.log(...args);
    }
  },
  
  error: (...args) => {
    // Always log errors, even in production
    console.error(...args);
  },
  
  warn: (...args) => {
    if (isDevelopment()) {
      console.warn(...args);
    }
  },
  
  debug: (...args) => {
    if (isDevelopment()) {
      console.debug(...args);
    }
  },
  
  info: (...args) => {
    if (isDevelopment()) {
      console.info(...args);
    }
  }
};

// For backward compatibility
export default logger;