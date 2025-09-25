<?php

class ImageProcessor {
    private $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    private $maxDimension = 2048;
    private $thumbnailSize = 150;
    
    public function validateImage($file) {
        // Check if file is an image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['valid' => false, 'error' => 'File is not a valid image'];
        }
        
        // Check MIME type
        $mimeType = $imageInfo['mime'];
        if (!in_array($mimeType, $this->allowedTypes)) {
            return ['valid' => false, 'error' => 'Image type not supported. Allowed: JPEG, PNG, GIF'];
        }
        
        // Check image dimensions
        if ($imageInfo[0] > $this->maxDimension || $imageInfo[1] > $this->maxDimension) {
            return ['valid' => false, 'error' => 'Image dimensions too large. Maximum: ' . $this->maxDimension . 'px'];
        }
        
        return ['valid' => true, 'info' => $imageInfo];
    }
    
    public function cropImage($imagePath, $cropData) {
        try {
            // Load source image
            $sourceImage = $this->loadImage($imagePath);
            if (!$sourceImage) {
                throw new Exception('Failed to load source image');
            }
            
            // Get source dimensions
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);
            
            // Validate crop data
            $cropX = max(0, min($cropData['x'], $sourceWidth));
            $cropY = max(0, min($cropData['y'], $sourceHeight));
            $cropWidth = max(1, min($cropData['width'], $sourceWidth - $cropX));
            $cropHeight = max(1, min($cropData['height'], $sourceHeight - $cropY));
            
            // Create cropped image
            $croppedImage = imagecreatetruecolor($cropWidth, $cropHeight);
            
            // Preserve transparency for PNG and GIF
            if (function_exists('imagecolortransparent')) {
                imagealphablending($croppedImage, false);
                imagesavealpha($croppedImage, true);
                $transparent = imagecolorallocatealpha($croppedImage, 255, 255, 255, 127);
                imagefill($croppedImage, 0, 0, $transparent);
            }
            
            // Copy and resize
            imagecopyresampled(
                $croppedImage, $sourceImage,
                0, 0, $cropX, $cropY,
                $cropWidth, $cropHeight, $cropWidth, $cropHeight
            );
            
            // Generate output filename
            $pathInfo = pathinfo($imagePath);
            $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_cropped.' . $pathInfo['extension'];
            
            // Save cropped image
            $saved = $this->saveImage($croppedImage, $outputPath, $pathInfo['extension']);
            
            // Clean up memory
            imagedestroy($sourceImage);
            imagedestroy($croppedImage);
            
            return $saved ? $outputPath : false;
            
        } catch (Exception $e) {
            error_log("Image cropping error: " . $e->getMessage());
            return false;
        }
    }
    
    public function optimizeImage($imagePath, $quality = 85) {
        try {
            $image = $this->loadImage($imagePath);
            if (!$image) {
                return false;
            }
            
            $pathInfo = pathinfo($imagePath);
            $optimized = $this->saveImage($image, $imagePath, $pathInfo['extension'], $quality);
            
            imagedestroy($image);
            return $optimized;
            
        } catch (Exception $e) {
            error_log("Image optimization error: " . $e->getMessage());
            return false;
        }
    }
    
    public function generateThumbnail($imagePath, $size = null) {
        try {
            $thumbnailSize = $size ?: $this->thumbnailSize;
            
            $sourceImage = $this->loadImage($imagePath);
            if (!$sourceImage) {
                return false;
            }
            
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);
            
            // Calculate thumbnail dimensions maintaining aspect ratio
            if ($sourceWidth > $sourceHeight) {
                $thumbWidth = $thumbnailSize;
                $thumbHeight = ($thumbnailSize / $sourceWidth) * $sourceHeight;
            } else {
                $thumbHeight = $thumbnailSize;
                $thumbWidth = ($thumbnailSize / $sourceHeight) * $sourceWidth;
            }
            
            // Create thumbnail
            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
            
            // Preserve transparency
            if (function_exists('imagecolortransparent')) {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                imagefill($thumbnail, 0, 0, $transparent);
            }
            
            // Resize image
            imagecopyresampled(
                $thumbnail, $sourceImage,
                0, 0, 0, 0,
                $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight
            );
            
            // Generate thumbnail filename
            $pathInfo = pathinfo($imagePath);
            $thumbnailPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
            
            // Save thumbnail
            $saved = $this->saveImage($thumbnail, $thumbnailPath, $pathInfo['extension'], 80);
            
            // Clean up memory
            imagedestroy($sourceImage);
            imagedestroy($thumbnail);
            
            return $saved ? $thumbnailPath : false;
            
        } catch (Exception $e) {
            error_log("Thumbnail generation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function resizeImage($imagePath, $maxWidth, $maxHeight, $maintainAspectRatio = true) {
        try {
            $sourceImage = $this->loadImage($imagePath);
            if (!$sourceImage) {
                return false;
            }
            
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);
            
            // Calculate new dimensions
            if ($maintainAspectRatio) {
                $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
                $newWidth = $sourceWidth * $ratio;
                $newHeight = $sourceHeight * $ratio;
            } else {
                $newWidth = $maxWidth;
                $newHeight = $maxHeight;
            }
            
            // Create resized image
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency
            if (function_exists('imagecolortransparent')) {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                imagefill($resizedImage, 0, 0, $transparent);
            }
            
            // Resize
            imagecopyresampled(
                $resizedImage, $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight, $sourceWidth, $sourceHeight
            );
            
            // Generate output filename
            $pathInfo = pathinfo($imagePath);
            $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_resized.' . $pathInfo['extension'];
            
            // Save resized image
            $saved = $this->saveImage($resizedImage, $outputPath, $pathInfo['extension']);
            
            // Clean up memory
            imagedestroy($sourceImage);
            imagedestroy($resizedImage);
            
            return $saved ? $outputPath : false;
            
        } catch (Exception $e) {
            error_log("Image resize error: " . $e->getMessage());
            return false;
        }
    }
    
    private function loadImage($imagePath) {
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return false;
        }
        
        switch ($imageInfo['mime']) {
            case 'image/jpeg':
                return imagecreatefromjpeg($imagePath);
            case 'image/png':
                return imagecreatefrompng($imagePath);
            case 'image/gif':
                return imagecreatefromgif($imagePath);
            default:
                return false;
        }
    }
    
    private function saveImage($image, $path, $extension, $quality = 85) {
        switch (strtolower($extension)) {
            case 'jpg':
            case 'jpeg':
                return imagejpeg($image, $path, $quality);
            case 'png':
                // PNG quality is 0-9, convert from 0-100
                $pngQuality = 9 - round(($quality / 100) * 9);
                return imagepng($image, $path, $pngQuality);
            case 'gif':
                return imagegif($image, $path);
            default:
                return false;
        }
    }
    
    public function getImageInfo($imagePath) {
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return false;
        }
        
        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'type' => $imageInfo[2],
            'mime' => $imageInfo['mime'],
            'size' => filesize($imagePath),
            'ratio' => $imageInfo[0] / $imageInfo[1]
        ];
    }
    
    public function convertToJpeg($imagePath, $quality = 85) {
        try {
            $sourceImage = $this->loadImage($imagePath);
            if (!$sourceImage) {
                return false;
            }
            
            $pathInfo = pathinfo($imagePath);
            $jpegPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.jpg';
            
            // Create white background for transparency
            $width = imagesx($sourceImage);
            $height = imagesy($sourceImage);
            $jpegImage = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($jpegImage, 255, 255, 255);
            imagefill($jpegImage, 0, 0, $white);
            
            // Copy source image onto white background
            imagecopy($jpegImage, $sourceImage, 0, 0, 0, 0, $width, $height);
            
            // Save as JPEG
            $saved = imagejpeg($jpegImage, $jpegPath, $quality);
            
            // Clean up memory
            imagedestroy($sourceImage);
            imagedestroy($jpegImage);
            
            return $saved ? $jpegPath : false;
            
        } catch (Exception $e) {
            error_log("JPEG conversion error: " . $e->getMessage());
            return false;
        }
    }
}
?>