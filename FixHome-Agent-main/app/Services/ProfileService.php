<?php
declare(strict_types=1);

final class ProfileService
{
    public static function initializeUserVersion(PDO $pdo, int $userId, ?int $changedByUserId = null): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO user_profile_versions
                (user_id,version_no,name,email,phone,valid_from,valid_to,changed_by_user_id)
             SELECT u.id,1,u.name,u.email,u.phone,NOW(),NULL,?
             FROM users u
             WHERE u.id=? AND NOT EXISTS (
                SELECT 1 FROM user_profile_versions v WHERE v.user_id=u.id
             )'
        );
        $stmt->execute([$changedByUserId,$userId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Không thể khởi tạo lịch sử hồ sơ người dùng.');
        }
    }

    public static function initializeCompanyVersion(PDO $pdo, int $companyId, ?int $changedByUserId = null): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO company_profile_versions
                (company_id,version_no,name,representative,phone,email,address,tax_code,valid_from,valid_to,changed_by_user_id)
             SELECT c.id,1,c.name,c.representative,c.phone,c.email,c.address,c.tax_code,NOW(),NULL,?
             FROM companies c
             WHERE c.id=? AND NOT EXISTS (
                SELECT 1 FROM company_profile_versions v WHERE v.company_id=c.id
             )'
        );
        $stmt->execute([$changedByUserId,$companyId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Không thể khởi tạo lịch sử hồ sơ doanh nghiệp.');
        }
    }

    public static function updateUserProfile(int $actorUserId, int $targetUserId, string $name, string $phone): bool
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Vui lòng nhập họ tên.');
        }
        $phone = PhonePolicy::normalize($phone);
        if ($phone === null) {
            throw new RuntimeException(PhonePolicy::ERROR_MESSAGE);
        }
        if (strlen($name) > 150) {
            throw new RuntimeException('Thông tin hồ sơ vượt quá độ dài cho phép.');
        }
        if ($actorUserId !== $targetUserId) {
            throw new RuntimeException('Bạn chỉ được cập nhật hồ sơ cá nhân của mình.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id,role,name,email,phone,status FROM users WHERE id=? LIMIT 1 FOR UPDATE");
            $stmt->execute([$targetUserId]);
            $user = $stmt->fetch();
            if (!$user || $user['status'] !== 'active' || !in_array($user['role'], ['customer','technician','company','admin'], true)) {
                throw new RuntimeException('Tài khoản không hợp lệ để cập nhật hồ sơ.');
            }

            $versions = self::lockUserVersions($pdo, $targetUserId);
            if ($name === (string)$user['name'] && $phone === (string)($user['phone'] ?? '')) {
                $pdo->commit();
                return false;
            }

            $boundary = self::databaseTimestamp($pdo);
            $current = $versions[0];
            $close = $pdo->prepare('UPDATE user_profile_versions SET valid_to=? WHERE id=? AND valid_to IS NULL');
            $close->execute([$boundary,(int)$current['id']]);
            if ($close->rowCount() !== 1) {
                throw new RuntimeException('Hồ sơ đã thay đổi đồng thời. Vui lòng thử lại.');
            }

            $update = $pdo->prepare('UPDATE users SET name=?,phone=?,updated_at=NOW() WHERE id=?');
            $update->execute([$name,$phone,$targetUserId]);
            $insert = $pdo->prepare(
                'INSERT INTO user_profile_versions
                    (user_id,version_no,name,email,phone,valid_from,valid_to,changed_by_user_id)
                 VALUES(?,?,?,?,?,?,NULL,?)'
            );
            $insert->execute([
                $targetUserId,(int)$current['version_no'] + 1,$name,(string)$user['email'],$phone,$boundary,$actorUserId,
            ]);
            MarketplaceService::audit($pdo, $actorUserId, (string)$user['role'], 'update_user_profile', 'user', $targetUserId, 'Updated current name and phone; durable user ID preserved.');
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateCompanyProfile(
        int $actorUserId,
        int $companyId,
        string $representative,
        string $phone,
        string $email,
        string $address
    ): bool {
        $representative = trim($representative);
        $email = strtolower(trim($email));
        $address = trim($address);
        if ($representative === '' || $address === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Vui lòng nhập đúng người đại diện, hotline, email doanh nghiệp và địa chỉ.');
        }
        $phone = PhonePolicy::normalize($phone);
        if ($phone === null) {
            throw new RuntimeException(PhonePolicy::ERROR_MESSAGE);
        }
        if (strlen($representative) > 150 || strlen($email) > 180 || strlen($address) > 255) {
            throw new RuntimeException('Thông tin doanh nghiệp vượt quá độ dài cho phép.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $actor = $pdo->prepare(
                "SELECT u.id,u.company_id
                 FROM users u
                 JOIN companies c ON c.id=u.company_id
                 WHERE u.id=? AND u.role='company' AND u.status='active'
                   AND c.id=? AND c.legal_status='verified' AND c.account_status='active'
                 LIMIT 1 FOR UPDATE"
            );
            $actor->execute([$actorUserId,$companyId]);
            if (!$actor->fetch()) {
                throw new RuntimeException('Bạn không có quyền cập nhật doanh nghiệp này.');
            }

            $stmt = $pdo->prepare('SELECT * FROM companies WHERE id=? LIMIT 1 FOR UPDATE');
            $stmt->execute([$companyId]);
            $company = $stmt->fetch();
            if (!$company) {
                throw new RuntimeException('Không tìm thấy doanh nghiệp.');
            }
            $versions = self::lockCompanyVersions($pdo, $companyId);
            if (
                $representative === (string)$company['representative']
                && $phone === (string)$company['phone']
                && $email === strtolower((string)$company['email'])
                && $address === (string)$company['address']
            ) {
                $pdo->commit();
                return false;
            }

            $boundary = self::databaseTimestamp($pdo);
            $current = $versions[0];
            $close = $pdo->prepare('UPDATE company_profile_versions SET valid_to=? WHERE id=? AND valid_to IS NULL');
            $close->execute([$boundary,(int)$current['id']]);
            if ($close->rowCount() !== 1) {
                throw new RuntimeException('Hồ sơ doanh nghiệp đã thay đổi đồng thời. Vui lòng thử lại.');
            }

            $update = $pdo->prepare('UPDATE companies SET representative=?,phone=?,email=?,address=?,updated_at=NOW() WHERE id=?');
            $update->execute([$representative,$phone,$email,$address,$companyId]);
            $insert = $pdo->prepare(
                'INSERT INTO company_profile_versions
                    (company_id,version_no,name,representative,phone,email,address,tax_code,valid_from,valid_to,changed_by_user_id)
                 VALUES(?,?,?,?,?,?,?,?,?,NULL,?)'
            );
            $insert->execute([
                $companyId,(int)$current['version_no'] + 1,(string)$company['name'],$representative,$phone,$email,$address,
                (string)$company['tax_code'],$boundary,$actorUserId,
            ]);
            MarketplaceService::audit($pdo, $actorUserId, 'company', 'update_company_profile', 'company', $companyId, 'Updated operational company profile; legal name and tax code preserved.');
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function userHistory(int $userId): array
    {
        $stmt = db()->prepare('SELECT * FROM user_profile_versions WHERE user_id=? ORDER BY version_no DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function companyHistory(int $companyId): array
    {
        $stmt = db()->prepare('SELECT * FROM company_profile_versions WHERE company_id=? ORDER BY version_no DESC');
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    private static function lockUserVersions(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare('SELECT id,version_no,valid_to FROM user_profile_versions WHERE user_id=? AND valid_to IS NULL ORDER BY version_no DESC FOR UPDATE');
        $stmt->execute([$userId]);
        $versions = $stmt->fetchAll();
        if (!$versions) {
            self::initializeUserVersion($pdo, $userId, $userId);
            $stmt->execute([$userId]);
            $versions = $stmt->fetchAll();
        }
        if (count($versions) !== 1) {
            throw new RuntimeException('Lịch sử hồ sơ người dùng không có đúng một phiên bản hiện tại.');
        }
        return $versions;
    }

    private static function lockCompanyVersions(PDO $pdo, int $companyId): array
    {
        $stmt = $pdo->prepare('SELECT id,version_no,valid_to FROM company_profile_versions WHERE company_id=? AND valid_to IS NULL ORDER BY version_no DESC FOR UPDATE');
        $stmt->execute([$companyId]);
        $versions = $stmt->fetchAll();
        if (!$versions) {
            self::initializeCompanyVersion($pdo, $companyId, null);
            $stmt->execute([$companyId]);
            $versions = $stmt->fetchAll();
        }
        if (count($versions) !== 1) {
            throw new RuntimeException('Lịch sử hồ sơ doanh nghiệp không có đúng một phiên bản hiện tại.');
        }
        return $versions;
    }

    private static function databaseTimestamp(PDO $pdo): string
    {
        $timestamp = $pdo->query('SELECT NOW()')->fetchColumn();
        if (!is_string($timestamp) || $timestamp === '') {
            throw new RuntimeException('Không thể xác định thời điểm cập nhật hồ sơ.');
        }
        return $timestamp;
    }
}
