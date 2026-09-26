<section class="container section" data-reveal>
  <div class="page-head page-head-split"><div><span class="section-kicker">QUẢN TRỊ FIXHOME</span><h1>Tổng quan vận hành</h1><p>Theo dõi kết nối marketplace, hồ sơ đối tác và khiếu nại; không hiển thị doanh thu riêng của doanh nghiệp.</p></div><div class="actions-row"><a class="btn" href="/admin/orders">Phân phối đơn</a><a class="btn ghost dark" href="/admin/accounts">Tài khoản khách hàng</a><a class="btn ghost dark" href="/admin/partners">Duyệt đối tác</a></div></div>
  <div class="metrics admin-metrics dashboard-metrics">
    <div class="metric"><span>Khách hàng</span><b><?= $stats['customers'] ?></b></div>
    <div class="metric"><span>Doanh nghiệp xác minh</span><b><?= $stats['companies'] ?></b><small>Đang hoạt động trên marketplace</small></div>
    <a class="metric metric-link" href="/admin/orders"><span>Tổng đơn</span><b><?= $stats['orders'] ?></b><small>Xem toàn bộ đơn →</small></a>
    <a class="metric metric-link" href="/admin/partners?status=pending_review"><span>Đối tác chờ duyệt</span><b><?= $stats['pending_partners'] ?></b><small>Mở đúng hàng chờ →</small></a>
    <a class="metric metric-link" href="/admin/orders?group=distribution"><span>Đơn chờ phân phối</span><b><?= $stats['pending_orders'] ?></b><small>Xem đúng nhóm đơn →</small></a>
    <a class="metric metric-link" href="/admin/complaints?view=unresolved"><span>Khiếu nại chưa đóng</span><b><?= $stats['complaints'] ?></b><small>Mở hàng cần xử lý →</small></a>
  </div>
  <div class="card">
    <div class="card-heading"><div><h2>Đơn gần đây</h2><p class="muted">Doanh nghiệp chỉ được coi là người thắng khi khách đã chọn báo giá.</p></div><a href="/admin/orders">Xem toàn bộ</a></div>
    <?php if(!$recent): ?><div class="empty compact-empty">Chưa có đơn.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Doanh nghiệp được chọn</th><th>Trạng thái</th><th>Tạo lúc</th></tr></thead><tbody><?php foreach($recent as $o): ?><tr><td><b><?= e($o['order_code']) ?></b></td><td><?= e($o['customer_name']) ?></td><td><?= e($o['company_name']?:'Chưa chọn') ?></td><td><span class="pill <?= status_class($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td><td><?= dt($o['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div>
</section>
