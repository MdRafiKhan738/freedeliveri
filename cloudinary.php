<?php
declare(strict_types=1);

/** Validate one uploaded image or video and return its binary data for MySQL BLOB storage. */
function uploadMediaToMysql(array $file): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'url' => ''];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Image upload failed. Please try again.'];
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    $fileSize = (int)($file['size'] ?? 0);
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['success' => false, 'message' => 'Invalid uploaded image.'];
    }
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$fileInfo->file($tmpName);
    $imageMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $videoMimeTypes = ['video/mp4', 'video/webm', 'video/ogg'];
    $isImage = in_array($mime, $imageMimeTypes, true);
    $isVideo = in_array($mime, $videoMimeTypes, true);

    if (!$isImage && !$isVideo) {
        return ['success' => false, 'message' => 'Only JPG, PNG, WEBP, GIF, MP4, WEBM and OGG files are allowed.'];
    }

    $maxSize = $isVideo ? 50 * 1024 * 1024 : 5 * 1024 * 1024;
    if ($fileSize <= 0 || $fileSize > $maxSize) {
        return ['success' => false, 'message' => $isVideo ? 'Video size must be between 1 byte and 50MB.' : 'Image size must be between 1 byte and 5MB.'];
    }

    if ($isImage && @getimagesize($tmpName) === false) {
        return ['success' => false, 'message' => 'Invalid image file.'];
    }

    $imageData = file_get_contents($tmpName);
    if ($imageData === false || $imageData === '') {
        return ['success' => false, 'message' => 'Image data could not be read.'];
    }

    return [
        'success' => true,
        'data' => $imageData,
        'mime' => $mime,
        'media_type' => $isVideo ? 'video' : 'image'
    ];
}

/** Validate every file in a product media upload and return each file's binary data. */
function uploadProductMediaToMysql(array $files): array
{
    $items = [];
    $count = isset($files['name']) && is_array($files['name']) ? count($files['name']) : 0;

    for ($index = 0; $index < $count; $index++) {
        $result = uploadMediaToMysql([
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ]);

        if (!$result['success']) {
            return $result;
        }

        $items[] = $result;
    }

    return ['success' => true, 'items' => $items];
}

/** Backward-compatible wrapper for existing single-image upload callers. */
function uploadImageToMysql(array $file): array
{
    return uploadMediaToMysql($file);
}
