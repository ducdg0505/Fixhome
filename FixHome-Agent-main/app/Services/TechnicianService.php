<?php
declare(strict_types=1);

final class TechnicianService
{
    public static function createTechnician(
        int $actorUserId,
        string $name,
        string $email,
        string $phone,
        string $skillNote,
        array $capabilityIds
    ): array {
        $name = trim($name);
        $email = strtolower(trim($email));
        $skillNote = trim($skillNote);
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Vui lòng nhập họ tên và email hợp lệ.');
        }
        $phone = PhonePolicy::normalize($phone);
        if ($phone === null) {
            throw new RuntimeException(PhonePolicy::ERROR_MESSAGE);
        }
        if (strlen($name) > 150 || strlen($email) > 180 || strlen($skillNote) > 255) {
            throw new RuntimeException('Thông tin kỹ thuật viên vượt quá độ dài cho phép.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $companyId = self::lockCompanyActor($pdo, $actorUserId);
            $capabilityIds = self::validatedCapabilityIds($pdo, $capabilityIds, true);
            $rawPassword = random_password();
            $stmt = $pdo->prepare(
                "INSERT INTO users(role,name,email,phone,password_hash,status,company_id,skill_note,must_change_password)
                 VALUES('technician',?,?,?,?,'active',?,?,1)"
            );
            $stmt->execute([$name,$email,$phone,password_hash($rawPassword,PASSWORD_DEFAULT),$companyId,$skillNote ?: null]);
            $technicianId = (int)$pdo->lastInsertId();
            ProfileService::initializeUserVersion($pdo,$technicianId,$actorUserId);
            self::insertCapabilities($pdo,$technicianId,$capabilityIds,$actorUserId);
            MarketplaceService::audit($pdo, $actorUserId, 'company', 'create_technician', 'user', $technicianId, 'Created technician with ' . count($capabilityIds) . ' service capabilities.');
            $pdo->commit();
            return ['id'=>$technicianId,'email'=>$email,'password'=>$rawPassword];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateCapabilities(int $technicianId, int $actorUserId, string $skillNote, array $capabilityIds): bool
    {
        $skillNote = trim($skillNote);
        if (strlen($skillNote) > 255) {
            throw new RuntimeException('Ghi chú chuyên môn vượt quá độ dài cho phép.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $companyId = self::lockCompanyActor($pdo, $actorUserId);
            $technicianStmt = $pdo->prepare("SELECT id,company_id,skill_note FROM users WHERE id=? AND role='technician' LIMIT 1 FOR UPDATE");
            $technicianStmt->execute([$technicianId]);
            $technician = $technicianStmt->fetch();
            if (!$technician || (int)$technician['company_id'] !== $companyId) {
                throw new RuntimeException('Kỹ thuật viên không thuộc doanh nghiệp của bạn.');
            }
            $capabilityIds = self::validatedCapabilityIds($pdo, $capabilityIds, false);
            $currentStmt = $pdo->prepare('SELECT service_id FROM technician_service_capabilities WHERE technician_id=? ORDER BY service_id FOR UPDATE');
            $currentStmt->execute([$technicianId]);
            $currentIds = array_map('intval',$currentStmt->fetchAll(PDO::FETCH_COLUMN));
            if ($currentIds === $capabilityIds && $skillNote === (string)($technician['skill_note'] ?? '')) {
                $pdo->commit();
                return false;
            }
            $update = $pdo->prepare('UPDATE users SET skill_note=?,updated_at=NOW() WHERE id=?');
            $update->execute([$skillNote ?: null,$technicianId]);
            $delete = $pdo->prepare('DELETE FROM technician_service_capabilities WHERE technician_id=?');
            $delete->execute([$technicianId]);
            self::insertCapabilities($pdo,$technicianId,$capabilityIds,$actorUserId);
            MarketplaceService::audit($pdo, $actorUserId, 'company', 'update_technician_capabilities', 'user', $technicianId, 'Capability set replaced atomically: ' . count($capabilityIds) . ' services.');
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function capabilityCatalog(): array
    {
        $rows = db()->query(
            'SELECT c.id category_id,c.name category_name,c.icon,s.id service_id,s.name service_name
             FROM service_categories c
             JOIN services s ON s.category_id=c.id AND s.active=1
             WHERE c.active=1
             ORDER BY c.id,s.id'
        )->fetchAll();
        $groups = [];
        foreach ($rows as $row) {
            $categoryId = (int)$row['category_id'];
            if (!isset($groups[$categoryId])) {
                $groups[$categoryId] = [
                    'id'=>$categoryId,'name'=>$row['category_name'],'icon'=>$row['icon'],'services'=>[],
                ];
            }
            $groups[$categoryId]['services'][] = ['id'=>(int)$row['service_id'],'name'=>$row['service_name']];
        }
        return array_values($groups);
    }

    public static function capabilitiesForTechnicians(array $technicianIds): array
    {
        $technicianIds = array_values(array_unique(array_filter(array_map('intval',$technicianIds),static fn(int $id): bool => $id > 0)));
        if (!$technicianIds) return [];
        $placeholders = implode(',',array_fill(0,count($technicianIds),'?'));
        $stmt = db()->prepare(
            "SELECT x.technician_id,s.id service_id,s.name service_name,s.category_id
             FROM technician_service_capabilities x
             JOIN services s ON s.id=x.service_id
             WHERE x.technician_id IN ($placeholders)
             ORDER BY x.technician_id,s.category_id,s.id"
        );
        $stmt->execute($technicianIds);
        $result = [];
        foreach ($stmt->fetchAll() as $row) $result[(int)$row['technician_id']][] = $row;
        return $result;
    }

    public static function assignmentRecommendations(int $companyId, array $orders): array
    {
        if (!$orders) return [];
        $stmt = db()->prepare(
            "SELECT u.id,u.name,u.phone,
                    COUNT(o.id) workload
             FROM users u
             LEFT JOIN orders o ON o.technician_id=u.id AND o.status NOT IN ('completed','cancelled')
             WHERE u.company_id=? AND u.role='technician' AND u.status='active'
             GROUP BY u.id,u.name,u.phone
             ORDER BY u.name,u.id"
        );
        $stmt->execute([$companyId]);
        $technicians = $stmt->fetchAll();
        if (!$technicians) return [];
        $technicianIds = array_column($technicians,'id');
        $capabilityRows = self::capabilitiesForTechnicians($technicianIds);
        $reputations = FeedbackService::technicianReputations($technicianIds);
        foreach ($technicians as &$technician) {
            $reputation = $reputations[(int)$technician['id']] ?? FeedbackService::emptyTechnicianReputation();
            $technician['average_rating'] = $reputation['average_rating'];
            $technician['review_count'] = $reputation['review_count'];
            $technician['completed_job_count'] = $reputation['completed_job_count'];
        }
        unset($technician);
        $recommendations = [];
        foreach ($orders as $order) {
            $serviceIds = array_values(array_unique(array_filter(
                array_map(static fn(array $service): int => (int)$service['service_id'], $order['services'] ?? []),
                static fn(int $serviceId): bool => $serviceId > 0
            )));
            $serviceSet = array_fill_keys($serviceIds,true);
            $requiredServiceCount = count($serviceIds);
            $categoryId = (int)($order['category_id'] ?? 0);
            $ranked = [];
            foreach ($technicians as $technician) {
                $capabilities = $capabilityRows[(int)$technician['id']] ?? [];
                $matchedServiceIds = [];
                $categoryRelevant = false;
                foreach ($capabilities as $capability) {
                    $serviceId = (int)$capability['service_id'];
                    if (isset($serviceSet[$serviceId])) $matchedServiceIds[$serviceId] = true;
                    if ($categoryId > 0 && (int)$capability['category_id'] === $categoryId) $categoryRelevant = true;
                }
                $matchedServiceCount = count($matchedServiceIds);
                $technician['workload'] = (int)$technician['workload'];
                $technician['matched_service_count'] = $matchedServiceCount;
                $technician['required_service_count'] = $requiredServiceCount;
                $technician['category_relevant'] = $categoryRelevant;
                $technician['match_level'] = $matchedServiceCount > 0 ? 2 : ($categoryRelevant ? 1 : 0);
                $technician['recommended'] = $matchedServiceCount > 0 || $categoryRelevant;
                $technician['match_label'] = $matchedServiceCount > 0
                    ? 'Khớp ' . $matchedServiceCount . '/' . $requiredServiceCount . ' dịch vụ'
                    : ($categoryRelevant
                        ? 'Phù hợp nhóm dịch vụ'
                        : ($capabilities ? 'Chưa khớp năng lực đã khai báo' : 'Chưa khai báo năng lực'));
                $ranked[] = $technician;
            }
            $recommendations[(int)$order['id']] = self::rankAssignmentOptions($ranked);
        }
        return $recommendations;
    }

    public static function rankAssignmentOptions(array $options): array
    {
        usort($options,static function(array $a,array $b): int {
                $byCoverage = (int)$b['matched_service_count'] <=> (int)$a['matched_service_count'];
                if ($byCoverage !== 0) return $byCoverage;
                $byCategory = (int)$b['category_relevant'] <=> (int)$a['category_relevant'];
                if ($byCategory !== 0) return $byCategory;
                $byWorkload = (int)$a['workload'] <=> (int)$b['workload'];
                if ($byWorkload !== 0) return $byWorkload;
                $byExperience = (int)$b['completed_job_count'] <=> (int)$a['completed_job_count'];
                if ($byExperience !== 0) return $byExperience;
                $byName = strcasecmp((string)$a['name'],(string)$b['name']);
                return $byName !== 0 ? $byName : ((int)$a['id'] <=> (int)$b['id']);
        });

        $count = count($options);
        for ($start = 0; $start < $count;) {
            $end = $start + 1;
            while ($end < $count && self::samePrimaryRecommendationRank($options[$start],$options[$end])) $end++;

            $qualifiedIndexes = [];
            $qualified = [];
            for ($index = $start; $index < $end; $index++) {
                if ((int)$options[$index]['review_count'] < 5) continue;
                $qualifiedIndexes[] = $index;
                $qualified[] = $options[$index];
            }
            usort($qualified,static function(array $a,array $b): int {
                $byRating = (float)$b['average_rating'] <=> (float)$a['average_rating'];
                if ($byRating !== 0) return $byRating;
                $byName = strcasecmp((string)$a['name'],(string)$b['name']);
                return $byName !== 0 ? $byName : ((int)$a['id'] <=> (int)$b['id']);
            });
            foreach ($qualifiedIndexes as $offset=>$index) $options[$index] = $qualified[$offset];
            $start = $end;
        }
        return $options;
    }

    private static function samePrimaryRecommendationRank(array $a,array $b): bool
    {
        return (int)$a['matched_service_count'] === (int)$b['matched_service_count']
            && (bool)$a['category_relevant'] === (bool)$b['category_relevant']
            && (int)$a['workload'] === (int)$b['workload']
            && (int)$a['completed_job_count'] === (int)$b['completed_job_count'];
    }

    public static function changeStatus(int $technicianId, int $actorUserId, string $targetStatus): void
    {
        if (!in_array($targetStatus, ['active', 'inactive'], true)) {
            throw new RuntimeException('Trạng thái kỹ thuật viên không hợp lệ.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $actorStmt = $pdo->prepare(
                "SELECT u.company_id
                 FROM users u
                 JOIN companies c ON c.id=u.company_id
                 WHERE u.id=? AND u.role='company' AND u.status='active'
                   AND c.legal_status='verified' AND c.account_status='active'
                 LIMIT 1 FOR UPDATE"
            );
            $actorStmt->execute([$actorUserId]);
            $companyId = (int)$actorStmt->fetchColumn();
            if ($companyId <= 0) {
                throw new RuntimeException('Chỉ tài khoản doanh nghiệp đang hoạt động mới được quản lý kỹ thuật viên.');
            }

            $technicianStmt = $pdo->prepare("SELECT id,status,company_id FROM users WHERE id=? AND role='technician' LIMIT 1 FOR UPDATE");
            $technicianStmt->execute([$technicianId]);
            $technician = $technicianStmt->fetch();
            if (!$technician || (int)$technician['company_id'] !== $companyId) {
                throw new RuntimeException('Kỹ thuật viên không thuộc doanh nghiệp của bạn.');
            }

            $currentStatus = (string)$technician['status'];
            if (!in_array($currentStatus, ['active', 'inactive'], true)) {
                throw new RuntimeException('Trạng thái hiện tại của kỹ thuật viên không cho phép thay đổi vòng đời tài khoản.');
            }
            $allowedTarget = $currentStatus === 'active' ? 'inactive' : 'active';
            if ($targetStatus !== $allowedTarget) {
                throw new RuntimeException('Chỉ cho phép chuyển đổi giữa đang hoạt động và ngừng hoạt động.');
            }

            if ($targetStatus === 'inactive') {
                $activeOrderStmt = $pdo->prepare(
                    "SELECT order_code FROM orders
                     WHERE technician_id=? AND status NOT IN ('completed','cancelled')
                     ORDER BY id LIMIT 1 FOR UPDATE"
                );
                $activeOrderStmt->execute([$technicianId]);
                $activeOrderCode = $activeOrderStmt->fetchColumn();
                if ($activeOrderCode !== false) {
                    throw new RuntimeException('Không thể ngừng hoạt động kỹ thuật viên đang phụ trách đơn ' . $activeOrderCode . '. Hãy xử lý xong hoặc điều phối công việc trước.');
                }
            }

            $update = $pdo->prepare('UPDATE users SET status=?,updated_at=NOW() WHERE id=?');
            $update->execute([$targetStatus,$technicianId]);
            MarketplaceService::audit($pdo, $actorUserId, 'company', $targetStatus === 'active' ? 'reactivate_technician' : 'deactivate_technician', 'user', $technicianId, 'Technician status -> ' . $targetStatus);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function lockCompanyActor(PDO $pdo, int $actorUserId): int
    {
        $stmt = $pdo->prepare(
            "SELECT u.company_id
             FROM users u
             JOIN companies c ON c.id=u.company_id
             WHERE u.id=? AND u.role='company' AND u.status='active'
               AND c.legal_status='verified' AND c.account_status='active'
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$actorUserId]);
        $companyId = (int)$stmt->fetchColumn();
        if ($companyId <= 0) {
            throw new RuntimeException('Chỉ tài khoản doanh nghiệp đang hoạt động mới được quản lý kỹ thuật viên.');
        }
        return $companyId;
    }

    private static function validatedCapabilityIds(PDO $pdo, array $capabilityIds, bool $required): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval',$capabilityIds),static fn(int $id): bool => $id > 0)));
        sort($ids,SORT_NUMERIC);
        if ($required && !$ids) {
            throw new RuntimeException('Vui lòng chọn ít nhất một năng lực xử lý.');
        }
        if (!$ids) return [];
        $placeholders = implode(',',array_fill(0,count($ids),'?'));
        $stmt = $pdo->prepare(
            "SELECT s.id FROM services s JOIN service_categories c ON c.id=s.category_id
             WHERE s.id IN ($placeholders) AND s.active=1 AND c.active=1 ORDER BY s.id FOR UPDATE"
        );
        $stmt->execute($ids);
        $valid = array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($valid !== $ids) {
            throw new RuntimeException('Danh sách năng lực xử lý chứa dịch vụ không hợp lệ.');
        }
        return $ids;
    }

    private static function insertCapabilities(PDO $pdo, int $technicianId, array $capabilityIds, int $actorUserId): void
    {
        if (!$capabilityIds) return;
        $stmt = $pdo->prepare('INSERT INTO technician_service_capabilities(technician_id,service_id,created_by_user_id) VALUES(?,?,?)');
        foreach ($capabilityIds as $serviceId) $stmt->execute([$technicianId,$serviceId,$actorUserId]);
    }
}
