/**
 * Authentication utilities for consistent CSRF handling across all user roles
 */

import { router } from '@inertiajs/svelte';

/**
 * Safely logout with proper CSRF token handling
 * This function works for all user roles (super-admin, warehouse, supervisor, etc.)
 * Uses multiple fallback methods to ensure reliable logout
 */
export function logout() {
    // Debug logging to understand the current state
    console.log('Logout function called with debug info:', {
        pathname: window.location.pathname,
        referrer: document.referrer,
        sessionFlag: sessionStorage.getItem('just_logged_in'),
        navigationType: performance.navigation?.type,
        timeSinceLoad: Date.now() - (performance.timing?.loadEventEnd || performance.timeOrigin)
    });
    
    // Check if we're on dashboard route AND came from login (post-login scenario)
    const isPostLogin = window.location.pathname === '/dashboard' && 
                       (document.referrer.includes('/login') || 
                        sessionStorage.getItem('just_logged_in') === 'true');
    
    // Check various indicators of fresh page load vs navigation
    const isPageFreshlyLoaded = performance.navigation?.type === 1 || 
                               !document.referrer || 
                               window.location.pathname === '/dashboard';
    
    // Check if page was loaded less than 10 seconds ago (extended window for login redirect)
    const pageLoadTime = performance.timing?.loadEventEnd || performance.timeOrigin;
    const timeSinceLoad = Date.now() - pageLoadTime;
    const isRecentPageLoad = timeSinceLoad < 10000; // Extended to 10 seconds
    
    // For safety, ALWAYS use GET logout on dashboard page to avoid CSRF timing issues
    const isDashboardPage = window.location.pathname === '/dashboard';
    
    // Always use GET logout for post-login scenarios to avoid CSRF timing issues
    if (isPostLogin || isPageFreshlyLoaded || isRecentPageLoad || isDashboardPage) {
        console.log('Post-login or fresh page load or dashboard page detected, using GET logout to avoid CSRF timing issues', {
            isPostLogin,
            isPageFreshlyLoaded, 
            isRecentPageLoad,
            isDashboardPage
        });
        // Clear the post-login flag
        sessionStorage.removeItem('just_logged_in');
        logoutWithGet();
        return;
    }
    
    // For navigated pages, try Inertia logout first
    try {
        router.post('/logout', {}, {
            onError: (errors) => {
                console.warn('Inertia logout failed, trying GET fallback:', errors);
                // If Inertia fails, try GET request (no CSRF needed)
                logoutWithGet();
            },
            onSuccess: () => {
                console.log('Logout successful via Inertia');
            }
        });
    } catch (error) {
        console.warn('Inertia router not available, using GET method:', error);
        logoutWithGet();
    }
}

/**
 * Logout using GET request (bypasses CSRF)
 */
function logoutWithGet() {
    console.log('Attempting logout via GET request');
    window.location.href = '/logout';
}

/**
 * Refresh CSRF token then perform logout
 */
async function refreshCsrfTokenAndLogout() {
    try {
        // Make a GET request to refresh the session and CSRF token
        const response = await fetch('/dashboard', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (response.ok) {
            // Extract updated CSRF token from response
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newToken = doc.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            if (newToken) {
                // Update the current page's CSRF token
                const currentMeta = document.querySelector('meta[name="csrf-token"]');
                if (currentMeta) {
                    currentMeta.setAttribute('content', newToken);
                }
                
                // Update axios default headers
                if (window.axios) {
                    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = newToken;
                }
                
                console.log('CSRF token refreshed successfully');
            }
        }
    } catch (error) {
        console.warn('Failed to refresh CSRF token:', error.message);
    }
    
    // Proceed with logout regardless of token refresh success
    logoutWithForm();
}

/**
 * Logout using form submission with current CSRF token
 */
function logoutWithForm() {
    const token = getCsrfToken();
    
    if (!token) {
        console.error('No CSRF token available, redirecting to login');
        window.location.href = '/login';
        return;
    }
    
    console.log('Logout with form - CSRF token length:', token.length);
    
    // Create form for logout
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/logout';
    form.style.display = 'none';
    
    // Add CSRF token
    const tokenInput = document.createElement('input');
    tokenInput.type = 'hidden';
    tokenInput.name = '_token';
    tokenInput.value = token;
    form.appendChild(tokenInput);
    
    // Add method override for DELETE if needed
    const methodInput = document.createElement('input');
    methodInput.type = 'hidden';
    methodInput.name = '_method';
    methodInput.value = 'POST';
    form.appendChild(methodInput);
    
    // Submit form
    document.body.appendChild(form);
    
    // Add error handling
    form.addEventListener('submit', function(e) {
        console.log('Form submitted for logout');
    });
    
    form.submit();
}

/**
 * Get CSRF token from meta tag or cookie
 */
export function getCsrfToken() {
    // First try meta tag
    let token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    if (!token) {
        // Try to get from cookie as fallback
        const xsrfToken = document.cookie
            .split('; ')
            .find(row => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];
        
        if (xsrfToken) {
            // Decode the token from cookie (it's URL encoded)
            token = decodeURIComponent(xsrfToken);
        }
    }
    
    return token || '';
}

/**
 * Check if user is authenticated based on current page props
 */
export function isAuthenticated(pageProps) {
    return !!(pageProps?.auth?.user?.id);
}

/**
 * Get current user role
 */
export function getCurrentUserRole(pageProps) {
    return pageProps?.auth?.user?.role || null;
}

/**
 * Check if current user has specific role
 */
export function hasRole(pageProps, role) {
    return getCurrentUserRole(pageProps) === role;
}

/**
 * Check if current user has any of the specified roles
 */
export function hasAnyRole(pageProps, roles) {
    const userRole = getCurrentUserRole(pageProps);
    return Array.isArray(roles) ? roles.includes(userRole) : false;
}