<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();

// Get download parameters
$itemType = $_GET['type'] ?? '';
$itemId = intval($_GET['id'] ?? 0);

// Validate parameters
if (!in_array($itemType, ['book', 'project', 'game']) || !$itemId) {
    redirect(BASE_PATH . '/user/library.php', 'Invalid download parameters', 'error');
}

try {
    // Verify user has purchased this item and order is completed
    $verifySql = "SELECT oi.item_id, oi.item_title, o.status,
                         CASE 
                             WHEN oi.item_type = 'book' THEN b.file_path
                             WHEN oi.item_type = 'project' THEN p.file_path
                             WHEN oi.item_type = 'game' THEN g.file_path
                         END as file_path,
                         CASE 
                             WHEN oi.item_type = 'book' THEN b.title
                             WHEN oi.item_type = 'project' THEN p.title
                             WHEN oi.item_type = 'game' THEN g.title
                         END as original_title
                  FROM order_items oi
                  JOIN orders o ON oi.order_id = o.id
                  LEFT JOIN books b ON oi.item_type = 'book' AND oi.item_id = b.id
                  LEFT JOIN projects p ON oi.item_type = 'project' AND oi.item_id = p.id
                  LEFT JOIN games g ON oi.item_type = 'game' AND oi.item_id = g.id
                  WHERE o.user_id = ? AND o.status = 'completed' 
                  AND oi.item_id = ? AND oi.item_type = ?
                  LIMIT 1";

    $verifyStmt = $db->prepare($verifySql);
    $verifyStmt->execute([$_SESSION['user_id'], $itemId, $itemType]);
    $item = $verifyStmt->fetch();

    if (!$item) {
        redirect(BASE_PATH . '/user/library.php', 'Access denied. You do not own this item or order is not completed.', 'error');
    }

    // Check if file exists
    $filePath = $item['file_path'];
    $fullFilePath = __DIR__ . '/../uploads/' . $itemType . 's/' . $filePath;
    
    if (!$filePath || !file_exists($fullFilePath)) {
        // Log the download attempt for admin review
        logActivity($_SESSION['user_id'], 'download_failed', $itemType, $itemId, [
            'reason' => 'file_not_found',
            'expected_path' => $filePath,
            'full_path' => $fullFilePath
        ]);
        
        redirect(BASE_PATH . '/user/library.php', 'Download file not found. Please contact support.', 'error');
    }

    // Log the download
    logActivity($_SESSION['user_id'], 'download_started', $itemType, $itemId, [
        'item_title' => $item['item_title'],
        'file_path' => $filePath
    ]);

    // Get file info
    $fileName = basename($filePath);
    $fileSize = filesize($fullFilePath);
    $mimeType = getMimeType($fullFilePath);
    
    // Generate download filename
    $downloadName = sanitizeFileName($item['original_title'] ?: $item['item_title']) . '.' . pathinfo($fileName, PATHINFO_EXTENSION);

    // Set headers for file download
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // Prevent any output before file
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Read and output file in chunks to handle large files
    $handle = fopen($fullFilePath, 'rb');
    if ($handle === false) {
        redirect(BASE_PATH . '/user/library.php', 'Error reading download file.', 'error');
    }

    while (!feof($handle)) {
        $chunk = fread($handle, 8192); // 8KB chunks
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    fclose($handle);

    // Log successful download
    logActivity($_SESSION['user_id'], 'download_completed', $itemType, $itemId, [
        'item_title' => $item['item_title'],
        'file_size' => $fileSize,
        'download_name' => $downloadName
    ]);

    exit;

} catch (Exception $e) {
    error_log("Download error for user {$_SESSION['user_id']}, item {$itemType}:{$itemId} - " . $e->getMessage());
    redirect(BASE_PATH . '/user/library.php', 'Download failed. Please try again or contact support.', 'error');
}

/**
 * Get MIME type for file
 */
function getMimeType($filePath) {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        // Documents
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain',
        'rtf' => 'application/rtf',
        'epub' => 'application/epub+zip',
        
        // Archives
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar',
        'gz' => 'application/gzip',
        
        // Images
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'svg' => 'image/svg+xml',
        
        // Audio
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/m4a',
        
        // Video
        'mp4' => 'video/mp4',
        'avi' => 'video/x-msvideo',
        'mkv' => 'video/x-matroska',
        'mov' => 'video/quicktime',
        
        // Executables
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msi',
        'dmg' => 'application/x-apple-diskimage',
        'deb' => 'application/vnd.debian.binary-package',
        'rpm' => 'application/x-rpm',
        
        // Code files
        'js' => 'application/javascript',
        'css' => 'text/css',
        'html' => 'text/html',
        'php' => 'text/plain',
        'py' => 'text/plain',
        'java' => 'text/plain',
        'cpp' => 'text/plain',
        'c' => 'text/plain',
        'json' => 'application/json',
        'xml' => 'application/xml'
    ];
    
    return $mimeTypes[$extension] ?? 'application/octet-stream';
}

/**
 * Sanitize filename for download
 */
function sanitizeFileName($filename) {
    // Remove/replace invalid characters
    $filename = preg_replace('/[^a-zA-Z0-9\-_\.\s]/', '', $filename);
    $filename = preg_replace('/\s+/', '_', trim($filename));
    $filename = substr($filename, 0, 200); // Limit length
    
    return $filename ?: 'download';
}
?>