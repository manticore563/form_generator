/**
 * Image Cropper - Cross-Browser Compatible Version
 * Responsive image cropping interface with fallbacks
 * Requires: browser-compatibility.js
 */

function ImageCropper(containerId, options) {
    this.container = document.getElementById(containerId);
    this.options = this.mergeOptions({
        aspectRatio: 1, // 1:1 for square, 4:3 for passport photo
        minWidth: 100,
        minHeight: 100,
        maxWidth: 800,
        maxHeight: 600,
        quality: 0.85,
        outputFormat: 'image/jpeg',
        touchEnabled: true
    }, options || {});
    
    this.canvas = null;
    this.ctx = null;
    this.image = null;
    this.cropArea = {
        x: 0,
        y: 0,
        width: 200,
        height: 200
    };
    this.isDragging = false;
    this.isResizing = false;
    this.dragStart = { x: 0, y: 0 };
    this.resizeHandle = null;
    
    // Check browser support
    this.hasCanvasSupport = window.BrowserSupport ? window.BrowserSupport.features.canvas : !!document.createElement('canvas').getContext;
    this.hasFileAPISupport = window.BrowserSupport ? window.BrowserSupport.features.fileAPI : !!(window.File && window.FileReader);
    
    this.init();
}

ImageCropper.prototype = {
    mergeOptions: function(defaults, options) {
        var result = {};
        for (var key in defaults) {
            result[key] = defaults[key];
        }
        for (var key in options) {
            result[key] = options[key];
        }
        return result;
    },
    
    init: function() {
        if (!this.hasCanvasSupport) {
            this.createFallbackInterface();
            return;
        }
        
        this.createInterface();
        this.bindEvents();
    },
    
    createInterface: function() {
        this.container.innerHTML = 
            '<div class="image-cropper">' +
                '<div class="cropper-controls">' +
                    '<input type="file" id="imageInput" accept="image/*" style="display: none;">' +
                    '<button type="button" id="selectImageBtn" class="btn btn-primary">Select Image</button>' +
                    '<button type="button" id="cropBtn" class="btn btn-success" disabled>Crop Image</button>' +
                    '<button type="button" id="resetBtn" class="btn btn-secondary" disabled>Reset</button>' +
                '</div>' +
                '<div class="cropper-canvas-container">' +
                    '<canvas id="cropperCanvas" style="display: none;"></canvas>' +
                    '<div class="cropper-overlay" style="display: none;">' +
                        '<div class="crop-area">' +
                            '<div class="resize-handle nw"></div>' +
                            '<div class="resize-handle ne"></div>' +
                            '<div class="resize-handle sw"></div>' +
                            '<div class="resize-handle se"></div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="cropper-preview">' +
                    '<canvas id="previewCanvas" style="display: none;"></canvas>' +
                '</div>' +
            '</div>';
        
        this.canvas = document.getElementById('cropperCanvas');
        this.ctx = this.canvas.getContext('2d');
        this.previewCanvas = document.getElementById('previewCanvas');
        this.previewCtx = this.previewCanvas.getContext('2d');
        this.overlay = this.container.querySelector('.cropper-overlay');
        this.cropAreaElement = this.container.querySelector('.crop-area');
    },
    
    createFallbackInterface: function() {
        this.container.innerHTML = 
            '<div class="cropper-fallback">' +
                '<h3>Image Cropping Not Supported</h3>' +
                '<p>Your browser does not support image cropping. Please upload your image and it will be used as-is.</p>' +
                '<input type="file" id="fallbackImageInput" accept="image/*" class="fallback-file-input">' +
                '<div id="fallbackPreview" style="margin-top: 15px;"></div>' +
            '</div>';
        
        var fallbackInput = document.getElementById('fallbackImageInput');
        var self = this;
        
        fallbackInput.addEventListener('change', function(e) {
            self.handleFallbackUpload(e);
        });
    },
    
    handleFallbackUpload: function(event) {
        var file = event.target.files && event.target.files[0];
        if (!file) return;
        
        var preview = document.getElementById('fallbackPreview');
        
        if (this.hasFileAPISupport) {
            var reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 300px; max-height: 300px; border: 1px solid #ddd; border-radius: 4px;">';
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '<p>File selected: ' + file.name + '</p>';
        }
        
        // Trigger callback if provided
        if (this.options.onImageSelected) {
            this.options.onImageSelected(file);
        }
    },
    
    bindEvents: function() {
        if (!this.hasCanvasSupport) return;
        
        var imageInput = document.getElementById('imageInput');
        var selectBtn = document.getElementById('selectImageBtn');
        var cropBtn = document.getElementById('cropBtn');
        var resetBtn = document.getElementById('resetBtn');
        var self = this;
        
        selectBtn.addEventListener('click', function() {
            imageInput.click();
        });
        
        imageInput.addEventListener('change', function(e) {
            self.loadImage(e.target.files[0]);
        });
        
        cropBtn.addEventListener('click', function() {
            self.cropImage();
        });
        
        resetBtn.addEventListener('click', function() {
            self.reset();
        });
        
        // Canvas events
        this.canvas.addEventListener('mousedown', function(e) {
            self.handleMouseDown(e);
        });
        
        this.canvas.addEventListener('mousemove', function(e) {
            self.handleMouseMove(e);
        });
        
        this.canvas.addEventListener('mouseup', function() {
            self.handleMouseUp();
        });
        
        // Touch events for mobile
        if (this.options.touchEnabled && 'ontouchstart' in window) {
            this.canvas.addEventListener('touchstart', function(e) {
                self.handleTouchStart(e);
            });
            
            this.canvas.addEventListener('touchmove', function(e) {
                self.handleTouchMove(e);
            });
            
            this.canvas.addEventListener('touchend', function() {
                self.handleTouchEnd();
            });
        }
        
        // Resize handles
        var handles = this.container.querySelectorAll('.resize-handle');
        for (var i = 0; i < handles.length; i++) {
            (function(handle) {
                handle.addEventListener('mousedown', function(e) {
                    self.handleResizeStart(e, handle);
                });
                
                if (self.options.touchEnabled && 'ontouchstart' in window) {
                    handle.addEventListener('touchstart', function(e) {
                        self.handleResizeStart(e, handle);
                    });
                }
            })(handles[i]);
        }
    },
    
    loadImage: function(file) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            alert('Please select a valid image file');
            return;
        }
        
        if (!this.hasFileAPISupport) {
            alert('File preview is not supported in your browser');
            return;
        }
        
        var reader = new FileReader();
        var self = this;
        
        reader.onload = function(e) {
            self.image = new Image();
            self.image.onload = function() {
                self.setupCanvas();
                self.drawImage();
                self.initializeCropArea();
                self.enableControls();
            };
            self.image.src = e.target.result;
        };
        
        reader.readAsDataURL(file);
    },
    
    setupCanvas: function() {
        var containerWidth = this.container.offsetWidth - 40; // Account for padding
        var maxWidth = Math.min(containerWidth, this.options.maxWidth);
        
        // Calculate canvas dimensions maintaining aspect ratio
        var canvasWidth, canvasHeight;
        if (this.image.width > this.image.height) {
            canvasWidth = Math.min(maxWidth, this.image.width);
            canvasHeight = (canvasWidth / this.image.width) * this.image.height;
        } else {
            canvasHeight = Math.min(this.options.maxHeight, this.image.height);
            canvasWidth = (canvasHeight / this.image.height) * this.image.width;
        }
        
        this.canvas.width = canvasWidth;
        this.canvas.height = canvasHeight;
        this.canvas.style.display = 'block';
        this.overlay.style.display = 'block';
        this.overlay.style.width = canvasWidth + 'px';
        this.overlay.style.height = canvasHeight + 'px';
    },
    
    drawImage: function() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.drawImage(this.image, 0, 0, this.canvas.width, this.canvas.height);
        this.drawCropOverlay();
    },
    
    drawCropOverlay: function() {
        // Draw semi-transparent overlay
        this.ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Clear crop area
        this.ctx.clearRect(this.cropArea.x, this.cropArea.y, this.cropArea.width, this.cropArea.height);
        
        // Redraw image in crop area
        var sourceX = (this.cropArea.x / this.canvas.width) * this.image.width;
        var sourceY = (this.cropArea.y / this.canvas.height) * this.image.height;
        var sourceWidth = (this.cropArea.width / this.canvas.width) * this.image.width;
        var sourceHeight = (this.cropArea.height / this.canvas.height) * this.image.height;
        
        this.ctx.drawImage(
            this.image,
            sourceX, sourceY, sourceWidth, sourceHeight,
            this.cropArea.x, this.cropArea.y, this.cropArea.width, this.cropArea.height
        );
        
        // Draw crop area border
        this.ctx.strokeStyle = '#fff';
        this.ctx.lineWidth = 2;
        this.ctx.strokeRect(this.cropArea.x, this.cropArea.y, this.cropArea.width, this.cropArea.height);
        
        // Update overlay position
        this.updateOverlayPosition();
    },
    
    updateOverlayPosition: function() {
        this.cropAreaElement.style.left = this.cropArea.x + 'px';
        this.cropAreaElement.style.top = this.cropArea.y + 'px';
        this.cropAreaElement.style.width = this.cropArea.width + 'px';
        this.cropAreaElement.style.height = this.cropArea.height + 'px';
    },
    
    initializeCropArea: function() {
        // Initialize crop area in center with aspect ratio
        var size = Math.min(this.canvas.width, this.canvas.height) * 0.6;
        var width = size;
        var height = size / this.options.aspectRatio;
        
        if (height > this.canvas.height * 0.8) {
            height = this.canvas.height * 0.8;
            width = height * this.options.aspectRatio;
        }
        
        this.cropArea = {
            x: (this.canvas.width - width) / 2,
            y: (this.canvas.height - height) / 2,
            width: width,
            height: height
        };
        
        this.drawImage();
    },
    
    getEventPos: function(e) {
        var rect = this.canvas.getBoundingClientRect();
        var clientX = e.clientX || (e.touches && e.touches[0] && e.touches[0].clientX);
        var clientY = e.clientY || (e.touches && e.touches[0] && e.touches[0].clientY);
        
        return {
            x: clientX - rect.left,
            y: clientY - rect.top
        };
    },
    
    handleMouseDown: function(e) {
        var pos = this.getEventPos(e);
        if (this.isInsideCropArea(pos)) {
            this.isDragging = true;
            this.dragStart = {
                x: pos.x - this.cropArea.x,
                y: pos.y - this.cropArea.y
            };
        }
    },
    
    handleMouseMove: function(e) {
        if (this.isDragging) {
            var pos = this.getEventPos(e);
            this.moveCropArea(pos.x - this.dragStart.x, pos.y - this.dragStart.y);
        }
    },
    
    handleMouseUp: function() {
        this.isDragging = false;
        this.isResizing = false;
        this.resizeHandle = null;
    },
    
    handleTouchStart: function(e) {
        e.preventDefault();
        this.handleMouseDown(e);
    },
    
    handleTouchMove: function(e) {
        e.preventDefault();
        this.handleMouseMove(e);
    },
    
    handleTouchEnd: function(e) {
        e.preventDefault();
        this.handleMouseUp();
    },
    
    handleResizeStart: function(e, handle) {
        e.stopPropagation();
        this.isResizing = true;
        this.resizeHandle = handle.className.split(' ')[1]; // Get direction (nw, ne, sw, se)
        
        var pos = this.getEventPos(e);
        this.dragStart = pos;
        var self = this;
        
        var mouseMoveHandler = function(e) {
            self.handleResizeMove(e);
        };
        
        var mouseUpHandler = function() {
            self.handleResizeEnd();
            document.removeEventListener('mousemove', mouseMoveHandler);
            document.removeEventListener('mouseup', mouseUpHandler);
            document.removeEventListener('touchmove', mouseMoveHandler);
            document.removeEventListener('touchend', mouseUpHandler);
        };
        
        document.addEventListener('mousemove', mouseMoveHandler);
        document.addEventListener('mouseup', mouseUpHandler);
        
        if (this.options.touchEnabled && 'ontouchstart' in window) {
            document.addEventListener('touchmove', mouseMoveHandler);
            document.addEventListener('touchend', mouseUpHandler);
        }
    },
    
    handleResizeMove: function(e) {
        if (!this.isResizing) return;
        
        var pos = this.getEventPos(e);
        var deltaX = pos.x - this.dragStart.x;
        var deltaY = pos.y - this.dragStart.y;
        
        this.resizeCropArea(deltaX, deltaY);
        this.dragStart = pos;
    },
    
    handleResizeEnd: function() {
        this.isResizing = false;
        this.resizeHandle = null;
    },
    
    isInsideCropArea: function(pos) {
        return pos.x >= this.cropArea.x && pos.x <= this.cropArea.x + this.cropArea.width &&
               pos.y >= this.cropArea.y && pos.y <= this.cropArea.y + this.cropArea.height;
    },
    
    moveCropArea: function(newX, newY) {
        // Constrain to canvas bounds
        newX = Math.max(0, Math.min(newX, this.canvas.width - this.cropArea.width));
        newY = Math.max(0, Math.min(newY, this.canvas.height - this.cropArea.height));
        
        this.cropArea.x = newX;
        this.cropArea.y = newY;
        
        this.drawImage();
    },
    
    resizeCropArea: function(deltaX, deltaY) {
        var newArea = {
            x: this.cropArea.x,
            y: this.cropArea.y,
            width: this.cropArea.width,
            height: this.cropArea.height
        };
        
        switch (this.resizeHandle) {
            case 'nw':
                newArea.x += deltaX;
                newArea.y += deltaY;
                newArea.width -= deltaX;
                newArea.height -= deltaY;
                break;
            case 'ne':
                newArea.y += deltaY;
                newArea.width += deltaX;
                newArea.height -= deltaY;
                break;
            case 'sw':
                newArea.x += deltaX;
                newArea.width -= deltaX;
                newArea.height += deltaY;
                break;
            case 'se':
                newArea.width += deltaX;
                newArea.height += deltaY;
                break;
        }
        
        // Maintain aspect ratio
        if (this.options.aspectRatio !== 'free') {
            newArea.height = newArea.width / this.options.aspectRatio;
        }
        
        // Validate bounds
        if (newArea.width >= this.options.minWidth && 
            newArea.height >= this.options.minHeight &&
            newArea.x >= 0 && newArea.y >= 0 &&
            newArea.x + newArea.width <= this.canvas.width &&
            newArea.y + newArea.height <= this.canvas.height) {
            
            this.cropArea = newArea;
            this.drawImage();
        }
    },
    
    cropImage: function() {
        if (!this.image) return null;
        
        // Calculate source coordinates on original image
        var scaleX = this.image.width / this.canvas.width;
        var scaleY = this.image.height / this.canvas.height;
        
        var sourceX = this.cropArea.x * scaleX;
        var sourceY = this.cropArea.y * scaleY;
        var sourceWidth = this.cropArea.width * scaleX;
        var sourceHeight = this.cropArea.height * scaleY;
        
        // Set preview canvas size
        this.previewCanvas.width = this.cropArea.width;
        this.previewCanvas.height = this.cropArea.height;
        
        // Draw cropped image to preview canvas
        this.previewCtx.drawImage(
            this.image,
            sourceX, sourceY, sourceWidth, sourceHeight,
            0, 0, this.cropArea.width, this.cropArea.height
        );
        
        // Show preview
        this.previewCanvas.style.display = 'block';
        
        // Return cropped image as blob or data URL
        if (this.previewCanvas.toBlob) {
            var self = this;
            return new Promise(function(resolve) {
                self.previewCanvas.toBlob(resolve, self.options.outputFormat, self.options.quality);
            });
        } else {
            // Fallback for older browsers
            return Promise.resolve(this.getCroppedImageDataURL());
        }
    },
    
    getCroppedImageDataURL: function() {
        if (!this.image) return null;
        
        this.cropImage();
        return this.previewCanvas.toDataURL(this.options.outputFormat, this.options.quality);
    },
    
    enableControls: function() {
        document.getElementById('cropBtn').disabled = false;
        document.getElementById('resetBtn').disabled = false;
    },
    
    reset: function() {
        this.canvas.style.display = 'none';
        this.overlay.style.display = 'none';
        this.previewCanvas.style.display = 'none';
        document.getElementById('cropBtn').disabled = true;
        document.getElementById('resetBtn').disabled = true;
        document.getElementById('imageInput').value = '';
        this.image = null;
    }
};

// Utility function to create cropper instances with browser compatibility
function createImageCropper(containerId, type) {
    type = type || 'photo';
    
    var options = {
        photo: {
            aspectRatio: 3/4, // Passport photo ratio
            minWidth: 150,
            minHeight: 200,
            maxWidth: 600,
            maxHeight: 800
        },
        signature: {
            aspectRatio: 3/1, // Wide signature ratio
            minWidth: 200,
            minHeight: 67,
            maxWidth: 600,
            maxHeight: 200
        },
        square: {
            aspectRatio: 1,
            minWidth: 100,
            minHeight: 100,
            maxWidth: 500,
            maxHeight: 500
        }
    };
    
    return new ImageCropper(containerId, options[type] || options.photo);
}