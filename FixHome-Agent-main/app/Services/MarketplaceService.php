<?php
declare(strict_types=1);

final class MarketplaceService
{
    private const ACTIVE_REQUEST_STATUSES = ['invited', 'viewed', 'quote_submitted'];
    private const ACTIVE_QUOTE_STATUSES = ['submitted', 'changes_requested'];

    public static function inviteMatchingCompanies(
        int $orderId,
        int $actorUserId,
        string $actorRole = 'admin',
        ?array $companyIds = null
    ): array {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order) {
                throw new RuntimeException('Không tìm thấy đơn.');
            }
            $invited = self::inviteForOrderInTransaction($pdo, $order, $actorUserId, $actorRole, $companyIds);
            $pdo->commit();
            return $invited;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function inviteForOrderInTransaction(
        PDO $pdo,
        array $order,
        int $actorUserId,
        string $actorRole,
        ?array $companyIds = null
    ): array {
        if (OrderState::isTerminal((string)$order['status']) || $order['company_id'] !== null) {
            throw new RuntimeException('Đơn đã đóng hoặc đã có doanh nghiệp được chọn.');
        }
        if (!in_array($order['status'], [OrderState::PENDING_DISTRIBUTION, OrderState::WAITING_QUOTE, OrderState::QUOTED], true)) {
            throw new RuntimeException('Đơn không ở trạng thái có thể mời doanh nghiệp.');
        }

        $params = [(int)$order['category_id']];
        $where = '';
        if ($companyIds !== null) {
            $companyIds = array_values(array_unique(array_filter(array_map('intval', $companyIds))));
            if (!$companyIds) {
                throw new RuntimeException('Vui lòng chọn ít nhất một doanh nghiệp.');
            }
            $where = ' AND c.id IN (' . implode(',', array_fill(0, count($companyIds), '?')) . ')';
            array_push($params, ...$companyIds);
        }
        $stmt = $pdo->prepare(
            "SELECT c.id,c.name
             FROM companies c
             JOIN company_service_categories csc ON csc.company_id=c.id AND csc.category_id=?
             WHERE c.legal_status='verified' AND c.account_status='active'{$where}
             ORDER BY c.id"
        );
        $stmt->execute($params);
        $companies = $stmt->fetchAll();
        if (!$companies) {
            throw new RuntimeException('Chưa có doanh nghiệp đã xác minh, đang hoạt động và phù hợp nhóm dịch vụ.');
        }

        $insert = $pdo->prepare(
            "INSERT INTO order_company_requests
                (order_id,company_id,status,invited_by_user_id,invited_at)
             VALUES(?,?,'invited',?,NOW())
             ON DUPLICATE KEY UPDATE id=id"
        );
        $invited = [];
        foreach ($companies as $company) {
            $insert->execute([(int)$order['id'], (int)$company['id'], $actorUserId]);
            if ($insert->rowCount() === 1) {
                $invited[] = $company;
                self::notifyCompanyUsers(
                    $pdo,
                    (int)$company['id'],
                    'order_invited',
                    'Có cơ hội dịch vụ mới',
                    'FixHome mời doanh nghiệp báo giá cho đơn ' . $order['order_code'] . '.',
                    (int)$order['id']
                );
            }
        }
        if (!$invited) {
            throw new RuntimeException('Các doanh nghiệp phù hợp đã được mời trước đó.');
        }

        $fromStatus = (string)$order['status'];
        $toStatus = $fromStatus === OrderState::PENDING_DISTRIBUTION ? OrderState::WAITING_QUOTE : $fromStatus;
        if ($toStatus !== $fromStatus) {
            OrderState::assertTransition($fromStatus, $toStatus);
            $update = $pdo->prepare('UPDATE orders SET status=?,updated_at=NOW() WHERE id=?');
            $update->execute([$toStatus, (int)$order['id']]);
        }
        self::appendTimeline(
            $pdo,
            (int)$order['id'],
            'order',
            'companies_invited',
            $fromStatus,
            $toStatus,
            null,
            null,
            $actorUserId,
            $actorRole,
            'FixHome đã mời ' . count($invited) . ' doanh nghiệp phù hợp gửi báo giá.'
        );
        self::audit($pdo, $actorUserId, $actorRole, 'invite_companies', 'order', (int)$order['id'], 'Invited companies: ' . implode(',', array_column($invited, 'id')));
        return $invited;
    }

    public static function submitQuote(
        int $orderId,
        int $companyId,
        int $actorUserId,
        int $minPrice,
        int $maxPrice,
        string $note,
        string $estimatedArrival
    ): int {
        if ($minPrice < 0 || $maxPrice < $minPrice) {
            throw new RuntimeException('Khoảng giá báo không hợp lệ.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $order = self::lockOrder($pdo, $orderId);
            self::assertOpenWithoutWinner($order, 'Đơn đã đóng hoặc đã chọn doanh nghiệp.');
            $request = self::lockRequest($pdo, $orderId, $companyId);
            if (!$request || !in_array($request['status'], self::ACTIVE_REQUEST_STATUSES, true)) {
                throw new RuntimeException('Doanh nghiệp chưa được mời hoặc lời mời không còn hiệu lực.');
            }
            self::assertCompanyActor($pdo, $actorUserId, $companyId);

            $previousStatus = (string)$request['status'];
            if ($request['current_quote_id'] !== null) {
                $stmt = $pdo->prepare('SELECT * FROM quotes WHERE id=? FOR UPDATE');
                $stmt->execute([(int)$request['current_quote_id']]);
                $current = $stmt->fetch();
                if ($current && in_array($current['status'], self::ACTIVE_QUOTE_STATUSES, true)) {
                    $stmt = $pdo->prepare("UPDATE quotes SET status='superseded',responded_at=NOW() WHERE id=?");
                    $stmt->execute([(int)$current['id']]);
                }
            }

            $stmt = $pdo->prepare('SELECT COALESCE(MAX(revision),0)+1 FROM quotes WHERE order_id=? AND company_id=?');
            $stmt->execute([$orderId, $companyId]);
            $revision = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare(
                "INSERT INTO quotes
                    (order_company_request_id,order_id,company_id,revision,min_price,max_price,note,estimated_arrival,status,created_by_user_id,submitted_at)
                 VALUES(?,?,?,?,?,?,?,?,'submitted',?,NOW())"
            );
            $stmt->execute([(int)$request['id'], $orderId, $companyId, $revision, $minPrice, $maxPrice, $note ?: null, $estimatedArrival ?: null, $actorUserId]);
            $quoteId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare("UPDATE order_company_requests SET status='quote_submitted',current_quote_id=?,quote_submitted_at=NOW(),viewed_at=COALESCE(viewed_at,NOW()) WHERE id=?");
            $stmt->execute([$quoteId, (int)$request['id']]);

            $fromOrderStatus = (string)$order['status'];
            $toOrderStatus = $fromOrderStatus;
            if ($fromOrderStatus === OrderState::WAITING_QUOTE) {
                OrderState::assertTransition($fromOrderStatus, OrderState::QUOTED);
                $toOrderStatus = OrderState::QUOTED;
                $stmt = $pdo->prepare('UPDATE orders SET status=?,updated_at=NOW() WHERE id=?');
                $stmt->execute([$toOrderStatus, $orderId]);
                self::appendTimeline($pdo, $orderId, 'order', 'order_quoted', $fromOrderStatus, $toOrderStatus, null, null, $actorUserId, 'company', 'Đơn đã nhận được báo giá hợp lệ.');
            } elseif ($fromOrderStatus !== OrderState::QUOTED) {
                throw new RuntimeException('Đơn không ở trạng thái nhận báo giá.');
            }

            self::appendTimeline(
                $pdo,
                $orderId,
                'company_request',
                $revision > 1 ? 'quote_revised' : 'quote_submitted',
                $previousStatus,
                'quote_submitted',
                (int)$request['id'],
                $companyId,
                $actorUserId,
                'company',
                'Doanh nghiệp gửi báo giá lần ' . $revision . '.'
            );
            self::notifyUser($pdo, (int)$order['customer_id'], 'quote_submitted', 'Bạn có báo giá mới', 'Một doanh nghiệp đã gửi báo giá cho đơn ' . $order['order_code'] . '.', $orderId);
            self::audit($pdo, $actorUserId, 'company', 'submit_quote', 'quote', $quoteId, 'Order #' . $orderId . ', revision ' . $revision);
            $pdo->commit();
            return $quoteId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function declineRequest(int $orderId, int $companyId, int $actorUserId, string $reason): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $order = self::lockOrder($pdo, $orderId);
            self::assertOpenWithoutWinner($order, 'Đơn đã đóng hoặc doanh nghiệp đã được chọn.');
            $request = self::lockRequest($pdo, $orderId, $companyId);
            if (!$request || !in_array($request['status'], self::ACTIVE_REQUEST_STATUSES, true)) {
                throw new RuntimeException('Lời mời không tồn tại hoặc đã đóng.');
            }
            self::assertCompanyActor($pdo, $actorUserId, $companyId);
            if ($request['current_quote_id'] !== null) {
                $stmt = $pdo->prepare("UPDATE quotes SET status='withdrawn_by_company',responded_at=NOW() WHERE id=? AND status IN ('submitted','changes_requested')");
                $stmt->execute([(int)$request['current_quote_id']]);
            }
            $stmt = $pdo->prepare("UPDATE order_company_requests SET status='declined',closed_at=NOW(),close_reason=? WHERE id=?");
            $stmt->execute([$reason ?: 'Doanh nghiệp từ chối cơ hội.', (int)$request['id']]);
            self::appendTimeline($pdo, $orderId, 'company_request', 'company_declined', (string)$request['status'], 'declined', (int)$request['id'], $companyId, $actorUserId, 'company', $reason ?: 'Doanh nghiệp từ chối cơ hội.');

            if ($order['status'] === OrderState::QUOTED && self::countSubmittedQuotes($pdo, $orderId) === 0) {
                OrderState::assertTransition(OrderState::QUOTED, OrderState::WAITING_QUOTE);
                $stmt = $pdo->prepare('UPDATE orders SET status=?,updated_at=NOW() WHERE id=?');
                $stmt->execute([OrderState::WAITING_QUOTE, $orderId]);
                self::appendTimeline($pdo, $orderId, 'order', 'waiting_for_quote', OrderState::QUOTED, OrderState::WAITING_QUOTE, null, null, $actorUserId, 'company', 'Đơn tiếp tục chờ báo giá từ các doanh nghiệp khác.');
            }
            self::audit($pdo, $actorUserId, 'company', 'decline_request', 'order_company_request', (int)$request['id'], $reason);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function selectQuote(int $orderId, int $quoteId, int $customerId): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $order = self::lockOrder($pdo, $orderId);
            if ((int)$order['customer_id'] !== $customerId) {
                throw new RuntimeException('Bạn không có quyền chọn báo giá của đơn này.');
            }
            self::assertOpenWithoutWinner($order, 'Đơn đã đóng hoặc đã có báo giá được chọn.');
            if ($order['status'] !== OrderState::QUOTED) {
                throw new RuntimeException('Đơn chưa ở trạng thái có thể chọn báo giá.');
            }

            $stmt = $pdo->prepare('SELECT * FROM quotes WHERE id=? AND order_id=? FOR UPDATE');
            $stmt->execute([$quoteId, $orderId]);
            $quote = $stmt->fetch();
            if (!$quote || $quote['status'] !== 'submitted') {
                throw new RuntimeException('Báo giá không còn hiệu lực.');
            }
            $stmt = $pdo->prepare('SELECT * FROM order_company_requests WHERE id=? AND order_id=? AND company_id=? FOR UPDATE');
            $stmt->execute([(int)$quote['order_company_request_id'], $orderId, (int)$quote['company_id']]);
            $request = $stmt->fetch();
            if (!$request || $request['status'] !== 'quote_submitted' || (int)$request['current_quote_id'] !== $quoteId) {
                throw new RuntimeException('Chỉ báo giá hiện hành của một lời mời còn hiệu lực mới được chọn.');
            }
            $stmt = $pdo->prepare("SELECT id FROM companies WHERE id=? AND legal_status='verified' AND account_status='active' LIMIT 1 FOR UPDATE");
            $stmt->execute([(int)$quote['company_id']]);
            if ($stmt->fetchColumn() === false) {
                throw new RuntimeException('Doanh nghiệp gửi báo giá không còn đủ điều kiện được lựa chọn.');
            }

            $stmt = $pdo->prepare("SELECT * FROM order_company_requests WHERE order_id=? FOR UPDATE");
            $stmt->execute([$orderId]);
            $requests = $stmt->fetchAll();
            $stmt = $pdo->prepare("SELECT * FROM quotes WHERE order_id=? FOR UPDATE");
            $stmt->execute([$orderId]);
            $stmt->fetchAll();

            $stmt = $pdo->prepare("UPDATE order_company_requests SET status='selected',selected_at=NOW(),closed_at=NULL,close_reason=NULL WHERE id=?");
            $stmt->execute([(int)$request['id']]);
            $stmt = $pdo->prepare("UPDATE order_company_requests SET status='not_selected',closed_at=NOW(),close_reason='Khách hàng đã chọn doanh nghiệp khác.' WHERE order_id=? AND id<>? AND status IN ('invited','viewed','quote_submitted')");
            $stmt->execute([$orderId, (int)$request['id']]);
            $stmt = $pdo->prepare("UPDATE quotes SET status='accepted_by_customer',responded_at=NOW() WHERE id=?");
            $stmt->execute([$quoteId]);
            $stmt = $pdo->prepare("UPDATE quotes SET status='rejected_not_selected',responded_at=NOW() WHERE order_id=? AND company_id<>? AND status IN ('submitted','changes_requested')");
            $stmt->execute([$orderId, (int)$quote['company_id']]);

            OrderState::assertTransition((string)$order['status'], OrderState::QUOTE_ACCEPTED);
            $stmt = $pdo->prepare('UPDATE orders SET company_id=?,selected_quote_id=?,status=?,updated_at=NOW() WHERE id=?');
            $stmt->execute([(int)$quote['company_id'], $quoteId, OrderState::QUOTE_ACCEPTED, $orderId]);
            self::appendTimeline($pdo, $orderId, 'order', 'quote_accepted', (string)$order['status'], OrderState::QUOTE_ACCEPTED, (int)$request['id'], (int)$quote['company_id'], $customerId, 'customer', 'Khách hàng đã chọn báo giá hiện hành.');

            foreach ($requests as $competitor) {
                if ((int)$competitor['id'] === (int)$request['id'] || !in_array($competitor['status'], self::ACTIVE_REQUEST_STATUSES, true)) {
                    continue;
                }
                self::appendTimeline($pdo, $orderId, 'company_request', 'not_selected', (string)$competitor['status'], 'not_selected', (int)$competitor['id'], (int)$competitor['company_id'], $customerId, 'customer', 'Khách hàng đã chọn doanh nghiệp khác.');
                self::notifyCompanyUsers($pdo, (int)$competitor['company_id'], 'not_selected', 'Báo giá không được chọn', 'Khách hàng đã chọn doanh nghiệp khác cho đơn ' . $order['order_code'] . '.', $orderId);
            }
            self::notifyCompanyUsers($pdo, (int)$quote['company_id'], 'quote_accepted', 'Báo giá được chấp nhận', 'Khách hàng đã chọn báo giá cho đơn ' . $order['order_code'] . '.', $orderId);
            self::notifyUser($pdo, $customerId, 'quote_accepted', 'Đã chọn doanh nghiệp', 'Bạn đã chọn báo giá cho đơn ' . $order['order_code'] . '.', $orderId);
            self::audit($pdo, $customerId, 'customer', 'select_quote', 'quote', $quoteId, 'Order #' . $orderId . ', company #' . $quote['company_id']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function requestQuoteChanges(int $orderId, int $quoteId, int $customerId): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $order = self::lockOrder($pdo, $orderId);
            if ((int)$order['customer_id'] !== $customerId || $order['company_id'] !== null || $order['status'] !== OrderState::QUOTED) {
                throw new RuntimeException('Không thể yêu cầu điều chỉnh báo giá này.');
            }
            $stmt = $pdo->prepare(
                'SELECT q.*,r.current_quote_id,r.status request_status
                 FROM quotes q
                 JOIN order_company_requests r
                   ON r.id=q.order_company_request_id
                  AND r.order_id=q.order_id
                  AND r.company_id=q.company_id
                 WHERE q.id=? AND q.order_id=? AND r.order_id=?
                 FOR UPDATE'
            );
            $stmt->execute([$quoteId, $orderId, $orderId]);
            $quote = $stmt->fetch();
            if (!$quote || $quote['status'] !== 'submitted' || (int)$quote['current_quote_id'] !== $quoteId || $quote['request_status'] !== 'quote_submitted') {
                throw new RuntimeException('Báo giá không còn là báo giá hiện hành.');
            }
            $stmt = $pdo->prepare("UPDATE quotes SET status='changes_requested',responded_at=NOW() WHERE id=?");
            $stmt->execute([$quoteId]);
            self::appendTimeline(
                $pdo,
                $orderId,
                'company_request',
                'quote_changes_requested',
                'submitted',
                'changes_requested',
                (int)$quote['order_company_request_id'],
                (int)$quote['company_id'],
                $customerId,
                'customer',
                'Khách hàng yêu cầu doanh nghiệp điều chỉnh báo giá.'
            );
            if (self::countSubmittedQuotes($pdo, $orderId) === 0) {
                OrderState::assertTransition(OrderState::QUOTED, OrderState::WAITING_QUOTE);
                $stmt = $pdo->prepare('UPDATE orders SET status=?,updated_at=NOW() WHERE id=?');
                $stmt->execute([OrderState::WAITING_QUOTE, $orderId]);
                self::appendTimeline($pdo, $orderId, 'order', 'quote_changes_requested', OrderState::QUOTED, OrderState::WAITING_QUOTE, (int)$quote['order_company_request_id'], (int)$quote['company_id'], $customerId, 'customer', 'Khách hàng yêu cầu doanh nghiệp điều chỉnh báo giá.');
            }
            self::notifyCompanyUsers($pdo, (int)$quote['company_id'], 'quote_changes_requested', 'Khách hàng yêu cầu điều chỉnh báo giá', 'Vui lòng cập nhật báo giá cho đơn ' . $order['order_code'] . '.', $orderId);
            self::audit($pdo, $customerId, 'customer', 'request_quote_changes', 'quote', $quoteId, 'Order #' . $orderId);
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

    private static function lockRequest(PDO $pdo, int $orderId, int $companyId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM order_company_requests WHERE order_id=? AND company_id=? FOR UPDATE');
        $stmt->execute([$orderId, $companyId]);
        return $stmt->fetch() ?: null;
    }

    private static function assertOpenWithoutWinner(array $order, string $message): void
    {
        if (OrderState::isTerminal((string)$order['status']) || $order['company_id'] !== null || $order['selected_quote_id'] !== null) {
            throw new RuntimeException($message);
        }
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

    private static function countSubmittedQuotes(PDO $pdo, int $orderId): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quotes WHERE order_id=? AND status='submitted'");
        $stmt->execute([$orderId]);
        return (int)$stmt->fetchColumn();
    }

    public static function appendTimeline(
        PDO $pdo,
        int $orderId,
        string $scope,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $requestId,
        ?int $companyId,
        ?int $actorUserId,
        ?string $actorRole,
        string $note
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO order_timeline(order_id,event_scope,event_type,from_status,to_status,order_company_request_id,company_id,actor_user_id,actor_role,note) VALUES(?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$orderId, $scope, $eventType, $fromStatus, $toStatus, $requestId, $companyId, $actorUserId, $actorRole, $note]);
    }

    public static function audit(PDO $pdo, ?int $actorUserId, ?string $actorRole, string $action, ?string $entityType, ?int $entityId, string $detail): void
    {
        $stmt = $pdo->prepare('INSERT INTO audit_logs(user_id,actor_role,action,entity_type,entity_id,detail,ip_address) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute([$actorUserId, $actorRole, $action, $entityType, $entityId, $detail, $_SERVER['REMOTE_ADDR'] ?? null]);
    }

    public static function notifyUser(PDO $pdo, int $userId, string $type, string $title, string $message, ?int $orderId = null): void
    {
        $stmt = $pdo->prepare('INSERT INTO notifications(target_user_id,order_id,notification_type,title,message) VALUES(?,?,?,?,?)');
        $stmt->execute([$userId, $orderId, $type, $title, $message]);
    }

    public static function notifyCompanyUsers(PDO $pdo, int $companyId, string $type, string $title, string $message, ?int $orderId = null): void
    {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE company_id=? AND role='company' AND status='active' ORDER BY id");
        $stmt->execute([$companyId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
            self::notifyUser($pdo, (int)$userId, $type, $title, $message, $orderId);
        }
    }
}
