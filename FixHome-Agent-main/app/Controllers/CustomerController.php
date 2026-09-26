<?php
declare(strict_types=1);

final class CustomerController
{
    public static function dashboard(): void
    {
        $u = require_role('customer');
        $stmt = db()->prepare("SELECT COUNT(*) total,SUM(status='completed') completed,SUM(status IN ('quote_accepted','tech_assigned','tech_accepted','on_the_way','inspecting','repairing')) active,SUM(status='quoted') action_required,SUM(status='work_done') awaiting_confirmation FROM orders WHERE customer_id=?");
        $stmt->execute([$u['id']]);
        $stats = $stmt->fetch();
        $stmt = db()->prepare("SELECT o.*,c.name company_name,sc.name category_name FROM orders o LEFT JOIN companies c ON c.id=o.company_id JOIN service_categories sc ON sc.id=o.category_id WHERE o.customer_id=? ORDER BY o.created_at DESC,o.id DESC LIMIT 5");
        $stmt->execute([$u['id']]);
        $recent = $stmt->fetchAll();
        render('customer/dashboard', compact('u','stats','recent'), 'Khách hàng');
    }

    private static function bookingCatalog(): array
    {
        $categories = db()->query('SELECT * FROM service_categories ORDER BY id')->fetchAll();
        $services = db()->query('SELECT s.*,c.name category_name,c.icon FROM services s JOIN service_categories c ON c.id=s.category_id WHERE s.active=1 ORDER BY c.id,s.id')->fetchAll();
        ServiceCatalog::attachCommonIssues(db(), $services);
        return [$categories,$services];
    }

    public static function bookingForm(): void
    {
        $user = require_role('customer');
        [$categories,$services] = self::bookingCatalog();
        $diagnosisPreview = null;
        $savedAddresses = CustomerAddressService::listForCustomer((int)$user['id']);
        $defaultAddress = null;
        foreach ($savedAddresses as $savedAddress) {
            if ((int)$savedAddress['is_default'] === 1) {
                $defaultAddress = $savedAddress;
                break;
            }
        }
        $formValues = [
            'name' => (string)$user['name'],
            'phone' => (string)($user['phone'] ?? ''),
            'email' => (string)$user['email'],
            'address' => (string)($defaultAddress['address'] ?? ''),
        ];
        render('customer/book', compact('categories','services','diagnosisPreview','formValues','savedAddresses'), 'Đặt dịch vụ');
    }

    public static function previewDiagnosis(): void
    {
        $user = require_role('customer');
        verify_csrf();
        $description = post_string('description',5000);
        if ($description === '') {
            flash('error','Vui lòng mô tả hiện tượng để phân loại sơ bộ.');
            redirect('customer/book');
        }
        [$categories,$services] = self::bookingCatalog();
        $savedAddresses = CustomerAddressService::listForCustomer((int)$user['id']);
        $diagnosisPreview = DiagnosisService::analyze($description, false);
        $formValues = [
            'description' => $description,
            'name' => self::textInput('name'),
            'phone' => post_phone_input(),
            'email' => self::textInput('email'),
            'address' => self::textInput('address'),
            'scheduled_date' => post_string('scheduled_date',20) ?: date('Y-m-d',strtotime('+1 day')),
            'scheduled_time' => post_string('scheduled_time',10) ?: '09:00',
        ];
        render('customer/book', compact('categories','services','diagnosisPreview','formValues','savedAddresses'), 'Phân tích sơ bộ');
    }

    public static function createOrder(): void
    {
        $u = require_role('customer');
        verify_csrf();
        $mode = post_string('mode',80);
        $name = self::textInput('name');
        $phone = post_phone_input();
        $email = self::textInput('email');
        $address = self::textInput('address');
        $description = post_string('description',5000);
        $date = post_string('scheduled_date',20);
        $time = post_string('scheduled_time',10);
        $serviceIds = array_map('intval', $_POST['service_ids'] ?? []);
        $maxOrdersPerHour = max(1, min(20, (int)config('security.customer_order_max_per_hour', 5)));
        $rate = db()->prepare("SELECT COUNT(*) FROM orders WHERE customer_id=? AND created_at>=DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $rate->execute([$u['id']]);
        if ((int)$rate->fetchColumn() >= $maxOrdersPerHour) {
            flash('error','Bạn đã tạo nhiều yêu cầu trong thời gian ngắn. Vui lòng thử lại sau.');
            redirect('customer/book');
        }
        if (!$date || !$time) {
            flash('error','Vui lòng nhập lịch hẹn.');
            redirect('customer/book');
        }
        $scheduled = strtotime($date . ' ' . $time);
        if (!$scheduled || $scheduled < time() - 3600) {
            flash('error','Lịch hẹn không hợp lệ.');
            redirect('customer/book');
        }

        $image = null;
        $diagnosis = [];
        try {
            if ($mode === 'unknown') {
                if (!$description) {
                    throw new RuntimeException('Vui lòng mô tả hiện tượng khi chưa rõ lỗi.');
                }
                $image = UploadService::store($_FILES['image'] ?? null);
                $diagnosis = DiagnosisService::analyze($description, $image !== null);
                $categoryId = OrderService::categoryIdByName((string)$diagnosis['category'])
                    ?? OrderService::categoryIdByName('Không rõ lỗi');
                $stmt = db()->prepare("SELECT id FROM services WHERE code='unknown_diagnosis' LIMIT 1");
                $stmt->execute();
                $serviceIds = [(int)$stmt->fetchColumn()];
            } else {
                $categoryId = null;
            }
            $estimate = OrderService::estimate($serviceIds);
            if (!$estimate['services']) {
                throw new RuntimeException('Vui lòng chọn ít nhất một dịch vụ.');
            }
            $categoryId = $categoryId ?? (int)$estimate['services'][0]['category_id'];
            OrderService::createOrder((int)$u['id'], [
                'category_id' => $categoryId,
                'mode' => $mode === 'unknown' ? 'unknown_diagnosis' : 'catalog_selection',
                'description' => $description,
                'diagnosis_summary' => $diagnosis['summary'] ?? 'Khách hàng chọn dịch vụ cụ thể.',
                'diagnosis_device' => $diagnosis['device_type'] ?? null,
                'diagnosis_issue_group' => $diagnosis['issue_group'] ?? null,
                'diagnosis_risk' => $diagnosis['risk_level'] ?? null,
                'image_name' => $image,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
                'scheduled_at' => date('Y-m-d H:i:s', $scheduled),
            ], $estimate);
            flash('success','Đã tạo yêu cầu và mời các doanh nghiệp phù hợp báo giá.');
        } catch (Throwable $e) {
            if ($image !== null) {
                UploadService::deleteStored($image);
            }
            flash('error','Không thể tạo đơn: ' . $e->getMessage());
            redirect('customer/book');
        }
        redirect('customer/orders');
    }

    private static function textInput(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    public static function orders(): void
    {
        $u = require_role('customer');
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'status' => (string)($_GET['status'] ?? 'all'),
            'category' => max(0, (int)($_GET['category'] ?? 0)),
            'period' => (string)($_GET['period'] ?? 'all'),
        ];
        if (!in_array($filters['status'], ['all','waiting','action','active','work_done','completed','cancelled'], true)) $filters['status'] = 'all';
        if (!in_array($filters['period'], ['all','today','7','30'], true)) $filters['period'] = 'all';
        $where = ['o.customer_id=?'];
        $params = [(int)$u['id']];
        if ($filters['category'] > 0) { $where[] = 'o.category_id=?'; $params[] = $filters['category']; }
        if ($filters['status'] === 'waiting') $where[] = "o.status IN ('pending_distribution','waiting_quote')";
        elseif ($filters['status'] === 'action') $where[] = "o.status='quoted'";
        elseif ($filters['status'] === 'active') $where[] = "o.status IN ('quote_accepted','tech_assigned','tech_accepted','on_the_way','inspecting','repairing')";
        elseif ($filters['status'] === 'work_done') $where[] = "o.status='work_done'";
        elseif ($filters['status'] === 'completed') $where[] = "o.status='completed'";
        elseif ($filters['status'] === 'cancelled') $where[] = "o.status='cancelled'";
        if ($filters['period'] === 'today') $where[] = 'DATE(o.created_at)=CURDATE()';
        elseif ($filters['period'] === '7') $where[] = 'o.created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)';
        elseif ($filters['period'] === '30') $where[] = 'o.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)';
        if ($filters['q'] !== '') {
            $where[] = "(o.order_code LIKE ? OR sc.name LIKE ? OR COALESCE(c.name,'') LIKE ? OR EXISTS (SELECT 1 FROM order_services os WHERE os.order_id=o.id AND os.name_snapshot LIKE ?))";
            $term = '%' . $filters['q'] . '%';
            array_push($params,$term,$term,$term,$term);
        }
        $whereSql = implode(' AND ', $where);
        $count = db()->prepare("SELECT COUNT(*) FROM orders o JOIN service_categories sc ON sc.id=o.category_id LEFT JOIN companies c ON c.id=o.company_id WHERE $whereSql");
        $count->execute($params);
        $pagination = pagination((int)$count->fetchColumn(), 20);
        $stmt = db()->prepare("SELECT o.*,sc.name category_name,c.name company_name,t.name technician_name,rr.id repair_report_id,rr.actual_issue,rr.resolution,rr.post_repair_advice FROM orders o JOIN service_categories sc ON sc.id=o.category_id LEFT JOIN companies c ON c.id=o.company_id LEFT JOIN users t ON t.id=o.technician_id AND t.role='technician' AND t.company_id=o.company_id LEFT JOIN repair_reports rr ON rr.order_id=o.id AND rr.technician_id=o.technician_id AND o.status='completed' WHERE $whereSql ORDER BY o.created_at DESC,o.id DESC LIMIT ? OFFSET ?");
        $index = 1;
        foreach ($params as $param) $stmt->bindValue($index++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->bindValue($index++, (int)$pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue($index, (int)$pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll();
        self::decorateOrders($orders);
        $categories = db()->query('SELECT id,name FROM service_categories WHERE active=1 ORDER BY id')->fetchAll();
        render('customer/orders', compact('orders','pagination','filters','categories'), 'Theo dõi đơn');
    }

    private static function decorateOrders(array &$orders): void
    {
        if (!$orders) return;
        $ids = array_map(static fn(array $order): int => (int)$order['id'], $orders);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $quotes = $timeline = $services = $companyReviews = $technicianReviews = [];
        $quoteCompanyIds = [];

        $stmt = db()->prepare("SELECT r.order_id,q.*,c.name company_name,(q.id=r.current_quote_id) is_current FROM order_company_requests r JOIN quotes q ON q.order_company_request_id=r.id JOIN companies c ON c.id=q.company_id WHERE r.order_id IN ($placeholders) ORDER BY r.order_id,c.name,q.revision DESC,q.id DESC");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) {
            $quotes[(int)$row['order_id']][] = $row;
            $quoteCompanyIds[] = (int)$row['company_id'];
        }
        $companyReputations = FeedbackService::companyReputations($quoteCompanyIds);
        foreach ($quotes as &$orderQuotes) {
            foreach ($orderQuotes as &$quote) {
                $quote['company_reputation'] = $companyReputations[(int)$quote['company_id']] ?? ['average_rating'=>null,'review_count'=>0];
            }
            unset($quote);
        }
        unset($orderQuotes);

        $stmt = db()->prepare("SELECT * FROM order_timeline WHERE order_id IN ($placeholders) ORDER BY order_id,created_at,id");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) $timeline[(int)$row['order_id']][] = $row;

        $stmt = db()->prepare("SELECT * FROM order_services WHERE order_id IN ($placeholders) ORDER BY order_id,id");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) $services[(int)$row['order_id']][] = $row;

        $stmt = db()->prepare("SELECT * FROM reviews WHERE order_id IN ($placeholders)");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) $companyReviews[(int)$row['order_id']] = $row;

        $stmt = db()->prepare("SELECT * FROM technician_reviews WHERE order_id IN ($placeholders)");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) $technicianReviews[(int)$row['order_id']] = $row;

        $technicianReputations = FeedbackService::technicianReputations(array_column($orders, 'technician_id'));

        foreach ($orders as &$order) {
            $id = (int)$order['id'];
            $order['quotes'] = $quotes[$id] ?? [];
            $order['timeline'] = $timeline[$id] ?? [];
            $order['services'] = $services[$id] ?? [];
            $order['company_review'] = $companyReviews[$id] ?? null;
            $order['technician_review'] = $technicianReviews[$id] ?? null;
            $technicianId = (int)($order['technician_id'] ?? 0);
            $order['technician_reputation'] = $technicianReputations[$technicianId] ?? FeedbackService::emptyTechnicianReputation();
        }
        unset($order);
    }

    public static function quoteAction(): void
    {
        $u = require_role('customer');
        verify_csrf();
        try {
            $action = post_string('action',20);
            if ($action === 'accept') {
                MarketplaceService::selectQuote(post_int('order_id'), post_int('quote_id'), (int)$u['id']);
                flash('success','Đã chọn báo giá.');
            } elseif ($action === 'changes') {
                MarketplaceService::requestQuoteChanges(post_int('order_id'), post_int('quote_id'), (int)$u['id']);
                flash('info','Đã yêu cầu doanh nghiệp điều chỉnh báo giá.');
            } else {
                throw new RuntimeException('Thao tác báo giá không hợp lệ.');
            }
        } catch (Throwable $e) {
            flash('error',$e->getMessage());
        }
        redirect('customer/orders');
    }

    public static function cancel(): void
    {
        $u = require_role('customer');
        verify_csrf();
        try {
            OrderService::cancelOrder(post_int('order_id'), (int)$u['id'], 'customer', post_string('cancel_reason',1000));
            flash('success','Đã hủy đơn.');
        } catch (Throwable $e) {
            flash('error',$e->getMessage());
        }
        redirect('customer/orders');
    }

    public static function feedback(): void
    {
        $u = require_role('customer');
        verify_csrf();
        $orderId = post_int('order_id');
        $kind = post_string('kind',20);
        if ($kind === 'review') {
            try {
                $result = FeedbackService::submitOrderFeedback(
                    $orderId,
                    (int)$u['id'],
                    $_POST['company_rating'] ?? null,
                    post_string('company_comment',2001),
                    $_POST['technician_rating'] ?? null,
                    post_string('technician_comment',2001)
                );
                if ($result['created']) {
                    flash('success','Cảm ơn bạn đã gửi đánh giá.');
                } else {
                    flash('info','Đánh giá này đã được ghi nhận trước đó.');
                }
            } catch (Throwable $e) {
                flash('error',$e->getMessage());
            }
            redirect('customer/orders');
        }

        $stmt = db()->prepare('SELECT * FROM orders WHERE id=? AND customer_id=? LIMIT 1');
        $stmt->execute([$orderId,$u['id']]);
        $order = $stmt->fetch();
        if (!$order) {
            flash('error','Không tìm thấy đơn.');
            redirect('customer/orders');
        }
        if ($kind === 'complaint') {
            $subject = post_string('subject',180);
            $detail = post_string('detail',3000);
            if (!$subject || !$detail) {
                flash('error','Vui lòng nhập tiêu đề và nội dung khiếu nại.');
                redirect('customer/orders');
            }
            $stmt = db()->prepare('INSERT INTO complaints(order_id,customer_id,subject,detail) VALUES(?,?,?,?)');
            $stmt->execute([$orderId,$u['id'],$subject,$detail]);
            notify_role('admin','Khiếu nại mới','Khách hàng gửi khiếu nại cho đơn ' . $order['order_code'],'complaint_created',$orderId);
            flash('success','Đã gửi khiếu nại đến FixHome.');
        } else {
            flash('error','Thao tác phản hồi không hợp lệ.');
        }
        redirect('customer/orders');
    }
}
