<?php
function db(): PDO { global $CONFIG; return Database::connect($CONFIG); }
function config(?string $key = null, $default = null) {
    global $CONFIG;
    if ($key === null) return $CONFIG;
    $value = $CONFIG;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return '/' . ltrim($path, '/'); }
function asset_url(string $path): string {
    $relative = ltrim($path, '/');
    $file = dirname(__DIR__) . '/' . $relative;
    $version = is_file($file) ? (string)filemtime($file) : '1';
    return url($relative) . '?v=' . rawurlencode($version);
}
function redirect(string $path): never { header('Location: ' . url($path), true, 303); exit; }
function flash(string $type, string $message): void { $_SESSION['_flash'][] = compact('type', 'message'); }
function consume_flashes(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    $token = $_POST['_csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419); exit('CSRF token không hợp lệ. Vui lòng tải lại trang.');
    }
}
function current_user(): ?array { return Auth::user(); }
function password_change_route_allowed(string $path): bool {
    return in_array($path, ['/account','/account/password','/logout'], true);
}
function require_auth(): array {
    $u = current_user();
    if (!$u) { flash('error','Vui lòng đăng nhập.'); redirect('login'); }
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (!empty($u['must_change_password']) && !password_change_route_allowed($path)) {
        flash('warning','Bạn cần đổi mật khẩu tạm thời trước khi sử dụng các chức năng khác.');
        redirect('account');
    }
    return $u;
}
function require_role(string ...$roles): array {
    $u = require_auth();
    if (!in_array($u['role'], $roles, true)) { http_response_code(403); exit('Bạn không có quyền truy cập chức năng này.'); }
    return $u;
}
function role_home(string $role): string {
    return match ($role) {
        'admin' => 'admin', 'customer' => 'customer', 'company' => 'company', 'technician' => 'technician', default => ''
    };
}
function user_home(array $user): string {
    return !empty($user['must_change_password']) ? 'account' : role_home((string)($user['role'] ?? ''));
}
function telephone_uri(?string $phone): string {
    $normalized = PhonePolicy::normalize((string)$phone);
    return $normalized === null ? '' : 'tel:' . $normalized;
}
function password_visibility_button(): string {
    return '<button type="button" class="password-toggle" data-password-toggle aria-label="Hiện mật khẩu" aria-pressed="false">'
        . '<svg class="password-icon password-icon-eye" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.4 12s3.5-6 9.6-6 9.6 6 9.6 6-3.5 6-9.6 6-9.6-6-9.6-6Z"/><circle cx="12" cy="12" r="2.7"/></svg>'
        . '<svg class="password-icon password-icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m3 3 18 18M10.6 6.1A10.9 10.9 0 0 1 12 6c6.1 0 9.6 6 9.6 6a16.6 16.6 0 0 1-3.1 3.7M6.2 6.2C3.8 7.8 2.4 12 2.4 12s3.5 6 9.6 6c1.5 0 2.8-.4 4-1M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>'
        . '</button>';
}
function vnd($value): string { return number_format((float)$value, 0, ',', '.') . 'đ'; }
function role_label(?string $role): string {
    return match ($role) {
        'admin' => 'Quản trị FixHome',
        'customer' => 'Khách hàng',
        'company' => 'Doanh nghiệp đối tác',
        'technician' => 'Kỹ thuật viên',
        default => (string)$role,
    };
}
function dt($value): string { if (!$value) return '—'; return date('d/m/Y H:i', strtotime($value)); }
function status_class(string $status): string {
    if (in_array($status, ['completed','approved','responded','closed'], true)) return 'success';
    if (in_array($status, ['cancelled','rejected','declined','not_selected','cancelled_by_customer','cancelled_by_admin','banned','deleted'], true)) return 'danger';
    if (in_array($status, ['pending_distribution','waiting_quote','quoted','work_done','pending_review','open'], true)) return 'warning';
    return 'info';
}
function status_label(?string $status): string {
    $labels = [
        'pending_distribution'=>'Chờ phân phối','waiting_quote'=>'Chờ báo giá','quoted'=>'Đã có báo giá',
        'quote_accepted'=>'Đã chọn báo giá','tech_assigned'=>'Đã phân công kỹ thuật viên',
        'tech_accepted'=>'Kỹ thuật viên đã nhận việc','on_the_way'=>'Đang di chuyển',
        'inspecting'=>'Đang kiểm tra','repairing'=>'Đang sửa chữa','work_done'=>'Đã sửa xong – chờ doanh nghiệp xác nhận','completed'=>'Hoàn thành',
        'cancelled'=>'Đã hủy','cancelled_by_customer'=>'Khách hàng đã hủy','cancelled_by_admin'=>'Quản trị viên đã hủy','invited'=>'Đã mời','viewed'=>'Đã xem',
        'quote_submitted'=>'Đã gửi báo giá','selected'=>'Được chọn','not_selected'=>'Không được chọn',
        'declined'=>'Đã từ chối','expired'=>'Hết hạn','submitted'=>'Chờ khách hàng chọn',
        'accepted_by_customer'=>'Khách hàng đã chọn','rejected_not_selected'=>'Không được chọn',
        'superseded'=>'Đã thay thế','changes_requested'=>'Cần điều chỉnh',
        'withdrawn_by_company'=>'Doanh nghiệp rút báo giá','pending_review'=>'Chờ kiểm tra pháp lý',
        'approved'=>'Đã duyệt','rejected'=>'Từ chối','open'=>'Mới',
        'in_progress'=>'Đang xử lý','responded'=>'Đã phản hồi','closed'=>'Đã đóng',
        'pending_verification'=>'Chờ xác minh','verified'=>'Đã xác minh',
        'not_provisioned'=>'Chưa cấp tài khoản','active'=>'Đang hoạt động','inactive'=>'Ngừng hoạt động','suspended'=>'Tạm ngừng','banned'=>'Bị cấm','deleted'=>'Đã xóa',
    ];
    return $labels[$status ?? ''] ?? (string)$status;
}
function random_password(int $length = 12): string {
    $length = max(PasswordPolicy::MIN_LENGTH, min(PasswordPolicy::MAX_LENGTH, $length));
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    do {
        $out = '';
        for ($i=0; $i<$length; $i++) $out .= $chars[random_int(0, strlen($chars)-1)];
    } while (!PasswordPolicy::isValid($out));
    return $out;
}
function client_ip(): ?string {
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return $ip === '' ? null : substr($ip, 0, 64);
}
function rate_limited(string $action, int $maxAttempts, int $windowMinutes): bool {
    $maxAttempts = max(1, min(1000, $maxAttempts));
    $windowMinutes = max(1, min(1440, $windowMinutes));
    $ip = client_ip();
    if ($ip === null) return false;
    $stmt = db()->prepare("SELECT COUNT(*) FROM audit_logs WHERE action=? AND ip_address=? AND created_at>=DATE_SUB(NOW(), INTERVAL {$windowMinutes} MINUTE)");
    $stmt->execute([$action,$ip]);
    return (int)$stmt->fetchColumn() >= $maxAttempts;
}
function audit_public(string $action, ?string $entityType = null, ?int $entityId = null, string $detail = ''): void {
    try {
        $stmt = db()->prepare('INSERT INTO audit_logs(user_id,action,entity_type,entity_id,detail,ip_address) VALUES(NULL,?,?,?,?,?)');
        $stmt->execute([$action,$entityType,$entityId,$detail,client_ip()]);
    } catch (Throwable $e) { /* public audit must not break request handling */ }
}

function audit(string $action, ?string $entityType = null, ?int $entityId = null, string $detail = ''): void {
    try {
        $uid = $_SESSION['user_id'] ?? null;
        $stmt = db()->prepare('INSERT INTO audit_logs(user_id,action,entity_type,entity_id,detail,ip_address) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$uid,$action,$entityType,$entityId,$detail,client_ip()]);
    } catch (Throwable $e) { /* auditing must not break business flow */ }
}
function notify(int $userId, string $title, string $message, string $type = 'general', ?int $orderId = null): void {
    $stmt = db()->prepare('INSERT INTO notifications(target_user_id,order_id,notification_type,title,message) VALUES(?,?,?,?,?)');
    $stmt->execute([$userId,$orderId,$type,$title,$message]);
}
function notify_role(string $role, string $title, string $message, string $type = 'general', ?int $orderId = null): void {
    $stmt = db()->prepare("INSERT INTO notifications(target_user_id,order_id,notification_type,title,message) SELECT id,?,?,?,? FROM users WHERE role=? AND status='active'");
    $stmt->execute([$orderId,$type,$title,$message,$role]);
}

function page_number(): int {
    $page = (int)($_GET['page'] ?? 1);
    return max(1, min(100000, $page));
}
function pagination(int $total, int $perPage = 20): array {
    $perPage = max(1, min(100, $perPage));
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min(page_number(), $pages);
    return [
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'total' => $total,
    ];
}
function pager(string $path, array $meta): string {
    $page = (int)($meta['page'] ?? 1);
    $pages = (int)($meta['pages'] ?? 1);
    if ($pages <= 1) return '';
    $out = '<nav class="pager" aria-label="Phân trang">';
    $query = $_GET;
    $pageUrl = static function (int $target) use ($path, $query): string {
        $query['page'] = $target;
        return url($path) . '?' . http_build_query($query);
    };
    if ($page > 1) $out .= '<a class="btn ghost dark" href="' . e($pageUrl($page - 1)) . '">← Trang trước</a>';
    $out .= '<span>Trang ' . $page . '/' . $pages . '</span>';
    if ($page < $pages) $out .= '<a class="btn ghost dark" href="' . e($pageUrl($page + 1)) . '">Trang sau →</a>';
    return $out . '</nav>';
}

function render(string $view, array $data = [], string $title = 'FixHome'): void {
    extract($data, EXTR_SKIP);
    $viewFile = __DIR__ . '/views/' . $view . '.php';
    if (!is_file($viewFile)) throw new RuntimeException('View not found: ' . $view);
    $pageTitle = $title;
    $user = current_user();
    $notificationUnread = 0;
    if ($user) {
        $notificationStmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE target_user_id=? AND is_read=0');
        $notificationStmt->execute([$user['id']]);
        $notificationUnread = (int)$notificationStmt->fetchColumn();
    }
    $flashes = consume_flashes();
    ob_start(); require $viewFile; $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
}
function post_string(string $key, int $max = 1000): string { $v=trim((string)($_POST[$key] ?? '')); return function_exists('mb_substr') ? mb_substr($v,0,$max,'UTF-8') : substr($v,0,$max); }
function post_phone_input(string $key = 'phone'): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}
function post_int(string $key): int { return (int)($_POST[$key] ?? 0); }
