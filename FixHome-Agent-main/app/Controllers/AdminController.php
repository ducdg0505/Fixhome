<?php
final class AdminController
{
    public static function dashboard(): void
    {
        require_role('admin');
        $stats = db()->query(
            "SELECT
                (SELECT COUNT(*) FROM users WHERE role='customer') customers,
                (SELECT COUNT(*) FROM companies WHERE legal_status='verified' AND account_status='active') companies,
                (SELECT COUNT(*) FROM orders) orders,
                (SELECT COUNT(*) FROM partner_applications WHERE status='pending_review') pending_partners,
                (SELECT COUNT(*) FROM orders WHERE status='pending_distribution') pending_orders,
                (SELECT COUNT(*) FROM complaints WHERE status<>'closed') complaints"
        )->fetch();
        $recent = db()->query("SELECT o.*,c.name company_name FROM orders o LEFT JOIN companies c ON c.id=o.company_id ORDER BY o.created_at DESC,o.id DESC LIMIT 8")->fetchAll();
        render('admin/dashboard', compact('stats','recent'), 'Quản trị FixHome');
    }

    public static function accounts(): void
    {
        $admin = require_role('admin');
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'kind' => (string)($_GET['kind'] ?? 'all'),
            'status' => (string)($_GET['status'] ?? 'all'),
        ];
        if (!in_array($filters['kind'], ['all','test','real'], true)) $filters['kind'] = 'all';
        if (!in_array($filters['status'], ['all','active','suspended','banned','deleted'], true)) $filters['status'] = 'all';
        if (function_exists('mb_substr')) $filters['q'] = mb_substr($filters['q'], 0, 180, 'UTF-8');
        else $filters['q'] = substr($filters['q'], 0, 180);

        $where = ["u.role='customer'"];
        $params = [];
        if ($filters['kind'] !== 'all') {
            $where[] = 'u.is_test=?';
            $params[] = $filters['kind'] === 'test' ? 1 : 0;
        }
        if ($filters['status'] !== 'all') {
            $where[] = 'u.status=?';
            $params[] = $filters['status'];
        }
        if ($filters['q'] !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ? OR COALESCE(u.phone,\'\') LIKE ?)';
            $term = '%' . $filters['q'] . '%';
            array_push($params, $term, $term, $term);
        }
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $count = db()->prepare('SELECT COUNT(*) FROM users u' . $whereSql);
        $count->execute($params);
        $pagination = pagination((int)$count->fetchColumn(), 25);
        $stmt = db()->prepare(
            'SELECT u.id,u.name,u.email,u.phone,u.status,u.is_test,u.created_at,'
            . '(SELECT COUNT(*) FROM orders o WHERE o.customer_id=u.id) order_count '
            . 'FROM users u' . $whereSql . ' ORDER BY u.created_at DESC,u.id DESC LIMIT ? OFFSET ?'
        );
        $index = 1;
        foreach ($params as $param) $stmt->bindValue($index++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->bindValue($index++, (int)$pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue($index, (int)$pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $customers = $stmt->fetchAll();

        $purgePreview = null;
        $previewTarget = max(0, (int)($_GET['preview'] ?? 0));
        if ($previewTarget > 0) {
            try {
                $purgePreview = AccountMaintenanceService::previewTestCustomerPurge((int)$admin['id'], $previewTarget);
                $target = db()->prepare("SELECT id,name,email FROM users WHERE id=? AND role='customer' LIMIT 1");
                $target->execute([$previewTarget]);
                $purgePreview['target'] = $target->fetch() ?: ['id'=>$previewTarget,'name'=>'#' . $previewTarget,'email'=>''];
            } catch (Throwable $e) {
                flash('error',$e->getMessage());
            }
        }
        render('admin/accounts', compact('customers','pagination','filters','purgePreview'), 'Quản lý tài khoản khách hàng');
    }

    public static function changeCustomerTestMarker(): void
    {
        $admin = require_role('admin');
        verify_csrf();
        $marker = post_string('marker', 20);
        try {
            if (!in_array($marker, ['test','real'], true)) throw new RuntimeException('Dấu tài khoản không hợp lệ.');
            AccountMaintenanceService::setTestCustomerMarker(
                (int)$admin['id'],
                post_int('target_user_id'),
                $marker === 'test',
                self::postedCurrentPassword()
            );
            flash('success',$marker === 'test' ? 'Đã đánh dấu tài khoản thử nghiệm.' : 'Đã gỡ dấu tài khoản thử nghiệm.');
        } catch (Throwable $e) {
            flash('error',$e instanceof PDOException ? 'Không thể cập nhật dấu tài khoản lúc này.' : $e->getMessage());
        }
        redirect('admin/accounts');
    }

    public static function changeCustomerStatus(): void
    {
        $admin = require_role('admin');
        verify_csrf();
        try {
            AccountMaintenanceService::changeRealCustomerStatus(
                (int)$admin['id'],
                post_int('target_user_id'),
                post_string('status', 30),
                post_string('reason', AccountMaintenanceService::STATUS_REASON_MAX_LENGTH + 1),
                self::postedCurrentPassword()
            );
            flash('success','Đã cập nhật trạng thái khách hàng.');
        } catch (Throwable $e) {
            flash('error',$e instanceof PDOException ? 'Không thể cập nhật trạng thái lúc này.' : $e->getMessage());
        }
        redirect('admin/accounts');
    }

    public static function purgeTestCustomer(): void
    {
        $admin = require_role('admin');
        verify_csrf();
        try {
            $result = AccountMaintenanceService::purgeTestCustomer(
                (int)$admin['id'],
                post_int('target_user_id'),
                self::postedCurrentPassword()
            );
            $failures = $result['file_cleanup']['failures_remaining'] ?? [];
            flash($failures ? 'warning' : 'success',$failures
                ? 'Đã xóa dữ liệu tài khoản thử, nhưng một số tệp ảnh cần được dọn dẹp thủ công.'
                : 'Đã xóa hoàn toàn tài khoản thử nghiệm.');
        } catch (Throwable $e) {
            flash('error',$e instanceof PDOException ? 'Không thể xóa tài khoản thử lúc này.' : $e->getMessage());
        }
        redirect('admin/accounts');
    }

    private static function postedCurrentPassword(): string
    {
        $password = $_POST['current_password'] ?? '';
        return is_string($password) ? $password : '';
    }

    public static function partners(): void
    {
        require_role('admin');
        $status = (string)($_GET['status'] ?? 'all');
        if (!in_array($status, ['all','pending_review','approved','rejected'], true)) $status = 'all';
        $where = $status === 'all' ? '' : ' WHERE status=?';
        $params = $status === 'all' ? [] : [$status];
        $count = db()->prepare('SELECT COUNT(*) FROM partner_applications' . $where);
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pagination = pagination($total, 30);
        $stmt = db()->prepare('SELECT a.* FROM partner_applications a' . $where . ' ORDER BY a.submitted_at DESC,a.id DESC LIMIT ? OFFSET ?');
        $index = 1;
        foreach ($params as $param) $stmt->bindValue($index++, $param, PDO::PARAM_STR);
        $stmt->bindValue($index++, (int)$pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue($index, (int)$pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $apps = $stmt->fetchAll();
        $categories = [];
        if ($apps) {
            $ids = array_map(static fn(array $app): int => (int)$app['id'], $apps);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = db()->prepare("SELECT x.application_id,c.* FROM partner_application_categories x JOIN service_categories c ON c.id=x.category_id WHERE x.application_id IN ($placeholders) ORDER BY x.application_id,c.id");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $row) $categories[(int)$row['application_id']][] = $row;
        }
        foreach ($apps as &$app) $app['categories'] = $categories[(int)$app['id']] ?? [];
        unset($app);
        render('admin/partners', compact('apps','pagination','status'), 'Duyệt đối tác');
    }

    public static function reviewPartner(): void
    {
        $u = require_role('admin');
        verify_csrf();
        $id = post_int('application_id');
        $action = post_string('action',20);
        $note = post_string('review_note',3000);
        $stmt = db()->prepare("SELECT * FROM partner_applications WHERE id=? AND status='pending_review' FOR UPDATE");
        try {
            db()->beginTransaction();
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            if (!$app) throw new RuntimeException('Hồ sơ không còn ở trạng thái chờ duyệt.');
            if ($action === 'reject') {
                $q = db()->prepare("UPDATE partner_applications SET status='rejected',review_note=?,reviewed_by_user_id=?,reviewed_at=NOW() WHERE id=?");
                $q->execute([$note ?: 'Hồ sơ chưa đáp ứng yêu cầu xác minh.',$u['id'],$id]);
                db()->commit();
                flash('info','Đã từ chối hồ sơ.');
                redirect('admin/partners?status=pending_review');
            }
            if ($action !== 'approve') throw new RuntimeException('Thao tác duyệt không hợp lệ.');
            $phone = PhonePolicy::normalize((string)$app['phone']);
            if ($phone === null) {
                throw new RuntimeException('Số điện thoại đối tác không hợp lệ. Vui lòng chỉnh hồ sơ trước khi phê duyệt.');
            }
            $dup = db()->prepare('SELECT COUNT(*) FROM companies WHERE tax_code=?');
            $dup->execute([$app['tax_code']]);
            if ((int)$dup->fetchColumn() > 0) throw new RuntimeException('Mã số thuế này đã tồn tại trong danh sách doanh nghiệp.');
            $raw = random_password();
            $q = db()->prepare("INSERT INTO companies(name,tax_code,representative,phone,email,address,legal_status,account_status,rating,source_application_id,approved_at) VALUES(?,?,?,?,?,?,'verified','active',0,?,NOW())");
            $q->execute([$app['company_name'],$app['tax_code'],$app['representative'],$phone,$app['email'],$app['address'],$id]);
            $companyId = (int)db()->lastInsertId();
            $email = strtolower($app['email']);
            $exists = db()->prepare('SELECT COUNT(*) FROM users WHERE email=?');
            $exists->execute([$email]);
            if ((int)$exists->fetchColumn() > 0) {
                $parts = explode('@',$email,2);
                $email = $parts[0] . '+fixhome-' . substr(bin2hex(random_bytes(2)),0,4) . '@' . ($parts[1] ?? 'example.com');
            }
            $q = db()->prepare("INSERT INTO users(role,name,email,phone,password_hash,status,company_id,must_change_password) VALUES('company',?,?,?,?,'active',?,1)");
            $q->execute([$app['company_name'],$email,$phone,password_hash($raw,PASSWORD_DEFAULT),$companyId]);
            $managerId = (int)db()->lastInsertId();
            ProfileService::initializeUserVersion(db(),$managerId,(int)$u['id']);
            ProfileService::initializeCompanyVersion(db(),$companyId,(int)$u['id']);
            $q = db()->prepare('UPDATE companies SET manager_user_id=? WHERE id=?');
            $q->execute([$managerId,$companyId]);
            $cats = db()->prepare('SELECT category_id FROM partner_application_categories WHERE application_id=?');
            $cats->execute([$id]);
            $ins = db()->prepare('INSERT INTO company_service_categories(company_id,category_id) VALUES(?,?)');
            foreach ($cats->fetchAll() as $category) $ins->execute([$companyId,$category['category_id']]);
            $q = db()->prepare("UPDATE partner_applications SET status='approved',review_note=?,reviewed_by_user_id=?,reviewed_at=NOW() WHERE id=?");
            $q->execute([$note,$u['id'],$id]);
            db()->commit();
            audit('approve_partner','company',$companyId,'Duyệt hồ sơ đối tác #' . $id);
            flash('success','Đã duyệt và tạo tài khoản. Thông tin chỉ hiển thị lần này: ' . $email . ' / ' . $raw);
            redirect('admin/partners?status=pending_review');
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            flash('error',$e instanceof PDOException ? 'Không thể duyệt hồ sơ do lỗi dữ liệu.' : $e->getMessage());
            redirect('admin/partners?status=pending_review');
        }
    }

    public static function orders(): void
    {
        require_role('admin');
        $filters = ['q'=>trim((string)($_GET['q']??'')),'status'=>(string)($_GET['status']??'all'),'category'=>max(0,(int)($_GET['category']??0)),'group'=>RoleWorkView::normalizeAdminGroup((string)($_GET['group']??'all'))];
        $allowedStatuses = ['all',OrderState::PENDING_DISTRIBUTION,OrderState::WAITING_QUOTE,OrderState::QUOTED,OrderState::QUOTE_ACCEPTED,OrderState::TECH_ASSIGNED,OrderState::TECH_ACCEPTED,OrderState::ON_THE_WAY,OrderState::INSPECTING,OrderState::REPAIRING,OrderState::WORK_DONE,OrderState::COMPLETED,OrderState::CANCELLED];
        if(!in_array($filters['status'],$allowedStatuses,true)) $filters['status']='all';
        $where=[];$params=[];
        $groupStatuses = RoleWorkView::adminStatuses($filters['group']);
        if ($groupStatuses) {
            $where[] = 'o.status IN (' . implode(',', array_fill(0, count($groupStatuses), '?')) . ')';
            array_push($params, ...$groupStatuses);
        }
        if($filters['status']!=='all'){ $where[]='o.status=?';$params[]=$filters['status']; }
        if($filters['category']>0){ $where[]='o.category_id=?';$params[]=$filters['category']; }
        if($filters['q']!==''){ $where[]="(o.order_code LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR COALESCE(c.name,'') LIKE ?)";$term='%'.$filters['q'].'%';array_push($params,$term,$term,$term,$term); }
        $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
        $count=db()->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN companies c ON c.id=o.company_id$whereSql");$count->execute($params);$total=(int)$count->fetchColumn();
        $pagination = pagination($total, 30);
        $stmt = db()->prepare("SELECT o.*,sc.name category_name,c.name company_name FROM orders o JOIN service_categories sc ON sc.id=o.category_id LEFT JOIN companies c ON c.id=o.company_id$whereSql ORDER BY o.created_at DESC,o.id DESC LIMIT ? OFFSET ?");
        $index=1;foreach($params as $param)$stmt->bindValue($index++,$param,is_int($param)?PDO::PARAM_INT:PDO::PARAM_STR);
        $stmt->bindValue($index++, (int)$pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue($index, (int)$pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll();
        $candidates = [];
        if ($orders) {
            $categoryIds = array_values(array_unique(array_map(static fn(array $order): int => (int)$order['category_id'], $orders)));
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $stmt = db()->prepare("SELECT x.category_id,c.id,c.name,c.rating FROM company_service_categories x JOIN companies c ON c.id=x.company_id WHERE x.category_id IN ($placeholders) AND c.legal_status='verified' AND c.account_status='active' ORDER BY x.category_id,c.name,c.id");
            $stmt->execute($categoryIds);
            foreach ($stmt->fetchAll() as $row) $candidates[(int)$row['category_id']][] = $row;
        }
        foreach ($orders as &$order) $order['candidates'] = $candidates[(int)$order['category_id']] ?? [];
        unset($order);
        $categories=db()->query('SELECT id,name FROM service_categories WHERE active=1 ORDER BY id')->fetchAll();
        $groups = RoleWorkView::adminGroups();
        render('admin/orders', compact('orders','pagination','filters','categories','allowedStatuses','groups'), 'Phân phối đơn');
    }

    public static function assignOrder(): void
    {
        $u = require_role('admin');
        verify_csrf();
        $id = post_int('order_id');
        $mode = post_string('mode',20);
        $companyId = $mode === 'manual' ? post_int('company_id') : null;
        try {
            MarketplaceService::inviteMatchingCompanies($id,(int)$u['id'],'admin',$companyId ? [$companyId] : null);
            flash('success','Đã mời doanh nghiệp báo giá.');
        } catch (Throwable $e) {
            flash('error',$e->getMessage());
        }
        redirect('admin/orders');
    }

    public static function companies(): void
    {
        require_role('admin');
        $total = (int)db()->query('SELECT COUNT(*) FROM companies')->fetchColumn();
        $pagination = pagination($total, 30);
        $stmt = db()->prepare('SELECT c.* FROM companies c ORDER BY c.created_at DESC,c.id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, (int)$pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $companies = $stmt->fetchAll();
        $categories = $connections = $technicians = [];
        if ($companies) {
            $ids = array_map(static fn(array $company): int => (int)$company['id'], $companies);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $reputations = FeedbackService::companyReputations($ids);

            $stmt = db()->prepare("SELECT company_id,COUNT(*) connection_count FROM order_company_requests WHERE company_id IN ($placeholders) GROUP BY company_id");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $row) $connections[(int)$row['company_id']] = (int)$row['connection_count'];

            $stmt = db()->prepare("SELECT company_id,COUNT(*) tech_count FROM users WHERE company_id IN ($placeholders) AND role='technician' GROUP BY company_id");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $row) $technicians[(int)$row['company_id']] = (int)$row['tech_count'];

            $stmt = db()->prepare("SELECT x.company_id,sc.name FROM company_service_categories x JOIN service_categories sc ON sc.id=x.category_id WHERE x.company_id IN ($placeholders) ORDER BY x.company_id,sc.id");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $row) $categories[(int)$row['company_id']][] = $row['name'];
        }
        foreach ($companies as &$company) {
            $id = (int)$company['id'];
            $company['reputation'] = $reputations[$id] ?? ['average_rating'=>null,'review_count'=>0];
            $company['connection_count'] = $connections[$id] ?? 0;
            $company['tech_count'] = $technicians[$id] ?? 0;
            $company['categories'] = $categories[$id] ?? [];
        }
        unset($company);
        render('admin/companies', compact('companies','pagination'), 'Doanh nghiệp đối tác');
    }

    public static function complaints(): void
    {
        require_role('admin');
        $complaintView = (string)($_GET['view'] ?? 'all');
        if (!in_array($complaintView, ['all','unresolved','open','in_progress','responded','closed'], true)) $complaintView = 'all';
        $where = '';
        $params = [];
        if ($complaintView === 'unresolved') $where = " WHERE cp.status<>'closed'";
        elseif ($complaintView !== 'all') { $where = ' WHERE cp.status=?'; $params[] = $complaintView; }
        $count = db()->prepare('SELECT COUNT(*) FROM complaints cp' . $where);
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pagination = pagination($total, 30);
        $stmt = db()->prepare("SELECT cp.*,o.order_code,u.name customer_name FROM complaints cp JOIN orders o ON o.id=cp.order_id JOIN users u ON u.id=cp.customer_id$where ORDER BY cp.created_at DESC,cp.id DESC LIMIT ? OFFSET ?");
        $index = 1;
        foreach ($params as $param) $stmt->bindValue($index++, $param, PDO::PARAM_STR);
        $stmt->bindValue($index++, (int)$pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue($index, (int)$pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $complaints = $stmt->fetchAll();
        render('admin/complaints', compact('complaints','pagination','complaintView'), 'Khiếu nại');
    }

    public static function updateComplaint(): void
    {
        $u = require_role('admin');
        verify_csrf();
        $id = post_int('complaint_id');
        $status = post_string('status',50);
        $note = post_string('admin_note',3000);
        $allowed = ['open','in_progress','responded','closed'];
        if (!in_array($status,$allowed,true)) {
            flash('error','Trạng thái không hợp lệ.');
            redirect('admin/complaints');
        }
        $stmt = db()->prepare("UPDATE complaints SET status=?,admin_note=?,handled_by_user_id=?,resolved_at=CASE WHEN ?='closed' THEN NOW() ELSE NULL END WHERE id=?");
        $stmt->execute([$status,$note,$u['id'],$status,$id]);
        flash('success','Đã cập nhật khiếu nại.');
        $returnView = (string)($_POST['return_view'] ?? 'all');
        if (!in_array($returnView, ['all','unresolved','open','in_progress','responded','closed'], true)) $returnView = 'all';
        redirect('admin/complaints?view=' . rawurlencode($returnView));
    }
}
