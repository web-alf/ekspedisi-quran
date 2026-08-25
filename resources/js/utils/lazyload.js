/**
 * Lazy loading utility for images and components
 */

/**
 * Create intersection observer for lazy loading images
 * @param {Function} callback - Callback when element is visible
 * @param {Object} options - Intersection observer options
 * @returns {IntersectionObserver}
 */
export function createLazyLoadObserver(callback, options = {}) {
    const defaultOptions = {
        root: null,
        rootMargin: '50px',
        threshold: 0.1,
        ...options
    };

    return new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                callback(entry.target);
            }
        });
    }, defaultOptions);
}

/**
 * Lazy load images with intersection observer
 * @param {Element} imageElement - Image element to lazy load
 * @param {Object} options - Options for lazy loading
 */
export function lazyLoadImage(imageElement, options = {}) {
    const observer = createLazyLoadObserver((target) => {
        // Load the image
        if (target.dataset.src) {
            target.src = target.dataset.src;
            target.removeAttribute('data-src');
        }
        
        // Load srcset if available
        if (target.dataset.srcset) {
            target.srcset = target.dataset.srcset;
            target.removeAttribute('data-srcset');
        }
        
        // Add loaded class for animations
        target.classList.add('loaded');
        
        // Stop observing once loaded
        observer.unobserve(target);
    }, options);
    
    observer.observe(imageElement);
    return observer;
}

/**
 * Batch lazy load multiple images
 * @param {NodeList|Array} images - Collection of image elements
 * @param {Object} options - Options for lazy loading
 */
export function lazyLoadImages(images, options = {}) {
    const observers = [];
    
    images.forEach(img => {
        const observer = lazyLoadImage(img, options);
        observers.push(observer);
    });
    
    return observers;
}

/**
 * Create a lazy loading directive for Svelte
 * @param {Element} node - The element to observe
 * @param {Object} params - Parameters for lazy loading
 */
export function lazyload(node, params = {}) {
    let observer;
    
    const handleIntersect = () => {
        if (params.src) {
            node.src = params.src;
        }
        if (params.srcset) {
            node.srcset = params.srcset;
        }
        if (params.callback) {
            params.callback(node);
        }
        
        node.classList.add('loaded');
        observer.unobserve(node);
    };
    
    observer = createLazyLoadObserver(handleIntersect, params.options);
    observer.observe(node);
    
    return {
        destroy() {
            if (observer) {
                observer.unobserve(node);
            }
        }
    };
}

/**
 * Preload critical images
 * @param {Array} imageSrcs - Array of image URLs to preload
 */
export function preloadImages(imageSrcs) {
    imageSrcs.forEach(src => {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.as = 'image';
        link.href = src;
        document.head.appendChild(link);
    });
}

/**
 * Generate responsive image srcset
 * @param {string} baseSrc - Base image source
 * @param {Array} sizes - Array of sizes [width, suffix]
 * @returns {string} - Generated srcset
 */
export function generateSrcSet(baseSrc, sizes = []) {
    if (!sizes.length) {
        return baseSrc;
    }
    
    const extension = baseSrc.split('.').pop();
    const baseName = baseSrc.replace(`.${extension}`, '');
    
    return sizes.map(([width, suffix]) => {
        return `${baseName}${suffix}.${extension} ${width}w`;
    }).join(', ');
}