<?php
/**
 * File Upload and Management Class
 */
class FileHandler {
    private $db;
    private $allowedTypes;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->allowedTypes = array_merge(
            ALLOWED_IMAGE_TYPES,
            ALLOWED_VIDEO_TYPES,
            ALLOWED_AUDIO_TYPES,
            ALLOWED_FILE_TYPES
        );
    }
    
    /**
     * Upload and process file
     */
    public function uploadFile($file, $uploadedBy) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No file uploaded or invalid upload');
        }
        
        // Validate file size
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('File size exceeds maximum allowed size');
        }
        
        // Get MIME type
        $mimeType = mime_content_type($file['tmp_name']);
        
        // Validate MIME type
        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new Exception('File type not allowed');
        }
        
        // Determine file category and directory
        $category = $this->getFileCategory($mimeType);
        $uploadDir = UPLOAD_PATH . $category . '/';
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $storedName = $this->generateUniqueFilename($extension);
        $filePath = $uploadDir . $storedName;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        // Save file metadata to database
        $sql = 'INSERT INTO files (original_name, stored_name, mime_type, size, uploaded_by, file_path) VALUES (?, ?, ?, ?, ?, ?)';
        $params = [
            $file['name'],
            $storedName,
            $mimeType,
            $file['size'],
            $uploadedBy,
            $category . '/' . $storedName
        ];
        
        $this->db->execute($sql, $params);
        $fileId = $this->db->lastInsertId();
        
        return [
            'id' => $fileId,
            'original_name' => $file['name'],
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'size' => $file['size'],
            'path' => $category . '/' . $storedName,
            'url' => UPLOAD_URL . $category . '/' . $storedName,
            'category' => $category
        ];
    }
    
    /**
     * Get file category based on MIME type
     */
    private function getFileCategory($mimeType) {
        if (in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
            return 'images';
        } elseif (in_array($mimeType, ALLOWED_VIDEO_TYPES)) {
            return 'videos';
        } elseif (in_array($mimeType, ALLOWED_AUDIO_TYPES)) {
            return 'audio';
        } else {
            return 'files';
        }
    }
    
    /**
     * Generate unique filename
     */
    private function generateUniqueFilename($extension) {
        return uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    }
    
    /**
     * Get file information by ID
     */
    public function getFileById($fileId) {
        $sql = 'SELECT f.*, u.name as uploader_name FROM files f 
                LEFT JOIN users u ON f.uploaded_by = u.id 
                WHERE f.id = ?';
        $file = $this->db->fetch($sql, [$fileId]);
        
        if ($file) {
            $file['url'] = UPLOAD_URL . $file['file_path'];
            $file['category'] = dirname($file['file_path']);
        }
        
        return $file;
    }
    
    /**
     * Delete file
     */
    public function deleteFile($fileId, $userId) {
        $file = $this->getFileById($fileId);
        
        if (!$file) {
            throw new Exception('File not found');
        }
        
        // Check if user has permission to delete
        if ($file['uploaded_by'] != $userId) {
            throw new Exception('Permission denied');
        }
        
        // Delete physical file
        $fullPath = UPLOAD_PATH . $file['file_path'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        
        // Delete from database
        $this->db->execute('DELETE FROM files WHERE id = ?', [$fileId]);
        
        return true;
    }
    
    /**
     * Get file thumbnail for images
     */
    public function createThumbnail($filePath, $width = 200, $height = 200) {
        $fullPath = UPLOAD_PATH . $filePath;
        
        if (!file_exists($fullPath)) {
            return null;
        }
        
        $imageInfo = getimagesize($fullPath);
        if (!$imageInfo) {
            return null;
        }
        
        $mimeType = $imageInfo['mime'];
        
        // Create image resource based on type
        switch ($mimeType) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($fullPath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($fullPath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($fullPath);
                break;
            default:
                return null;
        }
        
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        
        // Calculate thumbnail dimensions maintaining aspect ratio
        $ratio = min($width / $sourceWidth, $height / $sourceHeight);
        $thumbWidth = round($sourceWidth * $ratio);
        $thumbHeight = round($sourceHeight * $ratio);
        
        // Create thumbnail
        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        // Preserve transparency for PNG and GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
            imagefill($thumbnail, 0, 0, $transparent);
        }
        
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight);
        
        // Generate thumbnail filename
        $pathInfo = pathinfo($filePath);
        $thumbPath = $pathInfo['dirname'] . '/thumb_' . $pathInfo['basename'];
        $fullThumbPath = UPLOAD_PATH . $thumbPath;
        
        // Save thumbnail
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($thumbnail, $fullThumbPath, 85);
                break;
            case 'image/png':
                imagepng($thumbnail, $fullThumbPath);
                break;
            case 'image/gif':
                imagegif($thumbnail, $fullThumbPath);
                break;
        }
        
        imagedestroy($source);
        imagedestroy($thumbnail);
        
        return $thumbPath;
    }
    
    /**
     * Validate file type for specific category
     */
    public function validateFileType($mimeType, $category) {
        switch ($category) {
            case 'image':
                return in_array($mimeType, ALLOWED_IMAGE_TYPES);
            case 'video':
                return in_array($mimeType, ALLOWED_VIDEO_TYPES);
            case 'audio':
                return in_array($mimeType, ALLOWED_AUDIO_TYPES);
            case 'file':
                return in_array($mimeType, ALLOWED_FILE_TYPES);
            default:
                return in_array($mimeType, $this->allowedTypes);
        }
    }
    
    /**
     * Get human readable file size
     */
    public static function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}