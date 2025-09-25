/**
 * Cross-Browser Compatibility Framework
 * Provides polyfills, feature detection, and fallbacks for older browsers
 */

(function(window, document) {
    'use strict';
    
    // Browser detection and feature support
    const BrowserSupport = {
        // Feature detection
        features: {
            canvas: null,
            fileAPI: null,
            formData: null,
            fetch: null,
            classList: null,
            addEventListener: null,
            querySelector: null,
            flexbox: null,
            grid: null,
            customProperties: null,
            es6: null,
            promises: null,
            touchEvents: null,
            pointerEvents: null
        },
        
        // Browser information
        browser: {
            name: '',
            version: '',
            isIE: false,
            isEdge: false,
            isChrome: false,
            isFirefox: false,
            isSafari: false,
            isMobile: false,
            isTouch: false
        },
        
        init: function() {
            this.detectBrowser();
            this.detectFeatures();
            this.applyPolyfills();
            this.setupFallbacks();
            this.addBrowserClasses();
        },
        
        detectBrowser: function() {
            const ua = navigator.userAgent;
            
            // Detect browser
            if (ua.indexOf('MSIE') !== -1 || ua.indexOf('Trident/') !== -1) {
                this.browser.isIE = true;
                this.browser.name = 'ie';
                const match = ua.match(/(?:MSIE |rv:)(\d+)/);
                this.browser.version = match ? parseInt(match[1]) : 0;
            } else if (ua.indexOf('Edge/') !== -1) {
                this.browser.isEdge = true;
                this.browser.name = 'edge';
                const match = ua.match(/Edge\/(\d+)/);
                this.browser.version = match ? parseInt(match[1]) : 0;
            } else if (ua.indexOf('Chrome/') !== -1) {
                this.browser.isChrome = true;
                this.browser.name = 'chrome';
                const match = ua.match(/Chrome\/(\d+)/);
                this.browser.version = match ? parseInt(match[1]) : 0;
            } else if (ua.indexOf('Firefox/') !== -1) {
                this.browser.isFirefox = true;
                this.browser.name = 'firefox';
                const match = ua.match(/Firefox\/(\d+)/);
                this.browser.version = match ? parseInt(match[1]) : 0;
            } else if (ua.indexOf('Safari/') !== -1) {
                this.browser.isSafari = true;
                this.browser.name = 'safari';
                const match = ua.match(/Version\/(\d+)/);
                this.browser.version = match ? parseInt(match[1]) : 0;
            }
            
            // Detect mobile
            this.browser.isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua);
            
            // Detect touch support
            this.browser.isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        },
        
        detectFeatures: function() {
            // Canvas support
            this.features.canvas = !!(document.createElement('canvas').getContext);
            
            // File API support
            this.features.fileAPI = !!(window.File && window.FileReader && window.FileList && window.Blob);
            
            // FormData support
            this.features.formData = !!window.FormData;
            
            // Fetch API support
            this.features.fetch = !!window.fetch;
            
            // classList support
            this.features.classList = !!document.documentElement.classList;
            
            // addEventListener support
            this.features.addEventListener = !!window.addEventListener;
            
            // querySelector support
            this.features.querySelector = !!document.querySelector;
            
            // CSS Flexbox support
            this.features.flexbox = this.testCSSProperty('flexWrap') || this.testCSSProperty('webkitFlexWrap');
            
            // CSS Grid support
            this.features.grid = this.testCSSProperty('gridTemplateColumns');
            
            // CSS Custom Properties support
            this.features.customProperties = window.CSS && CSS.supports && CSS.supports('color', 'var(--test)');
            
            // ES6 support (basic check)
            this.features.es6 = (function() {
                try {
                    return new Function('(a = 0) => a')();
                } catch (e) {
                    return false;
                }
            })();
            
            // Promises support
            this.features.promises = !!window.Promise;
            
            // Touch events support
            this.features.touchEvents = 'ontouchstart' in window;
            
            // Pointer events support
            this.features.pointerEvents = !!window.PointerEvent;
        },
        
        testCSSProperty: function(property) {
            const element = document.createElement('div');
            return property in element.style;
        },
        
        applyPolyfills: function() {
            // classList polyfill for IE9
            if (!this.features.classList) {
                this.addClassListPolyfill();
            }
            
            // addEventListener polyfill for IE8
            if (!this.features.addEventListener) {
                this.addEventListenerPolyfill();
            }
            
            // querySelector polyfill for IE7
            if (!this.features.querySelector) {
                this.addQuerySelectorPolyfill();
            }
            
            // Fetch polyfill
            if (!this.features.fetch) {
                this.addFetchPolyfill();
            }
            
            // Promise polyfill
            if (!this.features.promises) {
                this.addPromisePolyfill();
            }
            
            // Array methods polyfills
            this.addArrayPolyfills();
            
            // Object methods polyfills
            this.addObjectPolyfills();
            
            // String methods polyfills
            this.addStringPolyfills();
        },
        
        addClassListPolyfill: function() {
            if (!('classList' in document.documentElement)) {
                Object.defineProperty(HTMLElement.prototype, 'classList', {
                    get: function() {
                        var self = this;
                        function update(fn) {
                            return function(value) {
                                var classes = self.className.split(/\s+/g);
                                var index = classes.indexOf(value);
                                fn(classes, index, value);
                                self.className = classes.join(' ');
                            };
                        }
                        
                        return {
                            add: update(function(classes, index, value) {
                                if (index < 0) classes.push(value);
                            }),
                            remove: update(function(classes, index) {
                                if (index >= 0) classes.splice(index, 1);
                            }),
                            toggle: update(function(classes, index, value) {
                                if (index >= 0) classes.splice(index, 1);
                                else classes.push(value);
                            }),
                            contains: function(value) {
                                return self.className.split(/\s+/g).indexOf(value) >= 0;
                            }
                        };
                    }
                });
            }
        },
        
        addEventListenerPolyfill: function() {
            if (!window.addEventListener) {
                window.addEventListener = function(type, listener) {
                    window.attachEvent('on' + type, listener);
                };
                
                window.removeEventListener = function(type, listener) {
                    window.detachEvent('on' + type, listener);
                };
                
                Element.prototype.addEventListener = function(type, listener) {
                    this.attachEvent('on' + type, listener);
                };
                
                Element.prototype.removeEventListener = function(type, listener) {
                    this.detachEvent('on' + type, listener);
                };
            }
        },
        
        addQuerySelectorPolyfill: function() {
            if (!document.querySelector) {
                // Basic querySelector implementation using getElementsByTagName, etc.
                document.querySelector = function(selector) {
                    var elements = document.querySelectorAll(selector);
                    return elements.length > 0 ? elements[0] : null;
                };
                
                document.querySelectorAll = function(selector) {
                    // Very basic implementation - would need a full CSS parser for complete support
                    if (selector.charAt(0) === '#') {
                        var element = document.getElementById(selector.substring(1));
                        return element ? [element] : [];
                    } else if (selector.charAt(0) === '.') {
                        return document.getElementsByClassName(selector.substring(1));
                    } else {
                        return document.getElementsByTagName(selector);
                    }
                };
            }
        },
        
        addFetchPolyfill: function() {
            if (!window.fetch) {
                window.fetch = function(url, options) {
                    return new Promise(function(resolve, reject) {
                        var xhr = new XMLHttpRequest();
                        options = options || {};
                        
                        xhr.open(options.method || 'GET', url);
                        
                        // Set headers
                        if (options.headers) {
                            for (var header in options.headers) {
                                xhr.setRequestHeader(header, options.headers[header]);
                            }
                        }
                        
                        xhr.onload = function() {
                            resolve({
                                ok: xhr.status >= 200 && xhr.status < 300,
                                status: xhr.status,
                                statusText: xhr.statusText,
                                text: function() {
                                    return Promise.resolve(xhr.responseText);
                                },
                                json: function() {
                                    return Promise.resolve(JSON.parse(xhr.responseText));
                                }
                            });
                        };
                        
                        xhr.onerror = function() {
                            reject(new Error('Network error'));
                        };
                        
                        xhr.send(options.body);
                    });
                };
            }
        },
        
        addPromisePolyfill: function() {
            if (!window.Promise) {
                window.Promise = function(executor) {
                    var self = this;
                    self.state = 'pending';
                    self.value = undefined;
                    self.handlers = [];
                    
                    function resolve(result) {
                        if (self.state === 'pending') {
                            self.state = 'fulfilled';
                            self.value = result;
                            self.handlers.forEach(handle);
                            self.handlers = null;
                        }
                    }
                    
                    function reject(error) {
                        if (self.state === 'pending') {
                            self.state = 'rejected';
                            self.value = error;
                            self.handlers.forEach(handle);
                            self.handlers = null;
                        }
                    }
                    
                    function handle(handler) {
                        if (self.state === 'pending') {
                            self.handlers.push(handler);
                        } else {
                            if (self.state === 'fulfilled' && typeof handler.onFulfilled === 'function') {
                                handler.onFulfilled(self.value);
                            }
                            if (self.state === 'rejected' && typeof handler.onRejected === 'function') {
                                handler.onRejected(self.value);
                            }
                        }
                    }
                    
                    this.then = function(onFulfilled, onRejected) {
                        return new Promise(function(resolve, reject) {
                            handle({
                                onFulfilled: function(result) {
                                    try {
                                        resolve(onFulfilled ? onFulfilled(result) : result);
                                    } catch (ex) {
                                        reject(ex);
                                    }
                                },
                                onRejected: function(error) {
                                    try {
                                        resolve(onRejected ? onRejected(error) : error);
                                    } catch (ex) {
                                        reject(ex);
                                    }
                                }
                            });
                        });
                    };
                    
                    this.catch = function(onRejected) {
                        return this.then(null, onRejected);
                    };
                    
                    executor(resolve, reject);
                };
                
                Promise.resolve = function(value) {
                    return new Promise(function(resolve) {
                        resolve(value);
                    });
                };
                
                Promise.reject = function(reason) {
                    return new Promise(function(resolve, reject) {
                        reject(reason);
                    });
                };
            }
        },
        
        addArrayPolyfills: function() {
            // Array.forEach polyfill
            if (!Array.prototype.forEach) {
                Array.prototype.forEach = function(callback, thisArg) {
                    for (var i = 0; i < this.length; i++) {
                        callback.call(thisArg, this[i], i, this);
                    }
                };
            }
            
            // Array.map polyfill
            if (!Array.prototype.map) {
                Array.prototype.map = function(callback, thisArg) {
                    var result = [];
                    for (var i = 0; i < this.length; i++) {
                        result[i] = callback.call(thisArg, this[i], i, this);
                    }
                    return result;
                };
            }
            
            // Array.filter polyfill
            if (!Array.prototype.filter) {
                Array.prototype.filter = function(callback, thisArg) {
                    var result = [];
                    for (var i = 0; i < this.length; i++) {
                        if (callback.call(thisArg, this[i], i, this)) {
                            result.push(this[i]);
                        }
                    }
                    return result;
                };
            }
            
            // Array.find polyfill
            if (!Array.prototype.find) {
                Array.prototype.find = function(callback, thisArg) {
                    for (var i = 0; i < this.length; i++) {
                        if (callback.call(thisArg, this[i], i, this)) {
                            return this[i];
                        }
                    }
                    return undefined;
                };
            }
            
            // Array.from polyfill
            if (!Array.from) {
                Array.from = function(arrayLike, mapFn, thisArg) {
                    var result = [];
                    for (var i = 0; i < arrayLike.length; i++) {
                        var value = arrayLike[i];
                        if (mapFn) {
                            value = mapFn.call(thisArg, value, i);
                        }
                        result.push(value);
                    }
                    return result;
                };
            }
        },
        
        addObjectPolyfills: function() {
            // Object.keys polyfill
            if (!Object.keys) {
                Object.keys = function(obj) {
                    var keys = [];
                    for (var key in obj) {
                        if (obj.hasOwnProperty(key)) {
                            keys.push(key);
                        }
                    }
                    return keys;
                };
            }
            
            // Object.assign polyfill
            if (!Object.assign) {
                Object.assign = function(target) {
                    for (var i = 1; i < arguments.length; i++) {
                        var source = arguments[i];
                        for (var key in source) {
                            if (source.hasOwnProperty(key)) {
                                target[key] = source[key];
                            }
                        }
                    }
                    return target;
                };
            }
        },
        
        addStringPolyfills: function() {
            // String.prototype.trim polyfill
            if (!String.prototype.trim) {
                String.prototype.trim = function() {
                    return this.replace(/^\s+|\s+$/g, '');
                };
            }
            
            // String.prototype.startsWith polyfill
            if (!String.prototype.startsWith) {
                String.prototype.startsWith = function(searchString, position) {
                    position = position || 0;
                    return this.substr(position, searchString.length) === searchString;
                };
            }
            
            // String.prototype.endsWith polyfill
            if (!String.prototype.endsWith) {
                String.prototype.endsWith = function(searchString, length) {
                    if (length === undefined || length > this.length) {
                        length = this.length;
                    }
                    return this.substring(length - searchString.length, length) === searchString;
                };
            }
            
            // String.prototype.includes polyfill
            if (!String.prototype.includes) {
                String.prototype.includes = function(searchString, position) {
                    return this.indexOf(searchString, position) !== -1;
                };
            }
        },
        
        setupFallbacks: function() {
            // Canvas fallback
            if (!this.features.canvas) {
                this.setupCanvasFallback();
            }
            
            // File API fallback
            if (!this.features.fileAPI) {
                this.setupFileAPIFallback();
            }
            
            // CSS Grid fallback
            if (!this.features.grid) {
                this.setupGridFallback();
            }
            
            // CSS Custom Properties fallback
            if (!this.features.customProperties) {
                this.setupCustomPropertiesFallback();
            }
            
            // Touch events fallback
            if (!this.features.touchEvents && this.browser.isMobile) {
                this.setupTouchFallback();
            }
        },
        
        setupCanvasFallback: function() {
            // Disable image cropping functionality
            window.ImageCropper = function() {
                return {
                    init: function() {
                        console.warn('Canvas not supported - image cropping disabled');
                    },
                    loadImage: function() {
                        alert('Image cropping is not supported in your browser. Please use a modern browser.');
                    }
                };
            };
            
            // Add fallback message to cropper containers
            document.addEventListener('DOMContentLoaded', function() {
                var cropperContainers = document.querySelectorAll('.image-cropper-container');
                Array.prototype.forEach.call(cropperContainers, function(container) {
                    container.innerHTML = '<div class="cropper-fallback">' +
                        '<p>Image cropping is not supported in your browser.</p>' +
                        '<p>Please upload your image and it will be used as-is.</p>' +
                        '<input type="file" accept="image/*" class="fallback-file-input">' +
                        '</div>';
                });
            });
        },
        
        setupFileAPIFallback: function() {
            // Provide basic file upload without preview
            document.addEventListener('DOMContentLoaded', function() {
                var fileInputs = document.querySelectorAll('input[type="file"]');
                Array.prototype.forEach.call(fileInputs, function(input) {
                    var wrapper = document.createElement('div');
                    wrapper.className = 'file-input-fallback';
                    wrapper.innerHTML = '<p>File preview not supported. File will be uploaded when form is submitted.</p>';
                    input.parentNode.insertBefore(wrapper, input.nextSibling);
                });
            });
        },
        
        setupGridFallback: function() {
            // Add fallback class for CSS Grid
            document.documentElement.classList.add('no-grid');
            
            // Convert grid layouts to flexbox where possible
            document.addEventListener('DOMContentLoaded', function() {
                var gridContainers = document.querySelectorAll('.form-builder-container, .enrollment-form');
                Array.prototype.forEach.call(gridContainers, function(container) {
                    container.classList.add('grid-fallback');
                });
            });
        },
        
        setupCustomPropertiesFallback: function() {
            // Add fallback class
            document.documentElement.classList.add('no-custom-properties');
            
            // Load fallback CSS with hardcoded values
            var fallbackCSS = document.createElement('link');
            fallbackCSS.rel = 'stylesheet';
            fallbackCSS.href = 'assets/css/fallback-variables.css';
            document.head.appendChild(fallbackCSS);
        },
        
        setupTouchFallback: function() {
            // Convert touch events to mouse events
            document.addEventListener('DOMContentLoaded', function() {
                var touchElements = document.querySelectorAll('.touch-enabled');
                Array.prototype.forEach.call(touchElements, function(element) {
                    element.classList.add('mouse-only');
                });
            });
        },
        
        addBrowserClasses: function() {
            var classes = [];
            
            // Browser classes
            classes.push('browser-' + this.browser.name);
            classes.push('browser-' + this.browser.name + '-' + this.browser.version);
            
            if (this.browser.isMobile) {
                classes.push('mobile');
            } else {
                classes.push('desktop');
            }
            
            if (this.browser.isTouch) {
                classes.push('touch');
            } else {
                classes.push('no-touch');
            }
            
            // Feature classes
            Object.keys(this.features).forEach(function(feature) {
                if (BrowserSupport.features[feature]) {
                    classes.push('has-' + feature.replace(/([A-Z])/g, '-$1').toLowerCase());
                } else {
                    classes.push('no-' + feature.replace(/([A-Z])/g, '-$1').toLowerCase());
                }
            });
            
            // Add classes to document element
            var docElement = document.documentElement;
            classes.forEach(function(className) {
                docElement.classList.add(className);
            });
        }
    };
    
    // Error handling and user feedback
    const ErrorHandler = {
        init: function() {
            this.setupGlobalErrorHandler();
            this.setupUnhandledRejectionHandler();
            this.setupConsoleWarnings();
        },
        
        setupGlobalErrorHandler: function() {
            window.onerror = function(message, source, lineno, colno, error) {
                ErrorHandler.logError('JavaScript Error', {
                    message: message,
                    source: source,
                    line: lineno,
                    column: colno,
                    error: error
                });
                
                // Show user-friendly message for critical errors
                if (message.indexOf('Script error') === -1) {
                    ErrorHandler.showUserError('An error occurred. Please refresh the page and try again.');
                }
                
                return false; // Don't suppress default browser error handling
            };
        },
        
        setupUnhandledRejectionHandler: function() {
            if (window.addEventListener) {
                window.addEventListener('unhandledrejection', function(event) {
                    ErrorHandler.logError('Unhandled Promise Rejection', {
                        reason: event.reason,
                        promise: event.promise
                    });
                    
                    ErrorHandler.showUserError('An error occurred while processing your request.');
                });
            }
        },
        
        setupConsoleWarnings: function() {
            // Warn about unsupported features
            if (!BrowserSupport.features.canvas) {
                console.warn('Canvas API not supported - image cropping functionality disabled');
            }
            
            if (!BrowserSupport.features.fileAPI) {
                console.warn('File API not supported - file preview functionality disabled');
            }
            
            if (BrowserSupport.browser.isIE && BrowserSupport.browser.version < 11) {
                console.warn('Internet Explorer ' + BrowserSupport.browser.version + ' detected - some features may not work properly');
            }
        },
        
        logError: function(type, details) {
            // Log to console
            console.error(type + ':', details);
            
            // Could also send to server for monitoring
            // this.sendErrorToServer(type, details);
        },
        
        showUserError: function(message) {
            // Create or update error notification
            var errorDiv = document.getElementById('global-error-notification');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.id = 'global-error-notification';
                errorDiv.className = 'error-notification';
                errorDiv.style.cssText = 'position:fixed;top:20px;right:20px;background:#dc3545;color:white;padding:15px;border-radius:5px;z-index:10000;max-width:300px;';
                document.body.appendChild(errorDiv);
            }
            
            errorDiv.innerHTML = message + ' <button onclick="this.parentNode.style.display=\'none\'" style="background:none;border:none;color:white;float:right;cursor:pointer;">&times;</button>';
            errorDiv.style.display = 'block';
            
            // Auto-hide after 10 seconds
            setTimeout(function() {
                if (errorDiv.style.display !== 'none') {
                    errorDiv.style.display = 'none';
                }
            }, 10000);
        }
    };
    
    // Performance optimization for older browsers
    const PerformanceOptimizer = {
        init: function() {
            this.optimizeAnimations();
            this.optimizeImages();
            this.debounceEvents();
        },
        
        optimizeAnimations: function() {
            // Disable animations on older browsers
            if (BrowserSupport.browser.isIE && BrowserSupport.browser.version < 10) {
                var style = document.createElement('style');
                style.textContent = '* { transition: none !important; animation: none !important; }';
                document.head.appendChild(style);
            }
        },
        
        optimizeImages: function() {
            // Lazy load images on modern browsers
            if ('IntersectionObserver' in window) {
                var imageObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });
                
                document.addEventListener('DOMContentLoaded', function() {
                    var lazyImages = document.querySelectorAll('img[data-src]');
                    Array.prototype.forEach.call(lazyImages, function(img) {
                        imageObserver.observe(img);
                    });
                });
            }
        },
        
        debounceEvents: function() {
            // Debounce resize and scroll events
            var debounce = function(func, wait) {
                var timeout;
                return function() {
                    var context = this, args = arguments;
                    clearTimeout(timeout);
                    timeout = setTimeout(function() {
                        func.apply(context, args);
                    }, wait);
                };
            };
            
            window.addEventListener('resize', debounce(function() {
                // Trigger custom resize event
                var event = document.createEvent('Event');
                event.initEvent('debouncedResize', true, true);
                window.dispatchEvent(event);
            }, 250));
            
            window.addEventListener('scroll', debounce(function() {
                // Trigger custom scroll event
                var event = document.createEvent('Event');
                event.initEvent('debouncedScroll', true, true);
                window.dispatchEvent(event);
            }, 100));
        }
    };
    
    // Initialize everything when DOM is ready
    function initCompatibility() {
        BrowserSupport.init();
        ErrorHandler.init();
        PerformanceOptimizer.init();
        
        // Expose to global scope for debugging
        window.BrowserSupport = BrowserSupport;
        window.ErrorHandler = ErrorHandler;
    }
    
    // Initialize immediately if DOM is already ready, otherwise wait
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCompatibility);
    } else {
        initCompatibility();
    }
    
})(window, document);