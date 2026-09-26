<?php
declare(strict_types=1);

final class NotificationController
{
    public static function index(): void
    {
        $user = require_auth();
        $count = db()->prepare('SELECT COUNT(*) FROM notifications WHERE target_user_id=?');
        $count->execute([$user['id']]);
        $pagination = pagination((int)$count->fetchColumn(), 30);
        $stmt = db()->prepare('SELECT * FROM notifications WHERE target_user_id=? ORDER BY created_at DESC,id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, (int)$user['id'], PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(3, (int)$pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll();
        render('notifications/index', compact('notifications','pagination'), 'Thông báo');
    }

    public static function markRead(): void
    {
        $user = require_auth();
        verify_csrf();
        $notificationId = post_int('notification_id');
        NotificationService::markRead((int)$user['id'], $notificationId > 0 ? $notificationId : null);
        flash('success', $notificationId > 0 ? 'Đã đánh dấu thông báo là đã đọc.' : 'Đã đánh dấu tất cả thông báo là đã đọc.');
        redirect('notifications');
    }
}
