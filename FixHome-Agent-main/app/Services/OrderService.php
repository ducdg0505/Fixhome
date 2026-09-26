<?php
declare(strict_types=1);

final class OrderService
{
    private const REPAIR_REPORT_TEXT_LIMIT = 10000;

    public static function categoryIdByName(string $name): ?int
    {
        $stmt = db()->prepare('SELECT id FROM service_categories WHERE name=? LIMIT 1');
        $stmt->execute([$name]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int)$value;
    }

    public static function estimate(array $serviceIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $serviceIds))));
        if (!$ids) {
            return ['min' => 0, 'max' => 0, 'services' => []];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("SELECT s.*,c.name category_name FROM services s JOIN service_categories c ON c.id=s.category_id WHERE s.id IN ({$marks}) AND s.active=1 ORDER BY s.id");
        $stmt->execute($ids);
        $services = $stmt->fetchAll();
        $min = 0;
        $max = 0;
        foreach ($services as $service) {
            $min += (int)$service['min_price'];
            $max += (int)$service['max_price'];
        }
        return ['min' => $min, 'max' => $max, 'services' => $services];
    }

    public static function createOrder(int $authenticatedCustomerId, array $data, array $estimate): int
    {
        $customerName = trim((string)($data['name'] ?? ''));
        $customerEmail = trim((string)($data['email'] ?? ''));
        $phone = PhonePolicy::normalize((string)($data['phone'] ?? ''));
        $address = trim((string)($data['address'] ?? ''));
        if ($customerName === '') {
            throw new RuntimeException('Vui lòng nhập tên người liên hệ.');
        }
        if (self::textLength($customerName) > 150) {
            throw new RuntimeException('Tên người liên hệ không được vượt quá 150 ký tự.');
        }
        if ($phone === null) {
            throw new RuntimeException(PhonePolicy::ERROR_MESSAGE);
        }
        if ($customerEmail !== '' && (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL) || self::textLength($customerEmail) > 180)) {
            throw new RuntimeException('Email liên hệ không hợp lệ hoặc vượt quá 180 ký tự.');
        }
        if ($address === '') {
            throw new RuntimeException('Vui lòng nhập địa chỉ sửa chữa.');
        }
        if (self::textLength($address) > 255) {
            throw new RuntimeException('Địa chỉ sửa chữa không được vượt quá 255 ký tự.');
        }
        if (!$estimate['services']) {
            throw new RuntimeException('Đơn phải có ít nhất một dịch vụ hợp lệ.');
        }
        $categoryId = (int)$data['category_id'];
        $isUnknownDiagnosisMetaOrder =
            ($data['mode'] ?? '') === 'unknown_diagnosis'
            && count($estimate['services']) === 1
            && ($estimate['services'][0]['code'] ?? '') === 'unknown_diagnosis';
        foreach ($estimate['services'] as $service) {
            if ((int)$service['category_id'] !== $categoryId && !$isUnknownDiagnosisMetaOrder) {
                throw new RuntimeException('Các dịch vụ trong một đơn phải cùng nhóm.');
            }
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $actor = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='customer' AND status='active' LIMIT 1 FOR UPDATE");
            $actor->execute([$authenticatedCustomerId]);
            if ($actor->fetchColumn() === false) {
                throw new RuntimeException('Tài khoản Khách hàng không hợp lệ để tạo đơn.');
            }
            $code = 'FH' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            $stmt = $pdo->prepare(
                "INSERT INTO orders
                    (order_code,customer_id,customer_name,customer_phone,customer_email,category_id,mode,description,diagnosis_summary,diagnosis_device,diagnosis_issue_group,diagnosis_risk,image_name,address,scheduled_at,status,estimate_min,estimate_max)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending_distribution',?,?)"
            );
            $stmt->execute([
                $code, $authenticatedCustomerId, $customerName, $phone,
                $customerEmail === '' ? null : $customerEmail, $categoryId, (string)$data['mode'],
                $data['description'] ?: null, $data['diagnosis_summary'] ?: null,
                $data['diagnosis_device'] ?: null, $data['diagnosis_issue_group'] ?: null,
                $data['diagnosis_risk'] ?: null, $data['image_name'] ?: null,
                $address, (string)$data['scheduled_at'],
                (int)$estimate['min'], (int)$estimate['max'],
            ]);
            $orderId = (int)$pdo->lastInsertId();
            $insertService = $pdo->prepare(
                'INSERT INTO order_services(order_id,service_id,service_code_snapshot,name_snapshot,unit_snapshot,min_price_snapshot,max_price_snapshot,quantity) VALUES(?,?,?,?,?,?,?,1)'
            );
            foreach ($estimate['services'] as $service) {
                $insertService->execute([
                    $orderId, (int)$service['id'], (string)$service['code'], (string)$service['name'],
                    (string)$service['unit'], (int)$service['min_price'], (int)$service['max_price'],
                ]);
            }
            MarketplaceService::appendTimeline($pdo, $orderId, 'order', 'order_created', null, OrderState::PENDING_DISTRIBUTION, null, null, $authenticatedCustomerId, 'customer', 'Khách hàng gửi yêu cầu dịch vụ.');
            MarketplaceService::audit($pdo, $authenticatedCustomerId, 'customer', 'create_order', 'order', $orderId, 'Customer created order ' . $code);

            $eligible = $pdo->prepare("SELECT c.id FROM companies c JOIN company_service_categories x ON x.company_id=c.id AND x.category_id=? WHERE c.legal_status='verified' AND c.account_status='active' ORDER BY c.id");
            $eligible->execute([$categoryId]);
            $companyIds = array_map('intval', $eligible->fetchAll(PDO::FETCH_COLUMN));
            if ($companyIds) {
                MarketplaceService::inviteForOrderInTransaction($pdo, [
                    'id' => $orderId,
                    'order_code' => $code,
                    'category_id' => $categoryId,
                    'status' => OrderState::PENDING_DISTRIBUTION,
                    'company_id' => null,
                ], $authenticatedCustomerId, 'customer', $companyIds);
            }
            $pdo->commit();
            return $orderId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function cancelOrder(int $orderId, int $actorUserId, string $actorRole, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Vui lòng nhập lý do hủy.');
        }
        if (!in_array($actorRole, ['customer', 'admin'], true)) {
            throw new RuntimeException('Vai trò này không được hủy đơn.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $order = self::lockOrder($pdo, $orderId);
            $stmt = $pdo->prepare('SELECT role,status FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$actorUserId]);
            $actor = $stmt->fetch();
            if (!$actor || $actor['status'] !== 'active' || $actor['role'] !== $actorRole) {
                throw new RuntimeException('Tài khoản hủy đơn không hợp lệ hoặc vai trò không khớp.');
            }
            if ($actorRole === 'customer' && (int)$order['customer_id'] !== $actorUserId) {
                throw new RuntimeException('Bạn không có quyền hủy đơn này.');
            }
            if (OrderState::isTerminal((string)$order['status'])) {
                throw new RuntimeException('Đơn đã ở trạng thái kết thúc.');
            }
            OrderState::assertTransition((string)$order['status'], OrderState::CANCELLED);
            $requestStatus = $actorRole === 'customer' ? 'cancelled_by_customer' : 'cancelled_by_admin';
            $quoteStatus = $requestStatus;

            $stmt = $pdo->prepare('SELECT * FROM order_company_requests WHERE order_id=? FOR UPDATE');
            $stmt->execute([$orderId]);
            $requests = $stmt->fetchAll();
            $stmt = $pdo->prepare('SELECT * FROM quotes WHERE order_id=? FOR UPDATE');
            $stmt->execute([$orderId]);
            $stmt->fetchAll();

            $stmt = $pdo->prepare("UPDATE order_company_requests SET status=?,closed_at=NOW(),close_reason=? WHERE order_id=? AND status IN ('invited','viewed','quote_submitted','selected')");
            $stmt->execute([$requestStatus, $reason, $orderId]);
            $stmt = $pdo->prepare("UPDATE quotes SET status=?,responded_at=NOW() WHERE order_id=? AND status IN ('submitted','changes_requested','accepted_by_customer')");
            $stmt->execute([$quoteStatus, $orderId]);
            $stmt = $pdo->prepare('UPDATE orders SET status=?,cancelled_by_user_id=?,cancelled_by_role=?,cancel_reason=?,cancelled_at=NOW(),updated_at=NOW() WHERE id=?');
            $stmt->execute([OrderState::CANCELLED, $actorUserId, $actorRole, $reason, $orderId]);

            MarketplaceService::appendTimeline($pdo, $orderId, 'order', 'order_cancelled', (string)$order['status'], OrderState::CANCELLED, null, $order['company_id'] === null ? null : (int)$order['company_id'], $actorUserId, $actorRole, $reason);
            MarketplaceService::notifyUser($pdo, (int)$order['customer_id'], 'order_cancelled', 'Đơn đã hủy', 'Đơn ' . $order['order_code'] . ' đã được hủy.', $orderId);
            foreach ($requests as $request) {
                if (in_array($request['status'], ['invited', 'viewed', 'quote_submitted', 'selected'], true)) {
                    MarketplaceService::notifyCompanyUsers($pdo, (int)$request['company_id'], 'order_cancelled', 'Đơn đã hủy', 'Đơn ' . $order['order_code'] . ' đã được hủy.', $orderId);
                }
            }
            MarketplaceService::audit($pdo, $actorUserId, $actorRole, 'cancel_order', 'order', $orderId, $reason);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function assignTechnician(int $orderId, int $companyId, int $technicianId, int $actorUserId): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $order = self::lockOrder($pdo, $orderId);
            self::assertCompanyActor($pdo, $actorUserId, $companyId);
            if ((int)$order['company_id'] !== $companyId || !$order['selected_quote_id'] || $order['status'] !== OrderState::QUOTE_ACCEPTED) {
                throw new RuntimeException('Chỉ doanh nghiệp được chọn mới được phân công sau khi báo giá được chấp nhận.');
            }
            if ($order['technician_id'] !== null) {
                throw new RuntimeException('Đơn đã có kỹ thuật viên được phân công.');
            }
            $stmt = $pdo->prepare("SELECT id FROM order_company_requests WHERE order_id=? AND company_id=? AND status='selected' LIMIT 1 FOR UPDATE");
            $stmt->execute([$orderId, $companyId]);
            if ($stmt->fetchColumn() === false) {
                throw new RuntimeException('Doanh nghiệp chưa có request được chọn.');
            }
            $stmt = $pdo->prepare("SELECT id FROM quotes WHERE id=? AND order_id=? AND company_id=? AND status='accepted_by_customer' LIMIT 1 FOR UPDATE");
            $stmt->execute([(int)$order['selected_quote_id'], $orderId, $companyId]);
            if ($stmt->fetchColumn() === false) {
                throw new RuntimeException('Báo giá được chọn không còn hợp lệ.');
            }
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND company_id=? AND role='technician' AND status='active' FOR UPDATE");
            $stmt->execute([$technicianId, $companyId]);
            $technician = $stmt->fetch();
            if (!$technician) {
                throw new RuntimeException('Kỹ thuật viên phải đang hoạt động và thuộc doanh nghiệp được chọn.');
            }
            OrderState::assertTransition((string)$order['status'], OrderState::TECH_ASSIGNED);
            $stmt = $pdo->prepare('UPDATE orders SET technician_id=?,status=?,updated_at=NOW() WHERE id=?');
            $stmt->execute([$technicianId, OrderState::TECH_ASSIGNED, $orderId]);
            MarketplaceService::appendTimeline($pdo, $orderId, 'order', 'technician_assigned', (string)$order['status'], OrderState::TECH_ASSIGNED, null, $companyId, $actorUserId, 'company', 'Doanh nghiệp phân công kỹ thuật viên ' . $technician['name'] . '.');
            MarketplaceService::notifyUser($pdo, $technicianId, 'technician_assigned', 'Bạn có công việc mới', 'Bạn được phân công xử lý đơn ' . $order['order_code'] . '.', $orderId);
            MarketplaceService::notifyUser($pdo, (int)$order['customer_id'], 'technician_assigned', 'Đã phân công kỹ thuật viên', 'Đơn ' . $order['order_code'] . ' đã có kỹ thuật viên phụ trách.', $orderId);
            MarketplaceService::audit($pdo, $actorUserId, 'company', 'assign_technician', 'order', $orderId, 'Technician #' . $technicianId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function updateTechnicianProgress(int $orderId, int $technicianId, string $newStatus, string $note): void
    {
        if ($newStatus === OrderState::WORK_DONE) {
            throw new RuntimeException('Vui lòng hoàn tất biên bản kỹ thuật trước khi gửi doanh nghiệp xác nhận.');
        }
        if ($newStatus === OrderState::COMPLETED) {
            throw new RuntimeException('Doanh nghiệp xác nhận chi phí và hoàn thành đơn.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $order = self::lockOrder($pdo, $orderId);
            if ((int)$order['technician_id'] !== $technicianId || $order['company_id'] === null) {
                throw new RuntimeException('Bạn không được phân công đơn này.');
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id=? AND company_id=? AND role='technician' AND status='active'");
            $stmt->execute([$technicianId, (int)$order['company_id']]);
            if ((int)$stmt->fetchColumn() !== 1) {
                throw new RuntimeException('Kỹ thuật viên không còn thuộc doanh nghiệp được chọn.');
            }
            OrderState::assertTechnicianTransition((string)$order['status'], $newStatus);
            $stmt = $pdo->prepare('UPDATE orders SET status=?,updated_at=NOW() WHERE id=?');
            $stmt->execute([$newStatus, $orderId]);
            $timelineNote = $note ?: 'Kỹ thuật viên cập nhật tiến trình.';
            MarketplaceService::appendTimeline($pdo, $orderId, 'order', 'technician_progress', (string)$order['status'], $newStatus, null, (int)$order['company_id'], $technicianId, 'technician', $timelineNote);
            MarketplaceService::notifyUser($pdo, (int)$order['customer_id'], 'technician_progress', 'Cập nhật tiến trình', 'Đơn ' . $order['order_code'] . ' chuyển sang trạng thái ' . $newStatus . '.', $orderId);
            MarketplaceService::audit($pdo, $technicianId, 'technician', 'update_order_status', 'order', $orderId, (string)$order['status'] . ' -> ' . $newStatus);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function submitRepairReport(
        int $orderId,
        int $technicianId,
        string $actualIssue,
        string $resolution,
        string $postRepairAdvice
    ): void {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $order = self::lockOrder($pdo, $orderId);
            self::assertAssignedTechnician($pdo, $order, $technicianId);
            if ((string)$order['status'] !== OrderState::REPAIRING) {
                throw new RuntimeException('Chỉ có thể gửi biên bản khi đơn đang sửa chữa.');
            }
            [$actualIssue, $resolution, $advice] = self::validateRepairReport($actualIssue, $resolution, $postRepairAdvice);

            $existing = $pdo->prepare('SELECT id FROM repair_reports WHERE order_id=? FOR UPDATE');
            $existing->execute([$orderId]);
            if ($existing->fetchColumn() !== false) {
                throw new RuntimeException('Đơn đã có biên bản kỹ thuật.');
            }

            $stmt = $pdo->prepare('INSERT INTO repair_reports(order_id,technician_id,actual_issue,resolution,post_repair_advice,submitted_at) VALUES(?,?,?,?,?,NOW())');
            $stmt->execute([$orderId, $technicianId, $actualIssue, $resolution, $advice]);

            OrderState::assertTransition((string)$order['status'], OrderState::WORK_DONE);
            $stmt = $pdo->prepare('UPDATE orders SET status=?,updated_at=NOW() WHERE id=?');
            $stmt->execute([OrderState::WORK_DONE, $orderId]);

            MarketplaceService::appendTimeline($pdo, $orderId, 'order', 'repair_report_submitted', OrderState::REPAIRING, OrderState::REPAIRING, null, (int)$order['company_id'], $technicianId, 'technician', 'Kỹ thuật viên đã gửi biên bản kỹ thuật.');
            MarketplaceService::appendTimeline($pdo, $orderId, 'order', 'work_done', OrderState::REPAIRING, OrderState::WORK_DONE, null, (int)$order['company_id'], $technicianId, 'technician', 'Kỹ thuật viên xác nhận đã sửa xong, chờ doanh nghiệp kiểm tra kết quả và chi phí cuối.');
            MarketplaceService::notifyUser($pdo, (int)$order['customer_id'], 'work_done', 'Kỹ thuật viên đã sửa xong', 'Đơn ' . $order['order_code'] . ' đang chờ doanh nghiệp xác nhận kết quả và chi phí cuối.', $orderId);
            MarketplaceService::notifyCompanyUsers($pdo, (int)$order['company_id'], 'work_done', 'Công việc chờ xác nhận', 'Kỹ thuật viên đã sửa xong đơn ' . $order['order_code'] . '. Vui lòng xác nhận kết quả và chi phí cuối.', $orderId);
            MarketplaceService::audit($pdo, $technicianId, 'technician', 'submit_repair_report', 'order', $orderId, 'Repair report submitted; repairing -> work_done');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function updateRepairReport(
        int $orderId,
        int $technicianId,
        string $actualIssue,
        string $resolution,
        string $postRepairAdvice
    ): bool {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $order = self::lockOrder($pdo, $orderId);
            self::assertAssignedTechnician($pdo, $order, $technicianId);
            if ((string)$order['status'] !== OrderState::WORK_DONE) {
                throw new RuntimeException('Chỉ có thể chỉnh sửa biên bản khi đang chờ doanh nghiệp xác nhận.');
            }
            [$actualIssue, $resolution, $advice] = self::validateRepairReport($actualIssue, $resolution, $postRepairAdvice);

            $stmt = $pdo->prepare('SELECT * FROM repair_reports WHERE order_id=? FOR UPDATE');
            $stmt->execute([$orderId]);
            $report = $stmt->fetch();
            if ($report && (int)$report['technician_id'] !== $technicianId) {
                throw new RuntimeException('Biên bản không thuộc kỹ thuật viên được phân công.');
            }
            if ($report) {
                $unchanged = (string)$report['actual_issue'] === $actualIssue
                    && (string)$report['resolution'] === $resolution
                    && ($report['post_repair_advice'] === null ? null : (string)$report['post_repair_advice']) === $advice;
                if ($unchanged) {
                    $pdo->commit();
                    return false;
                }
                $stmt = $pdo->prepare('UPDATE repair_reports SET actual_issue=?,resolution=?,post_repair_advice=?,updated_at=NOW() WHERE id=?');
                $stmt->execute([$actualIssue, $resolution, $advice, (int)$report['id']]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO repair_reports(order_id,technician_id,actual_issue,resolution,post_repair_advice,submitted_at) VALUES(?,?,?,?,?,NOW())');
                $stmt->execute([$orderId, $technicianId, $actualIssue, $resolution, $advice]);
            }

            MarketplaceService::appendTimeline($pdo, $orderId, 'order', 'repair_report_updated', OrderState::WORK_DONE, OrderState::WORK_DONE, null, (int)$order['company_id'], $technicianId, 'technician', $report ? 'Kỹ thuật viên đã cập nhật biên bản kỹ thuật.' : 'Kỹ thuật viên đã bổ sung biên bản kỹ thuật còn thiếu.');
            MarketplaceService::audit($pdo, $technicianId, 'technician', 'update_repair_report', 'order', $orderId, $report ? 'Repair report updated' : 'Missing repair report created during work_done');
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function completeOrder(int $orderId, int $companyId, int $actorUserId, int $finalPrice, string $note): void
    {
        if ($finalPrice < 0) {
            throw new RuntimeException('Chi phí cuối không hợp lệ.');
        }
        if (trim($note) === '') {
            throw new RuntimeException('Vui lòng xác nhận kết quả công việc trước khi hoàn thành đơn.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $order = self::lockOrder($pdo, $orderId);
            self::assertCompanyActor($pdo, $actorUserId, $companyId);
            if ((int)$order['company_id'] !== $companyId || !$order['technician_id'] || !$order['selected_quote_id']) {
                throw new RuntimeException('Chỉ doanh nghiệp được chọn, sau khi phân công kỹ thuật viên, mới được hoàn thành đơn.');
            }
            if ((string)$order['status'] !== OrderState::WORK_DONE) {
                throw new RuntimeException('Chỉ có thể hoàn thành sau khi kỹ thuật viên xác nhận đã sửa xong.');
            }
            $report = $pdo->prepare("SELECT id FROM repair_reports WHERE order_id=? AND technician_id=? AND TRIM(actual_issue)<>'' AND TRIM(resolution)<>'' LIMIT 1 FOR UPDATE");
            $report->execute([$orderId, (int)$order['technician_id']]);
            if ($report->fetchColumn() === false) {
                throw new RuntimeException('Chờ kỹ thuật viên hoàn thiện biên bản kỹ thuật.');
            }
            $stmt = $pdo->prepare("SELECT id FROM order_company_requests WHERE order_id=? AND company_id=? AND status='selected' LIMIT 1 FOR UPDATE");
            $stmt->execute([$orderId, $companyId]);
            if ($stmt->fetchColumn() === false) {
                throw new RuntimeException('Request được chọn không còn hợp lệ.');
            }
            $stmt = $pdo->prepare("SELECT id FROM quotes WHERE id=? AND order_id=? AND company_id=? AND status='accepted_by_customer' LIMIT 1 FOR UPDATE");
            $stmt->execute([(int)$order['selected_quote_id'], $orderId, $companyId]);
            if ($stmt->fetchColumn() === false) {
                throw new RuntimeException('Báo giá được chọn không còn hợp lệ.');
            }
            OrderState::assertTransition((string)$order['status'], OrderState::COMPLETED);
            $stmt = $pdo->prepare('UPDATE orders SET status=?,final_price=?,completed_at=NOW(),updated_at=NOW() WHERE id=?');
            $stmt->execute([OrderState::COMPLETED, $finalPrice, $orderId]);
            MarketplaceService::appendTimeline($pdo, $orderId, 'order', 'order_completed', (string)$order['status'], OrderState::COMPLETED, null, $companyId, $actorUserId, 'company', $note);
            MarketplaceService::notifyUser($pdo, (int)$order['customer_id'], 'order_completed', 'Đơn đã hoàn thành', 'Đơn ' . $order['order_code'] . ' đã hoàn thành. Bạn có thể đánh giá dịch vụ.', $orderId);
            MarketplaceService::audit($pdo, $actorUserId, 'company', 'complete_order', 'order', $orderId, 'Final price: ' . $finalPrice);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function lockOrder(PDO $pdo, int $orderId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new RuntimeException('Không tìm thấy đơn.');
        }
        return $order;
    }

    private static function assertAssignedTechnician(PDO $pdo, array $order, int $technicianId): void
    {
        if ((int)$order['technician_id'] !== $technicianId || $order['company_id'] === null) {
            throw new RuntimeException('Bạn không được phân công đơn này.');
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id=? AND company_id=? AND role='technician' AND status='active'");
        $stmt->execute([$technicianId, (int)$order['company_id']]);
        if ((int)$stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Kỹ thuật viên không còn thuộc doanh nghiệp được chọn.');
        }
    }

    private static function validateRepairReport(string $actualIssue, string $resolution, string $postRepairAdvice): array
    {
        $actualIssue = trim($actualIssue);
        $resolution = trim($resolution);
        $postRepairAdvice = trim($postRepairAdvice);
        if ($actualIssue === '') {
            throw new RuntimeException('Vui lòng nhập vấn đề thực tế phát hiện.');
        }
        if ($resolution === '') {
            throw new RuntimeException('Vui lòng nhập cách khắc phục thực tế.');
        }
        foreach (['Vấn đề thực tế phát hiện' => $actualIssue, 'Cách khắc phục thực tế' => $resolution, 'Kết quả / khuyến nghị sau sửa' => $postRepairAdvice] as $label => $value) {
            $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
            if ($length > self::REPAIR_REPORT_TEXT_LIMIT) {
                throw new RuntimeException($label . ' không được vượt quá ' . self::REPAIR_REPORT_TEXT_LIMIT . ' ký tự.');
            }
        }
        return [$actualIssue, $resolution, $postRepairAdvice === '' ? null : $postRepairAdvice];
    }

    private static function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private static function assertCompanyActor(PDO $pdo, int $actorUserId, int $companyId): void
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM users u
             JOIN companies c ON c.id=u.company_id
             WHERE u.id=? AND u.company_id=? AND u.role='company' AND u.status='active'
               AND c.legal_status='verified' AND c.account_status='active'"
        );
        $stmt->execute([$actorUserId, $companyId]);
        if ((int)$stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Tài khoản không có quyền thao tác cho doanh nghiệp này.');
        }
    }
}
