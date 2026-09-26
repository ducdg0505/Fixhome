<?php
final class UploadService
{
    public static function store(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Tải ảnh thất bại.');
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $max = (int)config('uploads.max_bytes', 3145728);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0) throw new RuntimeException('Tệp tải lên không hợp lệ.');
        if ($size > $max) throw new RuntimeException('Ảnh vượt quá dung lượng cho phép.');

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $allowed = config('uploads.allowed_mime', []);
        if (!isset($allowed[$mime])) throw new RuntimeException('Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP.');

        $image = @getimagesize($tmp);
        if ($image === false || ($image['mime'] ?? '') !== $mime) throw new RuntimeException('Nội dung tệp không phải ảnh hợp lệ.');
        $width = (int)($image[0] ?? 0);
        $height = (int)($image[1] ?? 0);
        $maxPixels = (int)config('uploads.max_pixels', 24000000);
        if ($width < 1 || $height < 1 || ($width * $height) > $maxPixels) throw new RuntimeException('Kích thước ảnh không hợp lệ hoặc quá lớn.');

        $ext = $allowed[$mime];
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dir = __DIR__ . '/../../storage/order_uploads';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Không tạo được thư mục lưu ảnh.');
        if (!move_uploaded_file($tmp, $dir . '/' . $name)) throw new RuntimeException('Không lưu được ảnh tải lên.');
        return $name;
    }

    public static function resolveStored(?string $name): ?array
    {
        if (!self::isValidStoredName($name)) return null;
        $name = (string)$name;
        $mimeByExtension = ['jpg'=>'image/jpeg', 'png'=>'image/png', 'webp'=>'image/webp'];
        $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));

        foreach ([__DIR__ . '/../../storage/order_uploads', __DIR__ . '/../../uploads'] as $base) {
            $basePath = realpath($base);
            if ($basePath === false) continue;
            $path = realpath($basePath . DIRECTORY_SEPARATOR . $name);
            if ($path === false || !is_file($path) || !str_starts_with($path, $basePath . DIRECTORY_SEPARATOR)) continue;
            $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($path);
            if (($mimeByExtension[$extension] ?? null) !== $mime) continue;
            return ['path'=>$path, 'mime'=>$mime];
        }
        return null;
    }

    public static function deleteStored(?string $name): void
    {
        self::deleteStoredNames([$name]);
    }

    public static function deleteStoredNames(array $names): array
    {
        $validNames = [];
        foreach ($names as $name) {
            $name = is_string($name) ? $name : null;
            if (self::isValidStoredName($name)) {
                $validNames[] = $name;
            }
        }
        $validNames = array_values(array_unique($validNames));

        $result = [
            'references_collected' => count($validNames),
            'files_found' => 0,
            'files_deleted' => 0,
            'failures_remaining' => [],
        ];
        $locations = [
            'storage/order_uploads' => __DIR__ . '/../../storage/order_uploads',
            'uploads' => __DIR__ . '/../../uploads',
        ];
        foreach ($validNames as $name) {
            foreach ($locations as $location => $base) {
                $path = $base . DIRECTORY_SEPARATOR . $name;
                if (!is_file($path)) {
                    continue;
                }
                $result['files_found']++;
                $deleted = @unlink($path);
                if ($deleted && !is_file($path)) {
                    $result['files_deleted']++;
                    continue;
                }
                $result['failures_remaining'][] = [
                    'stored_name' => $name,
                    'location' => $location,
                    'reason' => 'delete_failed',
                ];
            }
        }
        return $result;
    }

    public static function isValidStoredName(?string $name): bool
    {
        return $name !== null
            && $name !== ''
            && basename($name) === $name
            && preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/D', $name) === 1;
    }
}
