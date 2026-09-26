<?php
declare(strict_types=1);

final class CompanyController
{
    private static function companyFor(array $user): array
    {
        if (!$user['company_id']) {
            http_response_code(403);
            exit('Tài khoản chưa liên kết doanh nghiệp.');
        }
        $stmt = db()->prepare('SELECT * FROM companies WHERE id=? LIMIT 1');
        $stmt->execute([$user['company_id']]);
        $company = $stmt->fetch();
        if (!$company) {
            http_response_code(404);
            exit('Không tìm thấy doanh nghiệp.');
        }
        if ($company['legal_status'] !== 'verified' || $company['account_status'] !== 'active') {
            http_response_code(403);
            exit('Doanh nghiệp không còn ở trạng thái đã xác minh và hoạt động.');
        }
        return $company;
    }

    public static function dashboard(): void
    {
        $u = require_role('company');
        $company = self::companyFor($u);
        $stmt = db()->prepare(
            "SELECT
                COALESCE(SUM(o.company_id IS NULL AND o.status NOT IN ('completed','cancelled') AND (r.status IN ('invited','viewed') OR q.status='changes_requested')),0) opportunities,
                COALESCE(SUM(o.company_id IS NULL AND o.status NOT IN ('completed','cancelled') AND r.status='quote_submitted' AND q.status='submitted'),0) waiting_customer,
                COALESCE(SUM(r.status='selected' AND o.company_id=? AND o.status IN ('quote_accepted','tech_assigned','tech_accepted','on_the_way','inspecting','repairing')),0) active,
                COALESCE(SUM(r.status='selected' AND o.company_id=? AND o.status='work_done'),0) awaiting_confirmation,
                COALESCE(SUM(CASE WHEN r.status='selected' AND o.status='completed' AND o.company_id=? THEN o.final_price ELSE 0 END),0) revenue
             FROM order_company_requests r
             JOIN orders o ON o.id=r.order_id
             LEFT JOIN quotes q ON q.id=r.current_quote_id
             WHERE r.company_id=?"
        );
        $stmt->execute([$company['id'],$company['id'],$company['id'],$company['id']]);
        $stats = $stmt->fetch();
        $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE company_id=? AND role='technician' AND status='active'");
        $stmt->execute([$company['id']]);
        $stats['techs'] = (int)$stmt->fetchColumn();
        render('company/dashboard', compact('u','company','stats'), 'Doanh nghiệp');
    }

    public static function orders(): void
    {
        $u = require_role('company');
        $company = self::companyFor($u);
        $orders = self::ordersForCompany((int)$company['id']);
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'category' => max(0,(int)($_GET['category'] ?? 0)),
            'group' => (string)($_GET['group'] ?? 'all'),
        ];
        $filters['group'] = RoleWorkView::normalizeCompanyGroup($filters['group']);
        $needle = function_exists('mb_strtolower') ? mb_strtolower($filters['q'],'UTF-8') : strtolower($filters['q']);
        $orders = array_values(array_filter($orders, static function(array $order) use ($filters,$needle): bool {
            if ($filters['category'] > 0 && (int)$order['category_id'] !== $filters['category']) return false;
            if ($needle !== '') {
                $haystack = implode(' ', [(string)$order['order_code'],(string)$order['category_name'],(string)($order['description'] ?? ''),(string)($order['customer_name'] ?? '')]);
                $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack,'UTF-8') : strtolower($haystack);
                if (!str_contains($haystack,$needle)) return false;
            }
            return true;
        }));
        $activeOpportunities = [];
        $waitingCustomer = [];
        $selectedActive = [];
        $awaitingConfirmation = [];
        $closedOrders = [];
        $groupCounts = array_fill_keys(array_keys(RoleWorkView::companyGroups()), 0);
        foreach ($orders as $order) {
            $group = RoleWorkView::companyGroupFor($order, (int)$company['id']);
            $groupCounts[$group]++;
            if ($group === 'opportunities') $activeOpportunities[] = $order;
            elseif ($group === 'waiting_customer') $waitingCustomer[] = $order;
            elseif ($group === 'active') $selectedActive[] = $order;
            elseif ($group === 'confirmation') $awaitingConfirmation[] = $order;
            else $closedOrders[] = $order;
        }
        $technicianRecommendations = TechnicianService::assignmentRecommendations((int)$company['id'],$orders);
        $categories = db()->query('SELECT id,name FROM service_categories WHERE active=1 ORDER BY id')->fetchAll();
        $groups = RoleWorkView::companyGroups();
        render('company/orders', compact('company','activeOpportunities','waitingCustomer','selectedActive','awaitingConfirmation','closedOrders','technicianRecommendations','filters','categories','groups','groupCounts'), 'Cơ hội và đơn được chọn');
    }

    private static function ordersForCompany(int $companyId): array
    {
        $stmt = db()->prepare(
            "SELECT o.*,r.id request_id,r.status request_status,r.current_quote_id,sc.name category_name,t.name technician_name,t.name repair_report_technician_name,
                    rr.id repair_report_id,rr.actual_issue,rr.resolution,rr.post_repair_advice,rr.submitted_at repair_report_submitted_at,rr.updated_at repair_report_updated_at
             FROM order_company_requests r
             JOIN orders o ON o.id=r.order_id
             JOIN service_categories sc ON sc.id=o.category_id
             LEFT JOIN users t ON t.id=o.technician_id
             LEFT JOIN repair_reports rr ON rr.order_id=o.id AND rr.technician_id=o.technician_id AND o.company_id=r.company_id
             WHERE r.company_id=?
             ORDER BY r.invited_at DESC,r.id DESC"
        );
        $stmt->execute([$companyId]);
        $orders = $stmt->fetchAll();
        if (!$orders) return [];

        $orderIds = array_values(array_unique(array_map(static fn(array $order): int => (int)$order['id'], $orders)));
        $quoteIds = array_values(array_filter(array_map(static fn(array $order): int => (int)($order['current_quote_id'] ?? 0), $orders)));
        $services = [];
        $quotes = $quoteHistory = [];

        $orderPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
        $serviceStmt = db()->prepare("SELECT * FROM order_services WHERE order_id IN ($orderPlaceholders) ORDER BY order_id,id");
        $serviceStmt->execute($orderIds);
        foreach ($serviceStmt->fetchAll() as $row) $services[(int)$row['order_id']][] = $row;

        if ($quoteIds) {
            $quotePlaceholders = implode(',', array_fill(0, count($quoteIds), '?'));
            $quoteStmt = db()->prepare("SELECT * FROM quotes WHERE id IN ($quotePlaceholders)");
            $quoteStmt->execute($quoteIds);
            foreach ($quoteStmt->fetchAll() as $row) $quotes[(int)$row['id']] = $row;
        }
        $historyParams = array_merge([$companyId], $orderIds);
        $historyStmt = db()->prepare("SELECT q.* FROM quotes q WHERE q.company_id=? AND q.order_id IN ($orderPlaceholders) ORDER BY q.order_id,q.revision DESC,q.id DESC");
        $historyStmt->execute($historyParams);
        foreach ($historyStmt->fetchAll() as $row) $quoteHistory[(int)$row['order_id']][] = $row;

        foreach ($orders as &$order) {
            $order['contact_visible'] = (int)$order['company_id'] === $companyId && $order['request_status'] === 'selected';
            $activeImageRequest = $order['company_id'] === null
                && !OrderState::isTerminal((string)$order['status'])
                && in_array((string)$order['request_status'], ['invited','viewed','quote_submitted'], true);
            $order['image_visible'] = !empty($order['image_name'])
                && ((int)($order['company_id'] ?? 0) === $companyId || $activeImageRequest);
            $order['repair_report_visible'] = (int)$order['company_id'] === $companyId && !empty($order['repair_report_id']);
            if (!$order['image_visible']) {
                $order['image_name'] = null;
            }
            if (!$order['contact_visible']) {
                $order['customer_name'] = null;
                $order['customer_phone'] = null;
                $order['customer_email'] = null;
                $order['address'] = null;
                $order['technician_name'] = null;
                $order['technician_id'] = null;
                $order['final_price'] = null;
                $order['completed_at'] = null;
                $order['selected_quote_id'] = null;
            }
            if (!$order['repair_report_visible']) {
                $order['repair_report_technician_name'] = null;
                $order['repair_report_id'] = null;
                $order['actual_issue'] = null;
                $order['resolution'] = null;
                $order['post_repair_advice'] = null;
                $order['repair_report_submitted_at'] = null;
                $order['repair_report_updated_at'] = null;
            }
            $quoteId = (int)($order['current_quote_id'] ?? 0);
            $order['latest_quote'] = $quoteId > 0 ? ($quotes[$quoteId] ?? null) : null;
            $order['quote_history'] = $quoteHistory[(int)$order['id']] ?? [];
            $order['services'] = $services[(int)$order['id']] ?? [];
        }
        unset($order);
        return $orders;
    }


    public static function quote(): void
    {
        $u = require_role('company');
        $company = self::companyFor($u);
        verify_csrf();
        try {
            MarketplaceService::submitQuote(
                post_int('order_id'),
                (int)$company['id'],
                (int)$u['id'],
                max(0,post_int('quote_min')),
                max(0,post_int('quote_max')),
                post_string('quote_note',3000),
                post_string('estimated_arrival',120)
            );
            flash('success','Đã gửi báo giá.');
        } catch (Throwable $e) {
            flash('error',$e->getMessage());
        }
        redirect('company/orders');
    }

    public static function decline(): void
    {
        $u = require_role('company');
        $company = self::companyFor($u);
        verify_csrf();
        try {
            MarketplaceService::declineRequest(post_int('order_id'), (int)$company['id'], (int)$u['id'], post_string('reason',1000));
            flash('info','Đã từ chối cơ hội. Đơn toàn cục vẫn tiếp tục với doanh nghiệp khác.');
        } catch (Throwable $e) {
            flash('error',$e->getMessage());
        }
        redirect('company/orders');
    }

    public static function assignTechnician(): void
    {
        $u = require_role('company');
        $company = self::companyFor($u);
        verify_csrf();
        try {
            OrderService::assignTechnician(post_int('order_id'), (int)$company['id'], post_int('technician_id'), (int)$u['id']);
            flash('success','Đã phân công kỹ thuật viên.');
        } catch (Throwable $e) {
            flash('error',$e->getMessage());
        }
        redirect('company/orders');
    }

    public static function complete(): void
    {
        $u = require_role('company');
        $company = self::companyFor($u);
        verify_csrf();
        try {
            OrderService::completeOrder(post_int('order_id'), (int)$company['id'], (int)$u['id'], max(0,post_int('final_price')), post_string('complete_note',2000));
            flash('success','Đã xác nhận hoàn thành đơn.');
        } catch (Throwable $e) {
            flash('error',$e->getMessage());
        }
        redirect('company/orders');
    }

    public static function technicians(): void
    {
        $u = require_role('company');
        $company = self::companyFor($u);
        $status = (string)($_GET['status'] ?? 'all');
        if (!in_array($status, ['all','active','inactive'], true)) $status = 'all';
        $statusSql = $status === 'all' ? '' : ' AND status=?';
        $stmt = db()->prepare("SELECT * FROM users WHERE company_id=? AND role='technician'$statusSql ORDER BY created_at DESC,id DESC");
        $params = [$company['id']];
        if ($status !== 'all') $params[] = $status;
        $stmt->execute($params);
        $technicians = $stmt->fetchAll();
        $capabilities = TechnicianService::capabilitiesForTechnicians(array_column($technicians,'id'));
        foreach ($technicians as &$technician) {
            $technician['capabilities'] = $capabilities[(int)$technician['id']] ?? [];
            $technician['capability_ids'] = array_map(static fn(array $row): int => (int)$row['service_id'],$technician['capabilities']);
        }
        unset($technician);
        $capabilityCatalog = TechnicianService::capabilityCatalog();
        render('company/technicians', compact('company','technicians','status','capabilityCatalog'), 'Kỹ thuật viên');
    }

    public static function createTechnician(): void
    {
        $u = require_role('company');
        $company = self::companyFor($u);
        verify_csrf();
        $name = post_string('name',150);
        $email = strtolower(post_string('email',180));
        $phone = post_phone_input();
        $skill = post_string('skill_note',255);
        $capabilityIds = is_array($_POST['capability_ids'] ?? null) ? $_POST['capability_ids'] : [];
        try {
            $created = TechnicianService::createTechnician((int)$u['id'],$name,$email,$phone,$skill,$capabilityIds);
            flash('success','Đã tạo kỹ thuật viên. Thông tin đăng nhập chỉ hiển thị lần này: ' . $created['email'] . ' / ' . $created['password']);
        } catch (Throwable $e) {
            $message = $e instanceof PDOException
                ? ($e->getCode()==='23000' ? 'Email đã tồn tại.' : 'Không thể tạo kỹ thuật viên lúc này.')
                : $e->getMessage();
            flash('error',$message);
        }
        redirect('company/technicians');
    }

    public static function updateTechnicianCapabilities(): void
    {
        $u = require_role('company');
        self::companyFor($u);
        verify_csrf();
        $capabilityIds = is_array($_POST['capability_ids'] ?? null) ? $_POST['capability_ids'] : [];
        try {
            $changed = TechnicianService::updateCapabilities(post_int('technician_id'),(int)$u['id'],post_string('skill_note',255),$capabilityIds);
            flash($changed ? 'success' : 'info',$changed ? 'Đã cập nhật năng lực kỹ thuật viên.' : 'Năng lực kỹ thuật viên không thay đổi.');
        } catch (Throwable $e) {
            flash('error',$e instanceof PDOException ? 'Không thể cập nhật năng lực kỹ thuật viên lúc này.' : $e->getMessage());
        }
        redirect('company/technicians');
    }

    public static function changeTechnicianStatus(): void
    {
        $u = require_role('company');
        self::companyFor($u);
        verify_csrf();
        try {
            TechnicianService::changeStatus(post_int('technician_id'), (int)$u['id'], post_string('status',30));
            flash('success', post_string('status',30) === 'active' ? 'Đã kích hoạt lại kỹ thuật viên.' : 'Đã ngừng hoạt động kỹ thuật viên. Lịch sử công việc được giữ nguyên.');
        } catch (Throwable $e) {
            flash('error',$e->getMessage());
        }
        redirect('company/technicians');
    }

    public static function revenue(): void
    {
        $u = require_role('company');
        $company = self::companyFor($u);
        $summary = db()->prepare("SELECT COUNT(*) order_count,COALESCE(SUM(final_price),0) total,COALESCE(AVG(final_price),0) avg_value FROM orders WHERE company_id=? AND status='completed'");
        $summary->execute([$company['id']]);
        $row = $summary->fetch();
        $total = (int)$row['total'];
        $avg = (int)$row['avg_value'];
        $pagination = pagination((int)$row['order_count'], 30);
        $stmt = db()->prepare("SELECT * FROM orders WHERE company_id=? AND status='completed' ORDER BY completed_at DESC,id DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, (int)$company['id'], PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(3, (int)$pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll();
        render('company/revenue', compact('company','orders','total','avg','pagination'), 'Doanh thu');
    }
}
