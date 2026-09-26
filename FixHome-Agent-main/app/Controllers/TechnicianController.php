<?php
final class TechnicianController
{
    public static function dashboard(): void
    {
        $u = require_role('technician');
        $workView = RoleWorkView::normalizeTechnicianView((string)($_GET['view'] ?? 'today'));
        $outcome = (string)($_GET['outcome'] ?? 'all');
        if (!in_array($outcome, ['all','completed','cancelled'], true) || $workView !== 'history') $outcome = 'all';

        $activeStatuses = RoleWorkView::technicianActiveStatuses();
        $activeSql = "'" . implode("','", $activeStatuses) . "'";
        $statsStmt = db()->prepare(
            "SELECT
                COALESCE(SUM(o.status IN ($activeSql) AND DATE(o.scheduled_at)=CURDATE()),0) today,
                COALESCE(SUM(o.status='tech_assigned' AND DATE(o.scheduled_at)>CURDATE()),0) upcoming,
                COALESCE(SUM(o.status IN ($activeSql)),0) active,
                COALESCE(SUM(o.status='work_done'),0) awaiting,
                COALESCE(SUM(o.status='completed'),0) completed,
                COALESCE(SUM(o.status IN ('completed','cancelled')),0) history
             FROM orders o
             WHERE o.technician_id=? AND o.company_id=?"
        );
        $statsStmt->execute([(int)$u['id'], (int)$u['company_id']]);
        $stats = $statsStmt->fetch() ?: [];

        $where = ['o.technician_id=?', 'o.company_id=?'];
        $params = [(int)$u['id'], (int)$u['company_id']];
        if ($workView === 'today') {
            $where[] = "o.status IN ($activeSql)";
            $where[] = 'DATE(o.scheduled_at)=CURDATE()';
        } elseif ($workView === 'upcoming') {
            $where[] = "o.status='tech_assigned'";
            $where[] = 'DATE(o.scheduled_at)>CURDATE()';
        } elseif ($workView === 'active') {
            $where[] = "o.status IN ($activeSql)";
        } elseif ($workView === 'awaiting') {
            $where[] = "o.status='work_done'";
        } else {
            $where[] = $outcome === 'all' ? "o.status IN ('completed','cancelled')" : 'o.status=?';
            if ($outcome !== 'all') $params[] = $outcome;
        }

        $s = db()->prepare(
            'SELECT o.*,c.name company_name,sc.name category_name,
                    rr.id repair_report_id,rr.actual_issue,rr.resolution,rr.post_repair_advice,rr.submitted_at repair_report_submitted_at,rr.updated_at repair_report_updated_at
             FROM orders o
             JOIN companies c ON c.id=o.company_id
             JOIN service_categories sc ON sc.id=o.category_id
             LEFT JOIN repair_reports rr ON rr.order_id=o.id AND rr.technician_id=o.technician_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY o.scheduled_at DESC,o.id DESC'
        );
        $s->execute($params);
        $orders = $s->fetchAll();
        foreach ($orders as &$order) {
            $order['allowed_statuses'] = OrderState::technicianProgressTransitionsFrom((string)$order['status']);
            $order = self::protectCustomerContact($order);
        }
        unset($order);
        $views = RoleWorkView::technicianViews();
        render('technician/dashboard',compact('u','orders','workView','views','outcome','stats'),'Kỹ thuật viên');
    }

    private static function protectCustomerContact(array $order): array
    {
        $order['customer_contact_visible'] = !OrderState::isTerminal((string)$order['status']);
        if (!$order['customer_contact_visible']) {
            $order['customer_phone'] = null;
            $order['customer_email'] = null;
            $order['address'] = null;
        }
        return $order;
    }

    public static function updateStatus(): void
    {
        $u=require_role('technician');verify_csrf();
        try {
            OrderService::updateTechnicianProgress(post_int('order_id'),(int)$u['id'],post_string('status',80),post_string('note',2000));
            flash('success','Đã cập nhật tiến trình.');
        } catch(Throwable $e) {
            flash('error',$e->getMessage());
        }
        $returnView = RoleWorkView::normalizeTechnicianView(post_string('return_view',30));
        redirect('technician?view=' . rawurlencode($returnView));
    }

    public static function submitRepairReport(): void
    {
        $u = require_role('technician');
        verify_csrf();
        $redirectView = 'active';
        try {
            OrderService::submitRepairReport(
                post_int('order_id'),
                (int)$u['id'],
                post_string('actual_issue',10001),
                post_string('resolution',10001),
                post_string('post_repair_advice',10001)
            );
            flash('success','Đã gửi biên bản kỹ thuật để doanh nghiệp xác nhận.');
            $redirectView = 'awaiting';
        } catch (Throwable $e) {
            flash('error',$e->getMessage());
        }
        redirect('technician?view=' . $redirectView);
    }

    public static function updateRepairReport(): void
    {
        $u = require_role('technician');
        verify_csrf();
        $orderId = post_int('order_id');
        $redirectView = 'awaiting';
        try {
            $changed = OrderService::updateRepairReport(
                $orderId,
                (int)$u['id'],
                post_string('actual_issue',10001),
                post_string('resolution',10001),
                post_string('post_repair_advice',10001)
            );
            flash($changed ? 'success' : 'info',$changed ? 'Đã cập nhật biên bản kỹ thuật.' : 'Biên bản kỹ thuật không thay đổi.');
        } catch (Throwable $e) {
            flash('error',$e->getMessage());
            $redirectView = self::repairReportFailureView($orderId, (int)$u['id'], (int)$u['company_id']);
        }
        redirect($redirectView === null ? 'technician' : 'technician?view=' . $redirectView);
    }

    private static function repairReportFailureView(int $orderId, int $technicianId, int $companyId): ?string
    {
        try {
            $stmt = db()->prepare('SELECT status FROM orders WHERE id=? AND technician_id=? AND company_id=? LIMIT 1');
            $stmt->execute([$orderId,$technicianId,$companyId]);
            $status = $stmt->fetchColumn();
            if ($status === OrderState::WORK_DONE) return 'awaiting';
            if ($status === OrderState::COMPLETED || $status === OrderState::CANCELLED) return 'history';
            if (is_string($status) && in_array($status, RoleWorkView::technicianActiveStatuses(), true)) return 'active';
        } catch (Throwable $ignored) {
            // Preserve the original update error and use the safe dashboard fallback.
        }
        return null;
    }
}
