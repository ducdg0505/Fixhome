<section class="container section" data-reveal>
  <div class="page-head page-head-split">
    <div><span class="section-kicker">KHÁCH HÀNG</span><h1>Xin chào, <?= e($u['name']) ?></h1><p>Đặt dịch vụ, nhận báo giá và theo dõi từng bước sửa chữa.</p></div>
    <div class="actions-row"><a class="btn" href="/customer/book">+ Đặt dịch vụ mới</a><a class="btn ghost dark" href="/customer/orders">Theo dõi đơn</a></div>
  </div>

  <div class="metrics dashboard-metrics">
    <a class="metric metric-link" href="/customer/orders?status=active"><span>Đơn đang thực hiện</span><b><?= (int)($stats['active']??0) ?></b><small>Mở đúng nhóm công việc →</small></a>
    <a class="metric metric-link" href="/customer/orders?status=completed"><span>Đã hoàn thành</span><b><?= (int)($stats['completed']??0) ?></b><small>Xem lịch sử dịch vụ →</small></a>
    <a class="metric metric-link" href="/customer/orders"><span>Tổng yêu cầu</span><b><?= (int)($stats['total']??0) ?></b><small>Xem tất cả yêu cầu →</small></a>
    <a class="metric metric-link" href="/customer/orders?status=action"><span>Cần bạn xử lý</span><b><?= (int)($stats['action_required']??0) ?></b><small>Xem báo giá cần chọn →</small></a>
    <a class="metric metric-link" href="/customer/orders?status=work_done"><span>Chờ doanh nghiệp xác nhận</span><b><?= (int)($stats['awaiting_confirmation']??0) ?></b><small>Xem công việc đã sửa xong →</small></a>
  </div>

  <div class="journey-strip" aria-label="Hành trình đơn hàng">
    <div><b>1</b><span>Gửi yêu cầu</span></div><i>→</i><div><b>2</b><span>Nhận báo giá</span></div><i>→</i><div><b>3</b><span>Chọn doanh nghiệp</span></div><i>→</i><div><b>4</b><span>Theo dõi sửa chữa</span></div>
  </div>

  <div class="card">
    <div class="card-heading"><div><h2>Đơn gần đây</h2><p class="muted">Xem nhanh trạng thái trước khi mở trang theo dõi đầy đủ.</p></div><a href="/customer/orders">Xem tất cả</a></div>
    <?php if(!$recent): ?>
      <div class="empty compact-empty"><b>Chưa có yêu cầu nào.</b><p>Hãy chọn dịch vụ hoặc mô tả lỗi để bắt đầu.</p><a class="btn small" href="/customer/book">Đặt dịch vụ</a></div>
    <?php else: ?>
      <div class="table-wrap"><table><thead><tr><th>Mã đơn</th><th>Nhóm</th><th>Doanh nghiệp</th><th>Trạng thái</th><th>Lịch hẹn</th></tr></thead><tbody><?php foreach($recent as $o): ?><tr><td><b><?= e($o['order_code']) ?></b></td><td><?= e($o['category_name']) ?></td><td><?= e($o['company_name']?:'Đang chờ khách chọn') ?></td><td><span class="pill <?= status_class($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td><td><?= dt($o['scheduled_at']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
</section>
