<?php
declare(strict_types=1);

final class NotificationService
{
    public static function markRead(int $userId, ?int $notificationId = null): int
    {
        if ($notificationId !== null && $notificationId > 0) {
            $stmt = db()->prepare('UPDATE notifications SET is_read=1,read_at=COALESCE(read_at,NOW()) WHERE id=? AND target_user_id=?');
            $stmt->execute([$notificationId,$userId]);
            return $stmt->rowCount();
        }
        $stmt = db()->prepare('UPDATE notifications SET is_read=1,read_at=COALESCE(read_at,NOW()) WHERE target_user_id=? AND is_read=0');
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }
}
