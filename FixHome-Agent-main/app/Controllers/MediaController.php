<?php
declare(strict_types=1);

final class MediaController
{
    public static function orderImage(): never
    {
        $user = require_auth();
        $rawOrderId = (string)($_GET['order_id'] ?? '');
        if ($rawOrderId === '' || !ctype_digit($rawOrderId) || (int)$rawOrderId < 1) {
            self::notFound();
        }

        $image = OrderMediaService::accessibleImage(db(), $user, (int)$rawOrderId);
        if (!$image) self::notFound();

        $stored = UploadService::resolveStored((string)$image['image_name']);
        if (!$stored) self::notFound();

        header('Content-Type: ' . $stored['mime']);
        header('Content-Length: ' . (string)filesize($stored['path']));
        header('Content-Disposition: inline');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($stored['path']);
        exit;
    }

    private static function notFound(): never
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: private, no-store, max-age=0');
        echo 'Không tìm thấy.';
        exit;
    }
}
