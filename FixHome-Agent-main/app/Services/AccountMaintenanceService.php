<?php
declare(strict_types=1);

final class AccountMaintenanceService
{
    public const STATUS_REASON_MAX_LENGTH = 500;
    public const DELETED_ACCOUNT_NAME = 'Tài khoản đã xóa';
    public const DELETED_ORDER_PHONE = '[đã xóa]';
    public const DELETED_ORDER_ADDRESS = '[địa chỉ đã xóa]';

    public static function previewTestCustomerPurge(int $adminUserId, int $targetUserId): array
    {
        $pdo = db();
        self::assertActiveAdmin(self::findUser($pdo, $adminUserId), $adminUserId);
        self::assertTestCustomer(self::findUser($pdo, $targetUserId), $targetUserId);

        return [
            'target_user_id' => $targetUserId,
            'counts' => self::collectCounts($pdo, $targetUserId),
        ];
    }

    public static function purgeTestCustomer(int $adminUserId, int $targetUserId, string $adminPassword): array
    {
        if (!PasswordPolicy::isSafeBcryptInput($adminPassword)) {
            throw new RuntimeException('Mật khẩu quản trị không hợp lệ.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $admin = self::findUser($pdo, $adminUserId, true);
            self::assertActiveAdmin($admin, $adminUserId);
            if (!password_verify($adminPassword, (string)$admin['password_hash'])) {
                throw new RuntimeException('Mật khẩu quản trị không chính xác.');
            }

            $target = self::findUser($pdo, $targetUserId, true);
            self::assertTestCustomer($target, $targetUserId);

            $orderContext = self::orderContext($pdo, $targetUserId, true);
            $orderIds = $orderContext['order_ids'];
            $storedNames = $orderContext['stored_names'];
            $counts = self::collectCounts($pdo, $targetUserId);
            $requestIds = self::idsForOrders($pdo, 'order_company_requests', $orderIds);
            $quoteIds = self::idsForOrders($pdo, 'quotes', $orderIds);
            $companyIds = self::affectedCompanyIds($pdo, $targetUserId, $orderIds);
            $sharedStoredNames = self::sharedStoredNames($pdo, $targetUserId, $storedNames);
            $deletableStoredNames = array_values(array_diff($storedNames, $sharedStoredNames));

            self::lockCompanies($pdo, $companyIds);
            self::deleteRelevantAuditLogs($pdo, $targetUserId, $orderIds, $requestIds, $quoteIds);

            if ($orderIds) {
                self::executeForIds($pdo, 'UPDATE orders SET selected_quote_id=NULL WHERE id IN (%s)', $orderIds);
                self::executeForIds($pdo, 'UPDATE order_company_requests SET current_quote_id=NULL WHERE order_id IN (%s)', $orderIds);
                self::executeForIds($pdo, 'UPDATE orders SET company_id=NULL WHERE id IN (%s)', $orderIds);

                self::executeForIds($pdo, 'DELETE FROM order_timeline WHERE order_id IN (%s)', $orderIds);
                self::executeForIds($pdo, 'DELETE FROM repair_reports WHERE order_id IN (%s)', $orderIds);
                self::executeForIds($pdo, 'DELETE FROM order_services WHERE order_id IN (%s)', $orderIds);
            }

            self::deleteTargetOrOrderRows($pdo, 'reviews', 'customer_id', $targetUserId, $orderIds);
            self::deleteTargetOrOrderRows($pdo, 'technician_reviews', 'customer_id', $targetUserId, $orderIds);
            self::deleteTargetOrOrderRows($pdo, 'complaints', 'customer_id', $targetUserId, $orderIds);
            self::deleteTargetOrOrderRows($pdo, 'notifications', 'target_user_id', $targetUserId, $orderIds);

            if ($orderIds) {
                self::executeForIds($pdo, 'DELETE FROM quotes WHERE order_id IN (%s)', $orderIds);
                self::executeForIds($pdo, 'DELETE FROM order_company_requests WHERE order_id IN (%s)', $orderIds);
                self::executeForIds($pdo, 'DELETE FROM orders WHERE id IN (%s)', $orderIds);
            }

            self::execute($pdo, 'DELETE FROM customer_addresses WHERE user_id=?', [$targetUserId]);
            self::execute($pdo, 'DELETE FROM user_profile_versions WHERE user_id=?', [$targetUserId]);
            self::recomputeCompanyRatings($pdo, $companyIds);
            self::execute($pdo, 'DELETE FROM users WHERE id=?', [$targetUserId]);

            $detail = json_encode([
                'target_user_id' => $targetUserId,
                'orders' => (int)$counts['orders'],
                'reviews' => (int)$counts['reviews'],
                'technician_reviews' => (int)$counts['technician_reviews'],
                'images' => count($storedNames),
            ], JSON_UNESCAPED_SLASHES);
            self::execute(
                $pdo,
                'INSERT INTO audit_logs(user_id,actor_role,action,entity_type,entity_id,detail,ip_address) VALUES(?,?,?,?,?,?,?)',
                [$adminUserId, 'admin', 'purge_test_customer', 'user', $targetUserId, $detail ?: '{}', self::clientIp()]
            );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $fileCleanup = UploadService::deleteStoredNames($deletableStoredNames);
        $fileCleanup['references_collected'] = count($storedNames);
        $fileCleanup['shared_references_skipped'] = count($sharedStoredNames);
        $fileCleanup['invalid_references_skipped'] = max(
            0,
            (int)$counts['uploaded_image_references'] - (int)$counts['valid_uploaded_image_references']
        );

        return [
            'purged' => true,
            'target_user_id' => $targetUserId,
            'counts' => $counts,
            'file_cleanup' => $fileCleanup,
        ];
    }

    public static function setTestCustomerMarker(
        int $adminUserId,
        int $targetUserId,
        bool $isTest,
        string $adminPassword
    ): array {
        self::assertSafeCurrentPassword($adminPassword, 'Mật khẩu quản trị không hợp lệ.');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $admin = self::findUser($pdo, $adminUserId, true);
            self::assertActiveAdmin($admin, $adminUserId);
            self::assertPassword($adminPassword, (string)$admin['password_hash'], 'Mật khẩu quản trị không chính xác.');

            $target = self::findUser($pdo, $targetUserId, true);
            self::assertMutableCustomer($target, $targetUserId);
            $nextValue = $isTest ? 1 : 0;
            if ((int)$target['is_test'] === $nextValue) {
                throw new RuntimeException($isTest
                    ? 'Tài khoản này đã được đánh dấu là tài khoản thử nghiệm.'
                    : 'Tài khoản này không có dấu thử nghiệm.');
            }

            self::execute($pdo, 'UPDATE users SET is_test=? WHERE id=?', [$nextValue, $targetUserId]);
            $action = $isTest ? 'mark_test_customer' : 'unmark_test_customer';
            self::insertAudit($pdo, $adminUserId, 'admin', $action, $targetUserId, [
                'target_user_id' => $targetUserId,
                'is_test' => $nextValue,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return ['target_user_id' => $targetUserId, 'is_test' => $nextValue];
    }

    public static function changeRealCustomerStatus(
        int $adminUserId,
        int $targetUserId,
        string $newStatus,
        string $reason,
        string $adminPassword
    ): array {
        self::assertSafeCurrentPassword($adminPassword, 'Mật khẩu quản trị không hợp lệ.');
        $reason = trim($reason);
        $reasonLength = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
        if ($reasonLength > self::STATUS_REASON_MAX_LENGTH) {
            throw new RuntimeException('Lý do không được vượt quá ' . self::STATUS_REASON_MAX_LENGTH . ' ký tự.');
        }
        if (in_array($newStatus, ['suspended', 'banned'], true) && $reason === '') {
            throw new RuntimeException('Vui lòng nhập lý do ngắn gọn cho thao tác này.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $admin = self::findUser($pdo, $adminUserId, true);
            self::assertActiveAdmin($admin, $adminUserId);
            self::assertPassword($adminPassword, (string)$admin['password_hash'], 'Mật khẩu quản trị không chính xác.');

            $target = self::findUser($pdo, $targetUserId, true);
            self::assertRealMutableCustomer($target, $targetUserId);
            $currentStatus = (string)$target['status'];
            $allowedTransitions = [
                'active' => ['suspended', 'banned'],
                'suspended' => ['active', 'banned'],
                'banned' => ['active'],
            ];
            if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
                throw new RuntimeException('Không thể chuyển trạng thái khách hàng từ ' . $currentStatus . ' sang ' . $newStatus . '.');
            }

            self::execute($pdo, 'UPDATE users SET status=? WHERE id=?', [$newStatus, $targetUserId]);
            $action = match ($newStatus) {
                'suspended' => 'suspend_customer',
                'banned' => 'ban_customer',
                'active' => 'reactivate_customer',
                default => throw new RuntimeException('Trạng thái khách hàng không hợp lệ.'),
            };
            self::insertAudit($pdo, $adminUserId, 'admin', $action, $targetUserId, [
                'target_user_id' => $targetUserId,
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
                'reason' => $reason,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return ['target_user_id' => $targetUserId, 'status' => $newStatus];
    }

    public static function deleteRealCustomerAccount(int $customerUserId, string $currentPassword): array
    {
        self::assertSafeCurrentPassword($currentPassword, 'Mật khẩu hiện tại không hợp lệ.');

        $pdo = db();
        $deletableStoredNames = [];
        $sharedStoredNames = [];
        $referencedNames = [];
        $validReferenceCount = 0;
        $pdo->beginTransaction();
        try {
            $customer = self::findUser($pdo, $customerUserId, true);
            self::assertSelfDeletableCustomer($customer, $customerUserId);
            self::assertPassword($currentPassword, (string)$customer['password_hash'], 'Mật khẩu hiện tại không chính xác.');

            $orderContext = self::orderContext($pdo, $customerUserId, true);
            if ((int)$orderContext['nonterminal_order_count'] > 0) {
                throw new RuntimeException('Không thể xóa tài khoản khi vẫn có đơn chưa kết thúc. Vui lòng hoàn tất hoặc hủy đơn trước.');
            }
            $complaints = $pdo->prepare("SELECT id FROM complaints WHERE customer_id=? AND status<>'closed' ORDER BY id FOR UPDATE");
            $complaints->execute([$customerUserId]);
            if ($complaints->fetchColumn() !== false) {
                throw new RuntimeException('Không thể xóa tài khoản khi vẫn có khiếu nại chưa đóng.');
            }

            $storedNames = $orderContext['stored_names'];
            $referencedNames = $orderContext['referenced_names'];
            $validReferenceCount = (int)$orderContext['valid_reference_count'];
            $sharedStoredNames = self::sharedStoredNames($pdo, $customerUserId, $storedNames);
            $deletableStoredNames = array_values(array_diff($storedNames, $sharedStoredNames));

            self::execute($pdo, 'DELETE FROM customer_addresses WHERE user_id=?', [$customerUserId]);
            self::execute($pdo, 'DELETE FROM user_profile_versions WHERE user_id=?', [$customerUserId]);
            self::execute($pdo, 'DELETE FROM notifications WHERE target_user_id=?', [$customerUserId]);
            self::execute(
                $pdo,
                'UPDATE orders SET customer_name=?,customer_phone=?,customer_email=NULL,address=?,image_name=NULL WHERE customer_id=?',
                [self::DELETED_ACCOUNT_NAME, self::DELETED_ORDER_PHONE, self::DELETED_ORDER_ADDRESS, $customerUserId]
            );

            $tombstoneEmail = 'deleted-account-' . $customerUserId . '-' . bin2hex(random_bytes(8)) . '@deleted.invalid';
            $unusablePasswordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            if ($unusablePasswordHash === false) {
                throw new RuntimeException('Không thể vô hiệu hóa thông tin đăng nhập.');
            }
            self::execute(
                $pdo,
                "UPDATE users SET name=?,email=?,phone=NULL,password_hash=?,status='deleted',must_change_password=0 WHERE id=?",
                [self::DELETED_ACCOUNT_NAME, $tombstoneEmail, $unusablePasswordHash, $customerUserId]
            );
            self::execute(
                $pdo,
                'INSERT INTO user_profile_versions '
                . '(user_id,version_no,name,email,phone,valid_from,valid_to,changed_by_user_id) '
                . 'SELECT id,1,name,email,phone,NOW(),NULL,NULL FROM users WHERE id=?',
                [$customerUserId]
            );
            self::insertAudit($pdo, $customerUserId, 'customer', 'self_delete_customer', $customerUserId, [
                'target_user_id' => $customerUserId,
                'orders_preserved' => count($orderContext['order_ids']),
                'image_references_cleared' => count($referencedNames),
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $fileCleanup = UploadService::deleteStoredNames($deletableStoredNames);
        $fileCleanup['references_collected'] = count($referencedNames);
        $fileCleanup['shared_references_skipped'] = count($sharedStoredNames);
        $fileCleanup['invalid_references_skipped'] = max(
            0,
            count($referencedNames) - $validReferenceCount
        );

        return [
            'deleted' => true,
            'target_user_id' => $customerUserId,
            'file_cleanup' => $fileCleanup,
        ];
    }

    private static function findUser(PDO $pdo, int $userId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT id,role,name,email,phone,status,is_test,password_hash FROM users WHERE id=? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user === false ? null : $user;
    }

    private static function assertActiveAdmin(?array $actor, int $actorUserId): void
    {
        if ($actor === null) {
            throw new RuntimeException('Không tìm thấy tài khoản quản trị #' . $actorUserId . '.');
        }
        if ((string)$actor['role'] !== 'admin' || (string)$actor['status'] !== 'active') {
            throw new RuntimeException('Chỉ quản trị viên đang hoạt động mới được thực hiện thao tác này.');
        }
    }

    private static function assertTestCustomer(?array $target, int $targetUserId): void
    {
        if ($target === null) {
            throw new RuntimeException('Không tìm thấy tài khoản đích #' . $targetUserId . '.');
        }
        if ((string)$target['role'] !== 'customer') {
            throw new RuntimeException('Chức năng này chỉ xóa tài khoản khách hàng thử nghiệm.');
        }
        if ((int)$target['is_test'] !== 1) {
            throw new RuntimeException('Không thể xóa tài khoản khách hàng thật bằng chức năng này.');
        }
    }

    private static function assertMutableCustomer(?array $target, int $targetUserId): void
    {
        if ($target === null) {
            throw new RuntimeException('Không tìm thấy tài khoản đích #' . $targetUserId . '.');
        }
        if ((string)$target['role'] !== 'customer') {
            throw new RuntimeException('Chức năng này chỉ áp dụng cho tài khoản khách hàng.');
        }
        if ((string)$target['status'] === 'deleted') {
            throw new RuntimeException('Tài khoản đã xóa chỉ được phép xem.');
        }
    }

    private static function assertRealMutableCustomer(?array $target, int $targetUserId): void
    {
        self::assertMutableCustomer($target, $targetUserId);
        if ((int)$target['is_test'] !== 0) {
            throw new RuntimeException('Trạng thái tạm ngưng/cấm chỉ áp dụng cho khách hàng thật.');
        }
    }

    private static function assertSelfDeletableCustomer(?array $target, int $targetUserId): void
    {
        if ($target === null) {
            throw new RuntimeException('Không tìm thấy tài khoản #' . $targetUserId . '.');
        }
        if ((string)$target['role'] !== 'customer') {
            throw new RuntimeException('Chỉ khách hàng thật mới có thể tự xóa tài khoản.');
        }
        if ((int)$target['is_test'] !== 0) {
            throw new RuntimeException('Tài khoản thử nghiệm phải được quản trị viên dọn dẹp.');
        }
        if ((string)$target['status'] !== 'active') {
            throw new RuntimeException('Chỉ tài khoản khách hàng đang hoạt động mới có thể tự xóa.');
        }
    }

    private static function assertSafeCurrentPassword(string $password, string $message): void
    {
        if (!PasswordPolicy::isSafeBcryptInput($password)) {
            throw new RuntimeException($message);
        }
    }

    private static function assertPassword(string $password, string $hash, string $message): void
    {
        if (!password_verify($password, $hash)) {
            throw new RuntimeException($message);
        }
    }

    private static function insertAudit(
        PDO $pdo,
        int $actorUserId,
        string $actorRole,
        string $action,
        int $targetUserId,
        array $detail
    ): void {
        $encoded = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        self::execute(
            $pdo,
            'INSERT INTO audit_logs(user_id,actor_role,action,entity_type,entity_id,detail,ip_address) VALUES(?,?,?,?,?,?,?)',
            [$actorUserId, $actorRole, $action, 'user', $targetUserId, $encoded ?: '{}', self::clientIp()]
        );
    }

    private static function collectCounts(PDO $pdo, int $targetUserId): array
    {
        $context = self::orderContext($pdo, $targetUserId, false);
        $orderIds = $context['order_ids'];
        $requestIds = self::idsForOrders($pdo, 'order_company_requests', $orderIds);
        $quoteIds = self::idsForOrders($pdo, 'quotes', $orderIds);

        return [
            'customer_account' => 1,
            'addresses' => self::count($pdo, 'SELECT COUNT(*) FROM customer_addresses WHERE user_id=?', [$targetUserId]),
            'profile_versions' => self::count($pdo, 'SELECT COUNT(*) FROM user_profile_versions WHERE user_id=?', [$targetUserId]),
            'orders' => count($orderIds),
            'order_services' => self::countForIds($pdo, 'order_services', 'order_id', $orderIds),
            'order_company_requests' => count($requestIds),
            'quotes' => count($quoteIds),
            'order_timeline' => self::countForIds($pdo, 'order_timeline', 'order_id', $orderIds),
            'repair_reports' => self::countForIds($pdo, 'repair_reports', 'order_id', $orderIds),
            'reviews' => self::countTargetOrOrderRows($pdo, 'reviews', 'customer_id', $targetUserId, $orderIds),
            'technician_reviews' => self::countTargetOrOrderRows($pdo, 'technician_reviews', 'customer_id', $targetUserId, $orderIds),
            'complaints' => self::countTargetOrOrderRows($pdo, 'complaints', 'customer_id', $targetUserId, $orderIds),
            'notifications' => self::countTargetOrOrderRows($pdo, 'notifications', 'target_user_id', $targetUserId, $orderIds),
            'uploaded_image_references' => count($context['referenced_names']),
            'valid_uploaded_image_references' => count($context['stored_names']),
            'audit_logs' => self::countRelevantAuditLogs($pdo, $targetUserId, $orderIds, $requestIds, $quoteIds),
        ];
    }

    private static function orderContext(PDO $pdo, int $targetUserId, bool $forUpdate): array
    {
        $sql = "SELECT id,status,image_name FROM orders WHERE customer_id=? ORDER BY id";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$targetUserId]);

        $orderIds = [];
        $referencedNames = [];
        $storedNames = [];
        $nonterminalOrderCount = 0;
        $validReferenceCount = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $orderIds[] = (int)$row['id'];
            if (!OrderState::isTerminal((string)$row['status'])) {
                $nonterminalOrderCount++;
            }
            $name = trim((string)($row['image_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $referencedNames[] = $name;
            if (UploadService::isValidStoredName($name)) {
                $validReferenceCount++;
                $storedNames[] = $name;
            }
        }

        return [
            'order_ids' => $orderIds,
            'referenced_names' => $referencedNames,
            'stored_names' => array_values(array_unique($storedNames)),
            'nonterminal_order_count' => $nonterminalOrderCount,
            'valid_reference_count' => $validReferenceCount,
        ];
    }

    private static function sharedStoredNames(PDO $pdo, int $targetUserId, array $storedNames): array
    {
        if (!$storedNames) {
            return [];
        }
        $stmt = $pdo->prepare(
            'SELECT DISTINCT image_name FROM orders WHERE customer_id<>? AND image_name IN ('
            . self::placeholders($storedNames)
            . ') ORDER BY image_name'
        );
        $stmt->execute(array_merge([$targetUserId], $storedNames));
        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }

    private static function idsForOrders(PDO $pdo, string $table, array $orderIds): array
    {
        if (!$orderIds) {
            return [];
        }
        $sql = sprintf('SELECT id FROM %s WHERE order_id IN (%s) ORDER BY id', $table, self::placeholders($orderIds));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($orderIds);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function affectedCompanyIds(PDO $pdo, int $targetUserId, array $orderIds): array
    {
        $sql = 'SELECT DISTINCT company_id FROM reviews WHERE customer_id=?';
        $params = [$targetUserId];
        if ($orderIds) {
            $sql .= ' OR order_id IN (' . self::placeholders($orderIds) . ')';
            $params = array_merge($params, $orderIds);
        }
        $sql .= ' ORDER BY company_id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function lockCompanies(PDO $pdo, array $companyIds): void
    {
        if (!$companyIds) {
            return;
        }
        $stmt = $pdo->prepare('SELECT id FROM companies WHERE id IN (' . self::placeholders($companyIds) . ') ORDER BY id FOR UPDATE');
        $stmt->execute($companyIds);
        $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function recomputeCompanyRatings(PDO $pdo, array $companyIds): void
    {
        if (!$companyIds) {
            return;
        }
        $stmt = $pdo->prepare(
            'UPDATE companies SET rating=(SELECT COALESCE(AVG(r.rating),0) FROM reviews r WHERE r.company_id=companies.id) WHERE id=?'
        );
        foreach ($companyIds as $companyId) {
            $stmt->execute([$companyId]);
        }
    }

    private static function deleteRelevantAuditLogs(PDO $pdo, int $targetUserId, array $orderIds, array $requestIds, array $quoteIds): void
    {
        [$where, $params] = self::auditScope($targetUserId, $orderIds, $requestIds, $quoteIds);
        self::execute($pdo, 'DELETE FROM audit_logs WHERE ' . $where, $params);
    }

    private static function countRelevantAuditLogs(PDO $pdo, int $targetUserId, array $orderIds, array $requestIds, array $quoteIds): int
    {
        [$where, $params] = self::auditScope($targetUserId, $orderIds, $requestIds, $quoteIds);
        return self::count($pdo, 'SELECT COUNT(*) FROM audit_logs WHERE ' . $where, $params);
    }

    private static function auditScope(int $targetUserId, array $orderIds, array $requestIds, array $quoteIds): array
    {
        $parts = ["user_id=?", "(entity_type IN ('user','customer') AND entity_id=?)"];
        $params = [$targetUserId, $targetUserId];
        foreach ([['order', $orderIds], ['order_company_request', $requestIds], ['quote', $quoteIds]] as [$entityType, $ids]) {
            if (!$ids) {
                continue;
            }
            $parts[] = '(entity_type=? AND entity_id IN (' . self::placeholders($ids) . '))';
            $params[] = $entityType;
            $params = array_merge($params, $ids);
        }
        return ['(' . implode(' OR ', $parts) . ')', $params];
    }

    private static function countTargetOrOrderRows(PDO $pdo, string $table, string $targetColumn, int $targetUserId, array $orderIds): int
    {
        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE %s=?', $table, $targetColumn);
        $params = [$targetUserId];
        if ($orderIds) {
            $sql .= ' OR order_id IN (' . self::placeholders($orderIds) . ')';
            $params = array_merge($params, $orderIds);
        }
        return self::count($pdo, $sql, $params);
    }

    private static function deleteTargetOrOrderRows(PDO $pdo, string $table, string $targetColumn, int $targetUserId, array $orderIds): void
    {
        $sql = sprintf('DELETE FROM %s WHERE %s=?', $table, $targetColumn);
        $params = [$targetUserId];
        if ($orderIds) {
            $sql .= ' OR order_id IN (' . self::placeholders($orderIds) . ')';
            $params = array_merge($params, $orderIds);
        }
        self::execute($pdo, $sql, $params);
    }

    private static function countForIds(PDO $pdo, string $table, string $column, array $ids): int
    {
        if (!$ids) {
            return 0;
        }
        return self::count(
            $pdo,
            sprintf('SELECT COUNT(*) FROM %s WHERE %s IN (%s)', $table, $column, self::placeholders($ids)),
            $ids
        );
    }

    private static function executeForIds(PDO $pdo, string $sqlTemplate, array $ids): void
    {
        self::execute($pdo, sprintf($sqlTemplate, self::placeholders($ids)), $ids);
    }

    private static function execute(PDO $pdo, string $sql, array $params): void
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    private static function count(PDO $pdo, string $sql, array $params): int
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    private static function clientIp(): ?string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        return $ip === '' ? null : substr($ip, 0, 64);
    }
}
