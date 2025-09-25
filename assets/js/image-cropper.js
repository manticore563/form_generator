class ImageCropper {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            aspectRatio: options.aspectRatio || 1, // 1:1 for square, 4:3 for passport photo
            minWidth: options.minWidth || 100,
            minHeight: options.minHeight || 100,
            maxWidth: options.maxWidth || 800,
            maxHeight: options.maxHeight || 600,
            quality: options.quality || 0.85,
            outputFormat: options.outputFormat || 'image/jpeg',
            touchEnabled: options.touchEnabled !== false
        };
        
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
        
        this.init();
    }
    
    init() {
        this.createInterface();
        this.bindEvents();
    }
    
    createInterface() {
        this.container.innerHTML = `
            <div class="image-cropper">
                <div class="cropper-controls">
                    <input type="file" id="imageInput" accept="image/*" style="display: none;">
                    <button type="button" id="selectImageBtn" class="btn btn-primary">Select Image</button>
                    <button type="button" id="cropBtn" class="btn btn-success" disabled>Crop Image</button>
                    <button type="button" id="resetBtn" class="btn btn-secondary" disabled>Reset</button>
                </div>
                <div class="cropper-canvas-container">
                    <canvas id="cropperCanvas" style="display: none;"></canvas>
                    <div class="cropper-overlay" style="display: none;">
                        <div class="crop-area">
                            <div class="resize-handle nw"></div>
                            <div class="resize-handle ne"></div>
                            <div class="resize-handle sw"></div>
                            <div class="resize-handle se"></div>
                        </div>
                    </div>
                </div>
                <div class="cropper-preview">
                    <canvas id="previewCanvas" style="display: none;"></canvas>
                </div>
            </div>
        `;
        
        this.canvas = document.getElementById('cropperCanvas');
        this.ctx = this.canvas.getContext('2d');
        this.previewCanvas = document.getElementById('previewCanvas');
        this.previewCtx = this.previewCanvas.getContext('2d');
        this.overlay = this.container.querySelector('.cropper-overlay');
        this.cropAreaElement = this.container.querySelector('.crop-area');
    }
    
    bindEvents() {
        const imageInput = document.getElementById('imageInput');
        const selectBtn = document.getElementById('selectImageBtn');
        const cropBtn = document.getElementById('cropBtn');
        const resetBtn = document.getElementById('resetBtn');
        
        selectBtn.addEventListener('click', () => imageInput.click());
        imageInput.addEventListener('change', (e) => this.loadImage(e.target.files[0]));
        cropBtn.addEventListener('click', () => this.cropImage());
        resetBtn.addEventListener('click', () => this.reset());
        
        // Canvas events
        this.canvas.addEventListener('mousedown', (e) => this.handleMouseDown(e));
        this.canvas.addEventListener('mousemove', (e) => this.handleMouseMove(e));
        this.canvas.addEventListener('mouseup', () => this.handleMouseUp());
        
        // Touch events for mobile
        if (this.options.touchEnabled) {
            this.canvas.addEventListener('touchstart', (e) => this.handleTouchStart(e));
            this.canvas.addEventListener('touchmove', (e) => this.handleTouchMove(e));
            this.canvas.addEventListener('touchend', () => this.handleTouchEnd());
        }
        
        // Resize handles
        const handles = this.container.querySelectorAll('.resize-handle');
        handles.forEach(handle => {
            handle.addEventListener('mousedown', (e) => this.handleResizeStart(e, handle));
            if (this.options.touchEnabled) {
                handle.addEventListener('touchstart', (e) => this.handleResizeStart(e, handle));
            }
        });
    }
    
    loadImage(file) {
        if (!file || !file.type.startsWith('image/')) {
            alert('Please select a valid image file');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = (e) => {
            this.image = new Image();
            this.image.onload = () => {
                this.setupCanvas();
                this.drawImage();
                this.initializeCropArea();
                this.enableControls();
            };
            this.image.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
    
    setupCanvas() {
        const containerWidth = this.container.offsetWidth - 40; // Account for padding
        const maxWidth = Math.min(containerWidth, this.options.maxWidth);
        
        // Calculate canvas dimensions maintaining aspect ratio
        let canvasWidth, canvasHeight;
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
    }
    
    drawImage() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.drawImage(this.image, 0, 0, this.canvas.width, this.canvas.height);
        this.drawCropOverlay();
    }
    
    drawCropOverlay() {
        // Draw semi-transparent overlay
        this.ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Clear crop area
        this.ctx.clearRect(this.cropArea.x, this.cropArea.y, this.cropArea.width, this.cropArea.height);
        
        // Redraw image in crop area
        const sourceX = (this.cropArea.x / this.canvas.width) * this.image.width;
        const sourceY = (this.cropArea.y / this.canvas.height) * this.image.height;
        const sourceWidth = (this.cropArea.width / this.canvas.width) * this.image.width;
        const sourceHeight = (this.cropArea.height / this.canvas.height) * this.image.height;
        
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
    }
    
    updateOverlayPosition() {
        this.cropAreaElement.style.left = this.cropArea.x + 'px';
        this.cropAreaElement.style.top = this.cropArea.y + 'px';
        this.cropAreaElement.style.width = this.cropArea.width + 'px';
        this.cropAreaElement.style.height = this.cropArea.height + 'px';
    }
    
    initializeCropArea() {
        // Initialize crop area in center with aspect ratio
        const size = Math.min(this.canvas.width, this.canvas.height) * 0.6;
        let width = size;
        let height = size / this.options.aspectRatio;
        
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
    }
    
    getEventPos(e) {
        const rect = this.canvas.getBoundingClientRect();
        const clientX = e.clientX || (e.touches && e.touches[0].clientX);
        const clientY = e.clientY || (e.touches && e.touches[0].clientY);
        
        return {
            x: clientX - rect.left,
            y: clientY - rect.top
        };
    }
    
    handleMouseDown(e) {
        const pos = this.getEventPos(e);
        if (this.isInsideCropArea(pos)) {
            this.isDragging = true;
            this.dragStart = {
                x: pos.x - this.cropArea.x,
                y: pos.y - this.cropArea.y
            };
        }
    }
    
    handleMouseMove(e) {
        if (this.isDragging) {
            const pos = this.getEventPos(e);
            this.moveCropArea(pos.x - this.dragStart.x, pos.y - this.dragStart.y);
        }
    }
    
    handleMouseUp() {
        this.isDragging = false;
        this.isResizing = false;
        this.resizeHandle = null;
    }
    
    handleTouchStart(e) {
        e.preventDefault();
        this.handleMouseDown(e);
    }
    
    handleTouchMove(e) {
        e.preventDefault();
        this.handleMouseMove(e);
    }
    
    handleTouchEnd(e) {
        e.preventDefault();
        this.handleMouseUp();
    }
    
    handleResizeStart(e, handle) {
        e.stopPropagation();
        this.isResizing = true;
        this.resizeHandle = handle.className.split(' ')[1]; // Get direction (nw, ne, sw, se)
        
        const pos = this.getEventPos(e);
        this.dragStart = pos;
        
        document.addEventListener('mousemove', this.handleResizeMove.bind(this));
        document.addEventListener('mouseup', this.handleResizeEnd.bind(this));
        
        if (this.options.touchEnabled) {
            document.addEventListener('touchmove', this.handleResizeMove.bind(this));
            document.addEventListener('touchend', this.handleResizeEnd.bind(this));
        }
    }
    
    handleResizeMove(e) {
        if (!this.isResizing) return;
        
        const pos = this.getEventPos(e);
        const deltaX = pos.x - this.dragStart.x;
        const deltaY = pos.y - this.dragStart.y;
        
        this.resizeCropArea(deltaX, deltaY);
        this.dragStart = pos;
    }
    
    handleResizeEnd() {
        this.isResizing = false;
        this.resizeHandle = null;
        
        document.removeEventListener('mousemove', this.handleResizeMove);
        document.removeEventListener('mouseup', this.handleResizeEnd);
        document.removeEventListener('touchmove', this.handleResizeMove);
        document.removeEventListener('touchend', this.handleResizeEnd);
    }
    
    isInsideCropArea(pos) {
        return pos.x >= this.cropArea.x && pos.x <= this.cropArea.x + this.cropArea.width &&
               pos.y >= this.cropArea.y && pos.y <= this.cropArea.y + this.cropArea.height;
    }
    
    moveCropArea(newX, newY) {
        // Constrain to canvas bounds
        newX = Math.max(0, Math.min(newX, this.canvas.width - this.cropArea.width));
        newY = Math.max(0, Math.min(newY, this.canvas.height - this.cropArea.height));
        
        this.cropArea.x = newX;
        this.cropArea.y = newY;
        
        this.drawImage();
    }
    
    resizeCropArea(deltaX, deltaY) {
        const newArea = { ...this.cropArea };
        
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
    }
    
    cropImage() {
        if (!this.image) return null;
        
        // Calculate source coordinates on original image
        const scaleX = this.image.width / this.canvas.width;
        const scaleY = this.image.height / this.canvas.height;
        
        const sourceX = this.cropArea.x * scaleX;
        const sourceY = this.cropArea.y * scaleY;
        const sourceWidth = this.cropArea.width * scaleX;
        const sourceHeight = this.cropArea.height * scaleY;
        
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
        
        // Return cropped image as blob
        return new Promise((resolve) => {
            this.previewCanvas.toBlob(resolve, this.options.outputFormat, this.options.quality);
        });
    }
    
    getCroppedImageDataURL() {
        if (!this.image) return null;
        
        this.cropImage();
        return this.previewCanvas.toDataURL(this.options.outputFormat, this.options.quality);
    }
    
    enableControls() {
        document.getElementById('cropBtn').disabled = false;
        document.getElementById('resetBtn').disabled = false;
    }
    
    reset() {
        this.canvas.style.display = 'none';
        this.overlay.style.display = 'none';
        this.previewCanvas.style.display = 'none';
        document.getElementById('cropBtn').disabled = true;
        document.getElementById('resetBtn').disabled = true;
        document.getElementById('imageInput').value = '';
        this.image = null;
    }
}

// Utility function to create cropper instances
function createImageCropper(containerId, type = 'photo') {
    const options = {
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