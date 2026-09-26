<?php
declare(strict_types=1);

final class FeedbackService
{
    private const COMMENT_MAX_LENGTH = 2000;
    private const ESTABLISHED_REVIEW_COUNT = 5;

    public static function submitOrderFeedback(
        int $orderId,
        int $customerId,
        mixed $companyRating,
        string $companyComment,
        mixed $technicianRating,
        string $technicianComment
    ): array {
        $companyRating = self::validatedOptionalRating($companyRating, 'doanh nghiệp');
        $technicianRating = self::validatedOptionalRating($technicianRating, 'kỹ thuật viên');
        $companyComment = self::validatedComment($companyComment);
        $technicianComment = self::validatedComment($technicianComment);

        if ($companyRating === null && $companyComment !== '') {
            throw new RuntimeException('Vui lòng chọn số sao cho doanh nghiệp.');
        }
        if ($technicianRating === null && $technicianComment !== '') {
            throw new RuntimeException('Vui lòng chọn số sao cho kỹ thuật viên.');
        }
        if ($companyRating === null && $technicianRating === null) {
            throw new RuntimeException('Vui lòng chọn ít nhất một nội dung đánh giá.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT id,customer_id,status,company_id,technician_id FROM orders WHERE id=? LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order || (int)$order['customer_id'] !== $customerId) {
                throw new RuntimeException('Không tìm thấy đơn thuộc tài khoản của bạn.');
            }
            if ((string)$order['status'] !== OrderState::COMPLETED || $order['company_id'] === null) {
                throw new RuntimeException('Chỉ đánh giá đơn đã hoàn thành.');
            }
            if ($technicianRating !== null && $order['technician_id'] === null) {
                throw new RuntimeException('Đơn này không có kỹ thuật viên được phân công để đánh giá.');
            }

            $created = [];
            $alreadyReviewed = [];
            if ($companyRating !== null) {
                $companyLock = $pdo->prepare('SELECT id FROM companies WHERE id=? LIMIT 1 FOR UPDATE');
                $companyLock->execute([(int)$order['company_id']]);
                if ($companyLock->fetchColumn() === false) {
                    throw new RuntimeException('Doanh nghiệp của đơn không còn tồn tại.');
                }
                $existing = $pdo->prepare('SELECT id FROM reviews WHERE order_id=? LIMIT 1 FOR UPDATE');
                $existing->execute([$orderId]);
                if ($existing->fetchColumn() !== false) {
                    $alreadyReviewed[] = 'company';
                } else {
                    $insert = $pdo->prepare(
                        'INSERT INTO reviews(order_id,customer_id,company_id,rating,comment) VALUES(?,?,?,?,?)'
                    );
                    $insert->execute([
                        $orderId,
                        $customerId,
                        (int)$order['company_id'],
                        $companyRating,
                        $companyComment !== '' ? $companyComment : null,
                    ]);
                    $update = $pdo->prepare(
                        'UPDATE companies c SET rating=(SELECT AVG(r.rating) FROM reviews r WHERE r.company_id=c.id) WHERE c.id=?'
                    );
                    $update->execute([(int)$order['company_id']]);
                    $created[] = 'company';
                }
            }

            if ($technicianRating !== null) {
                $existing = $pdo->prepare('SELECT id FROM technician_reviews WHERE order_id=? LIMIT 1 FOR UPDATE');
                $existing->execute([$orderId]);
                if ($existing->fetchColumn() !== false) {
                    $alreadyReviewed[] = 'technician';
                } else {
                    $insert = $pdo->prepare(
                        'INSERT INTO technician_reviews(order_id,customer_id,technician_id,rating,comment) VALUES(?,?,?,?,?)'
                    );
                    $insert->execute([
                        $orderId,
                        $customerId,
                        (int)$order['technician_id'],
                        $technicianRating,
                        $technicianComment !== '' ? $technicianComment : null,
                    ]);
                    $created[] = 'technician';
                }
            }

            $pdo->commit();
            return ['created'=>$created, 'already_reviewed'=>$alreadyReviewed];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function companyReputations(array $companyIds): array
    {
        $companyIds = self::normalizedIds($companyIds);
        if (!$companyIds) return [];
        $placeholders = implode(',', array_fill(0, count($companyIds), '?'));
        $stmt = db()->prepare(
            "SELECT company_id,AVG(rating) average_rating,COUNT(*) review_count
             FROM reviews WHERE company_id IN ($placeholders) GROUP BY company_id"
        );
        $stmt->execute($companyIds);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int)$row['company_id']] = [
                'average_rating'=>(float)$row['average_rating'],
                'review_count'=>(int)$row['review_count'],
            ];
        }
        return $result;
    }

    public static function technicianReputations(array $technicianIds): array
    {
        $technicianIds = self::normalizedIds($technicianIds);
        if (!$technicianIds) return [];
        $placeholders = implode(',', array_fill(0, count($technicianIds), '?'));
        $params = array_merge($technicianIds, $technicianIds);
        $stmt = db()->prepare(
            "SELECT u.id technician_id,
                    rr.average_rating,COALESCE(rr.review_count,0) review_count,
                    COALESCE(j.completed_job_count,0) completed_job_count
             FROM users u
             LEFT JOIN (
                 SELECT technician_id,AVG(rating) average_rating,COUNT(*) review_count
                 FROM technician_reviews WHERE technician_id IN ($placeholders) GROUP BY technician_id
             ) rr ON rr.technician_id=u.id
             LEFT JOIN (
                 SELECT technician_id,COUNT(*) completed_job_count
                 FROM orders WHERE technician_id IN ($placeholders) AND status='completed' GROUP BY technician_id
             ) j ON j.technician_id=u.id
             WHERE u.id IN ($placeholders) AND u.role='technician'"
        );
        $stmt->execute(array_merge($params, $technicianIds));
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $count = (int)$row['review_count'];
            $result[(int)$row['technician_id']] = [
                'average_rating'=>$count > 0 ? (float)$row['average_rating'] : null,
                'review_count'=>$count,
                'completed_job_count'=>(int)$row['completed_job_count'],
            ];
        }
        return $result;
    }

    public static function emptyTechnicianReputation(): array
    {
        return ['average_rating'=>null, 'review_count'=>0, 'completed_job_count'=>0];
    }

    public static function formatReputation(array $reputation): string
    {
        $count = (int)($reputation['review_count'] ?? 0);
        if ($count === 0) return 'Chưa có đánh giá';
        $label = '★ ' . number_format((float)$reputation['average_rating'], 1) . ' · ' . $count . ' đánh giá';
        return $count < self::ESTABLISHED_REVIEW_COUNT ? $label . ' · Mới' : $label;
    }

    private static function validatedOptionalRating(mixed $value, string $targetLabel): ?int
    {
        if ($value === null || $value === '') return null;
        if (is_int($value)) $rating = $value;
        elseif (is_string($value) && preg_match('/^[1-5]$/D', $value) === 1) $rating = (int)$value;
        else throw new RuntimeException('Số sao cho ' . $targetLabel . ' phải là số nguyên từ 1 đến 5.');
        if ($rating < 1 || $rating > 5) {
            throw new RuntimeException('Số sao cho ' . $targetLabel . ' phải là số nguyên từ 1 đến 5.');
        }
        return $rating;
    }

    private static function validatedComment(string $comment): string
    {
        $comment = trim($comment);
        $length = function_exists('mb_strlen') ? mb_strlen($comment, 'UTF-8') : strlen($comment);
        if ($length > self::COMMENT_MAX_LENGTH) {
            throw new RuntimeException('Nhận xét không được vượt quá 2.000 ký tự.');
        }
        return $comment;
    }

    private static function normalizedIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    }
}
