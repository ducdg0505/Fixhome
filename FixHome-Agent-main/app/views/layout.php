<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isActive = static function (string $path) use ($currentPath): bool {
    if ($path === '/') return $currentPath === '/';
    return $currentPath === $path || str_starts_with($currentPath, rtrim($path, '/') . '/');
};
$isGuestHome = !$user && $currentPath === '/';
$brandHref = $user ? url(user_home($user)) : '/';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="FixHome - nền tảng kết nối khách hàng với doanh nghiệp sửa chữa gia dụng.">
  <meta name="theme-color" content="#0b2b3c">
  <title><?= e($pageTitle) ?> | FixHome</title>
  <link rel="icon" href="/assets/images/fixhome_logo.webp" type="image/webp">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
</head>
<body class="<?= $user ? 'app-auth app-role-' . e($user['role']) : ($isGuestHome ? 'app-public guest-home-page' : 'app-public') ?>">
<a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
<?php if ($isGuestHome): ?>
<div class="guest-shell">
  <aside class="guest-sidebar" aria-label="Đăng nhập và đăng ký FixHome">
    <div class="guest-sidebar-inner">
      <a class="guest-sidebar-brand" href="/" aria-label="FixHome Cần Thơ - Trang chủ">
        <img src="/assets/images/fixhome_logo.webp" alt="FixHome">
        <span><b>FixHome</b><small>Cần Thơ</small></span>
      </a>
      <p class="guest-sidebar-copy">Đặt sửa chữa, nhận báo giá và theo dõi tiến trình trên một nơi duy nhất.</p>

      <div class="guest-auth-tabs" role="tablist" aria-label="Tài khoản">
        <button id="guest-auth-tab-login" type="button" class="active" role="tab" aria-selected="true" aria-controls="guest-auth-panel-login" tabindex="0" data-guest-auth-tab="login">Đăng nhập</button>
        <button id="guest-auth-tab-register" type="button" role="tab" aria-selected="false" aria-controls="guest-auth-panel-register" tabindex="-1" data-guest-auth-tab="register">Đăng ký</button>
      </div>

      <div id="guest-auth-panel-login" class="guest-auth-panel" role="tabpanel" aria-labelledby="guest-auth-tab-login" data-guest-auth-panel="login">
        <form method="post" action="/login" class="guest-auth-form">
          <?= csrf_field() ?>
          <label>Email<input type="email" name="email" placeholder="Nhập email" autocomplete="email" required></label>
          <label>Mật khẩu<span class="password-field"><input type="password" name="password" placeholder="Nhập mật khẩu" autocomplete="current-password" required><?= password_visibility_button() ?></span></label>
          <button class="guest-auth-submit" type="submit">Đăng nhập</button>
        </form>
      </div>

      <div id="guest-auth-panel-register" class="guest-auth-panel" role="tabpanel" aria-labelledby="guest-auth-tab-register" data-guest-auth-panel="register" hidden>
        <form method="post" action="/register" class="guest-auth-form">
          <?= csrf_field() ?>
          <label>Họ và tên<input name="name" autocomplete="name" required></label>
          <label>Số điện thoại<input type="tel" inputmode="tel" name="phone" autocomplete="tel" placeholder="0901234567 hoặc +84901234567" required></label>
          <label>Email<input type="email" name="email" autocomplete="email" required></label>
          <label>Mật khẩu<small class="field-help"><?= e(PasswordPolicy::HELP_TEXT) ?></small><span class="password-field"><input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required><?= password_visibility_button() ?></span></label>
          <label>Nhập lại mật khẩu<span class="password-field"><input type="password" name="password2" autocomplete="new-password" minlength="12" maxlength="72" required><?= password_visibility_button() ?></span></label>
          <button class="guest-auth-submit" type="submit">Tạo tài khoản</button>
        </form>
      </div>

      <p class="guest-sidebar-note">FixHome là nền tảng trung gian. Doanh nghiệp đối tác chịu trách nhiệm khảo sát, báo giá cuối cùng và bảo hành dịch vụ.</p>
    </div>
  </aside>
  <div class="guest-stage">
<?php else: ?>
<header class="topbar">
  <?php if ($user): ?>
  <div class="topbar-inner">
    <a class="brand" href="<?= e($brandHref) ?>" aria-label="FixHome - Trang chính"><img class="brand-logo" src="/assets/images/fixhome_logo.webp" alt="FixHome"><span class="brand-wordmark">FixHome</span></a>
    <button class="menu-btn" type="button" data-menu aria-label="Mở menu" aria-expanded="false" aria-controls="main-nav">☰</button>
    <div class="topbar-menu" data-nav id="main-nav">
      <nav class="nav primary-nav" aria-label="Điều hướng theo vai trò">
        <?php if (!empty($user['must_change_password'])): ?>
          <a class="active" href="/account">Đổi mật khẩu tạm thời</a>
        <?php elseif ($user['role']==='customer'): ?>
          <a class="<?= $isActive('/customer') && !$isActive('/customer/book') && !$isActive('/customer/orders') ? 'active' : '' ?>" href="/customer">Tổng quan</a>
          <a class="<?= $isActive('/customer/book') ? 'active' : '' ?>" href="/customer/book">Đặt dịch vụ</a>
          <a class="<?= $isActive('/customer/orders') ? 'active' : '' ?>" href="/customer/orders">Theo dõi đơn</a>
        <?php endif; ?>
        <?php if (empty($user['must_change_password']) && $user['role']==='company'): ?>
          <a class="<?= $currentPath === '/company' ? 'active' : '' ?>" href="/company">Tổng quan</a>
          <a class="<?= $isActive('/company/orders') ? 'active' : '' ?>" href="/company/orders">Cơ hội & công việc</a>
          <a class="<?= $isActive('/company/technicians') ? 'active' : '' ?>" href="/company/technicians">Kỹ thuật viên</a>
          <a class="<?= $isActive('/company/revenue') ? 'active' : '' ?>" href="/company/revenue">Doanh thu</a>
        <?php endif; ?>
        <?php if (empty($user['must_change_password']) && $user['role']==='technician'): ?><a class="<?= $isActive('/technician') ? 'active' : '' ?>" href="/technician">Công việc</a><?php endif; ?>
        <?php if (empty($user['must_change_password']) && $user['role']==='admin'): ?>
          <a class="<?= $currentPath === '/admin' ? 'active' : '' ?>" href="/admin">Tổng quan</a>
          <a class="<?= $isActive('/admin/accounts') ? 'active' : '' ?>" href="/admin/accounts">Tài khoản</a>
          <a class="<?= $isActive('/admin/partners') ? 'active' : '' ?>" href="/admin/partners">Đối tác</a>
          <a class="<?= $isActive('/admin/orders') ? 'active' : '' ?>" href="/admin/orders">Đơn</a>
          <a class="<?= $isActive('/admin/companies') ? 'active' : '' ?>" href="/admin/companies">Doanh nghiệp</a>
          <a class="<?= $isActive('/admin/complaints') ? 'active' : '' ?>" href="/admin/complaints">Khiếu nại</a>
        <?php endif; ?>
      </nav>
      <div class="topbar-actions" aria-label="Tài khoản và thông báo">
        <?php if (empty($user['must_change_password'])): ?><a class="notification-link <?= $isActive('/notifications') ? 'active' : '' ?>" href="/notifications">Thông báo<?php if($notificationUnread): ?><span><?= $notificationUnread > 99 ? '99+' : $notificationUnread ?></span><?php endif; ?></a><?php endif; ?>
        <a class="user-chip <?= $isActive('/account') ? 'active' : '' ?>" href="/account"><span class="user-chip-name"><?= e($user['name']) ?></span><small><?= e(role_label($user['role'])) ?></small></a>
        <form method="post" action="/logout" class="inline-form"><?= csrf_field() ?><button class="link-button">Đăng xuất</button></form>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="container navwrap">
    <a class="brand" href="/" aria-label="FixHome - Trang chủ"><img class="brand-logo" src="/assets/images/fixhome_logo.webp" alt="FixHome"><span class="brand-wordmark">FixHome</span></a>
    <button class="menu-btn" type="button" data-menu aria-label="Mở menu" aria-expanded="false" aria-controls="main-nav">☰</button>
    <nav class="nav" data-nav id="main-nav" aria-label="Điều hướng chính">
      <a href="/#services">Dịch vụ</a>
      <a href="/#process">Quy trình</a>
      <a href="/#partner">Đối tác</a>
      <a class="<?= $isActive('/login') ? 'active' : '' ?>" href="/login">Đăng nhập</a>
      <a class="btn small" href="/register">Đăng ký</a>
    </nav>
  </div>
  <?php endif; ?>
</header>
<?php endif; ?>
<main id="main-content"<?= $isGuestHome ? ' class="guest-home-main"' : '' ?>>
  <?php if ($flashes): ?><div class="container flash-stack"><?php foreach($flashes as $f): ?><?php $urgentFlash = in_array($f['type'], ['error','danger','warning'], true); ?><div class="alert <?= e($f['type']) ?>" role="<?= $urgentFlash ? 'alert' : 'status' ?>" aria-live="<?= $urgentFlash ? 'assertive' : 'polite' ?>"><?= e($f['message']) ?></div><?php endforeach; ?></div><?php endif; ?>
  <?= $content ?>
</main>
<footer><div class="container footer-grid"><div class="footer-brand"><img src="/assets/images/fixhome_logo.webp" alt="FixHome"><div><b>FixHome</b><p>Nền tảng trung gian kết nối khách hàng với doanh nghiệp sửa chữa đã xác minh.</p></div></div><div><b>Giá minh bạch hơn</b><p>Giá hiển thị là khoảng tham khảo. Khách hàng xem báo giá của doanh nghiệp và chủ động chọn trước khi công việc được giao.</p></div><div class="footer-contact"><b>Liên hệ hỗ trợ</b><a href="tel:0949161719">094 916 17 19</a><a href="mailto:duongtta.cs191525@gmail.com">duongtta.cs191525@gmail.com</a><a href="https://www.facebook.com/profile.php?id=61591633523951" target="_blank" rel="noopener noreferrer">Facebook FixHome ↗</a></div></div></footer>
<?php if ($isGuestHome): ?>
  </div>
</div>
<?php endif; ?>
<script src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
</body></html>
