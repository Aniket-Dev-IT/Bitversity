<?php
class UploadService {
    private $upload_dir;
    private $allowed_types;
    private $max_size;
    
    public function __construct() {
        $this->upload_dir = __DIR__ . '/../uploads/';
        $this->allowed_types = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'document' => ['pdf', 'doc', 'docx', 'zip', 'rar'],
            'video' => ['mp4', 'avi', 'mov', 'wmv'],
            'audio' => ['mp3', 'wav', 'ogg']
        ];
        $this->max_size = 10 * 1024 * 1024; // 10MB
        
        // Create upload directories
        $this->createDirectories();
    }
    
    private function createDirectories() {
        $dirs = ['images', 'documents', 'videos', 'audio', 'temp'];
        
        foreach ($dirs as $dir) {
            $path = $this->upload_dir . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
    
    public function uploadFile($file, $type = 'image', $subfolder = '') {
        try {
            // Validate file
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                throw new Exception('No file uploaded');
            }
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Upload error: ' . $this->getUploadError($file['error']));
            }
            
            if ($file['size'] > $this->max_size) {
                throw new Exception('File too large. Maximum size is ' . ($this->max_size / 1024 / 1024) . 'MB');
            }
            
            // Validate file type
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $this->allowed_types[$type] ?? [])) {
                throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $this->allowed_types[$type] ?? []));
            }
            
            // Generate unique filename
            $filename = $this->generateUniqueFilename($file['name']);
            $target_dir = $this->upload_dir . $type . 's/' . ($subfolder ? $subfolder . '/' : '');
            
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            $target_file = $target_dir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $target_file)) {
                throw new Exception('Failed to move uploaded file');
            }
            
            // Optimize image if it's an image
            if ($type === 'image') {
                $this->optimizeImage($target_file);
            }
            
            return [
                'success' => true,
                'filename' => $filename,
                'path' => 'uploads/' . $type . 's/' . ($subfolder ? $subfolder . '/' : '') . $filename,
                'size' => filesize($target_file),
                'type' => $extension
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function deleteFile($filepath) {
        try {
            $full_path = __DIR__ . '/../' . $filepath;
            if (file_exists($full_path)) {
                unlink($full_path);
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log('Delete file error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function generateUniqueFilename($original_name) {
        $extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $name = pathinfo($original_name, PATHINFO_FILENAME);
        $name = preg_replace('/[^a-zA-Z0-9-_]/', '', $name);
        $name = substr($name, 0, 50); // Limit length
        
        return $name . '_' . uniqid() . '.' . $extension;
    }
    
    private function optimizeImage($filepath) {
        try {
            $info = getimagesize($filepath);
            if (!$info) return;
            
            $mime = $info['mime'];
            $width = $info[0];
            $height = $info[1];
            
            // Skip if image is already small
            if ($width <= 800 && $height <= 600) return;
            
            // Calculate new dimensions
            $max_width = 1200;
            $max_height = 900;
            
            if ($width > $max_width || $height > $max_height) {
                $ratio = min($max_width / $width, $max_height / $height);
                $new_width = intval($width * $ratio);
                $new_height = intval($height * $ratio);
                
                // Create new image
                $source = null;
                $destination = imagecreatetruecolor($new_width, $new_height);
                
                switch ($mime) {
                    case 'image/jpeg':
                        $source = imagecreatefromjpeg($filepath);
                        imagecopyresampled($destination, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                        imagejpeg($destination, $filepath, 85);
                        break;
                    case 'image/png':
                        $source = imagecreatefrompng($filepath);
                        imagealphablending($destination, false);
                        imagesavealpha($destination, true);
                        imagecopyresampled($destination, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                        imagepng($destination, $filepath, 6);
                        break;
                    case 'image/gif':
                        $source = imagecreatefromgif($filepath);
                        imagecopyresampled($destination, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                        imagegif($destination, $filepath);
                        break;
                }
                
                if ($source) imagedestroy($source);
                imagedestroy($destination);
            }
            
        } catch (Exception $e) {
            error_log('Image optimization error: ' . $e->getMessage());
        }
    }
    
    private function getUploadError($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize directive';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE directive';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
    
    public function getFileInfo($filepath) {
        $full_path = __DIR__ . '/../' . $filepath;
        
        if (!file_exists($full_path)) {
            return null;
        }
        
        return [
            'exists' => true,
            'size' => filesize($full_path),
            'modified' => filemtime($full_path),
            'mime' => mime_content_type($full_path),
            'readable' => is_readable($full_path)
        ];
    }
}
?>