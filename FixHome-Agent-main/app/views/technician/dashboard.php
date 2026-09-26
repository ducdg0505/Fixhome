<?php
$viewCounts = [
    'today' => (int)($stats['today'] ?? 0),
    'upcoming' => (int)($stats['upcoming'] ?? 0),
    'active' => (int)($stats['active'] ?? 0),
    'awaiting' => (int)($stats['awaiting'] ?? 0),
    'history' => (int)($stats['history'] ?? 0),
];
$viewSubtitles = [
    'today' => 'Công việc đang hoạt động có lịch hẹn hôm nay.',
    'upcoming' => 'Công việc chưa bắt đầu, đã được giao cho ngày tiếp theo.',
    'active' => 'Toàn bộ công việc đang hoạt động, bao gồm lịch hôm nay và việc sắp tới đã được giao.',
    'awaiting' => 'Bạn đã sửa xong; doanh nghiệp cần xác nhận kết quả và chi phí cuối.',
    'history' => $outcome === 'completed' ? 'Chỉ hiển thị công việc đã hoàn thành.' : ($outcome === 'cancelled' ? 'Chỉ hiển thị công việc đã hủy.' : 'Công việc đã hoàn thành hoặc đã hủy.'),
];
$viewUrl = static fn(string $target): string => '/technician?view=' . rawurlencode($target);
$progressSteps = [
    OrderState::TECH_ASSIGNED => 'Được giao',
    OrderState::TECH_ACCEPTED => 'Đã nhận',
    OrderState::ON_THE_WAY => 'Đang đi',
    OrderState::INSPECTING => 'Kiểm tra',
    OrderState::REPAIRING => 'Sửa chữa',
    OrderState::WORK_DONE => 'Đã sửa xong',
];
?>
<section class="container section" data-reveal>
  <div class="page-head"><span class="section-kicker">KỸ THUẬT VIÊN</span><h1>Công việc được phân công</h1><p>Ưu tiên lịch làm việc, liên hệ đúng khách hàng và cập nhật tiến trình theo các bước được phép.</p></div>

  <div class="metrics dashboard-metrics technician-metrics">
    <a class="metric metric-link" href="/technician?view=today"><span>Lịch hôm nay</span><b><?= $viewCounts['today'] ?></b><small>Mở lịch ưu tiên →</small></a>
    <a class="metric metric-link" href="/technician?view=active"><span>Đang xử lý</span><b><?= $viewCounts['active'] ?></b><small>Gồm lịch hôm nay và việc đã giao →</small></a>
    <a class="metric metric-link" href="/technician?view=history&amp;outcome=completed"><span>Đã hoàn thành</span><b><?= (int)($stats['completed'] ?? 0) ?></b><small>Xem lịch sử hoàn thành →</small></a>
    <a class="metric metric-link" href="/technician?view=awaiting"><span>Chờ xác nhận</span><b><?= $viewCounts['awaiting'] ?></b><small>Doanh nghiệp cần chốt kết quả →</small></a>
  </div>

  <nav class="order-status-tabs role-work-tabs" aria-label="Lọc công việc kỹ thuật viên">
    <?php foreach($views as $value => $label): ?><a href="<?= e($viewUrl($value)) ?>" class="<?= $workView===$value ? 'active' : '' ?>" <?= $workView===$value ? 'aria-current="page"' : '' ?>><?= e($label) ?> <span><?= $viewCounts[$value] ?></span></a><?php endforeach; ?>
  </nav>

  <div class="section-heading-inline role-view-heading"><div><h2><?= e($views[$workView]) ?></h2><p><?= e($viewSubtitles[$workView]) ?></p></div><span class="filter-result"><?= count($orders) ?> công việc</span></div>
  <?php if(!$orders): ?><div class="empty"><b>Không có công việc trong nhóm này.</b><p>Khi có công việc phù hợp, đơn sẽ xuất hiện tại đây.</p></div><?php endif; ?>

  <?php foreach($orders as $o):
    $currentIndex = array_search((string)$o['status'], array_keys($progressSteps), true);
    $customerPhoneUri = telephone_uri($o['customer_phone'] ?? null);
    $copyStatusId = 'copy-phone-status-' . (int)$o['id'];
  ?>
    <article class="order-card ux-order-card technician-card">
      <div class="order-head"><div><span class="category-tag"><?= e($o['category_name']) ?></span><h2><?= e($o['order_code']) ?></h2><small><?= dt($o['scheduled_at']) ?></small></div><span class="pill <?= status_class($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></div>

      <div class="tech-progress" aria-label="Tiến trình công việc">
        <?php foreach($progressSteps as $status => $label): $stepIndex = array_search($status, array_keys($progressSteps), true); $done = $currentIndex !== false && $stepIndex <= $currentIndex; ?>
          <span class="<?= $done ? 'done' : '' ?> <?= $status===$o['status'] ? 'current' : '' ?>"><i></i><?= e($label) ?></span>
        <?php endforeach; ?>
      </div>

      <?php if(!empty($o['customer_contact_visible'])): ?>
      <div class="contact-panel technician-contact">
        <div class="technician-phone-card">
          <small>LIÊN HỆ KHÁCH HÀNG</small>
          <b><?= e($o['customer_name'] ?: 'Khách hàng') ?></b>
          <?php if($customerPhoneUri): ?>
            <span class="customer-phone-number"><?= e($o['customer_phone']) ?></span>
            <div class="phone-actions">
              <a class="call-customer-btn" href="<?= e($customerPhoneUri) ?>">Gọi khách</a>
              <button type="button" class="copy-phone-btn" data-copy-phone data-phone="<?= e($o['customer_phone']) ?>" aria-describedby="<?= e($copyStatusId) ?>">Sao chép số</button>
            </div>
            <span class="copy-phone-status" id="<?= e($copyStatusId) ?>" role="status" aria-live="polite"></span>
          <?php else: ?>
            <span class="phone-fallback">Khách hàng chưa cung cấp số điện thoại liên hệ</span>
          <?php endif; ?>
        </div>
        <div><small>ĐỊA CHỈ PHỤC VỤ</small><b><?= e($o['address'] ?: 'Chưa có địa chỉ') ?></b></div>
        <div><small>DOANH NGHIỆP</small><b><?= e($o['company_name']) ?></b></div>
      </div>
      <?php else: ?>
      <div class="contact-panel technician-contact terminal-contact"><div><small>KHÁCH HÀNG</small><b><?= e($o['customer_name'] ?: 'Khách hàng') ?></b><span>Thông tin liên hệ đã được ẩn sau khi đơn kết thúc.</span></div><div><small>DOANH NGHIỆP</small><b><?= e($o['company_name']) ?></b></div></div>
      <?php endif; ?>
      <?php if($o['description']): ?><div class="note"><b>Mô tả từ khách:</b> <?= nl2br(e($o['description'])) ?></div><?php endif; ?>

      <?php if(!empty($o['repair_report_id'])): ?>
        <section class="repair-report-card" aria-label="Biên bản kỹ thuật">
          <div class="repair-report-heading"><span class="section-kicker">BIÊN BẢN KỸ THUẬT</span><small>Cập nhật <?= dt($o['repair_report_updated_at']) ?></small></div>
          <dl class="repair-report-fields">
            <div><dt>Vấn đề thực tế phát hiện</dt><dd><?= nl2br(e($o['actual_issue'])) ?></dd></div>
            <div><dt>Cách khắc phục thực tế</dt><dd><?= nl2br(e($o['resolution'])) ?></dd></div>
            <div><dt>Kết quả / khuyến nghị sau sửa</dt><dd><?= $o['post_repair_advice'] ? nl2br(e($o['post_repair_advice'])) : 'Không có khuyến nghị thêm' ?></dd></div>
          </dl>
        </section>
      <?php endif; ?>

      <?php if($o['status']===OrderState::REPAIRING): ?>
        <form method="post" action="/technician/repair-report/submit" class="mini-card form-grid repair-report-form">
          <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <div class="full"><span class="section-kicker">HOÀN TẤT SỬA CHỮA</span><h3>Gửi biên bản kỹ thuật</h3><p class="muted">Thông tin này là hồ sơ thực tế để doanh nghiệp xác nhận và khách hàng xem sau khi hoàn thành.</p></div>
          <div class="full"><label for="actual-issue-<?= (int)$o['id'] ?>">Vấn đề thực tế phát hiện <span class="required-mark">*</span></label><p class="field-help">Mô tả nguyên nhân hoặc hư hỏng xác định sau khi kiểm tra.</p><textarea id="actual-issue-<?= (int)$o['id'] ?>" name="actual_issue" rows="4" maxlength="10000" required></textarea></div>
          <div class="full"><label for="resolution-<?= (int)$o['id'] ?>">Cách khắc phục thực tế <span class="required-mark">*</span></label><p class="field-help">Ghi rõ thao tác hoặc phương án đã thực hiện.</p><textarea id="resolution-<?= (int)$o['id'] ?>" name="resolution" rows="4" maxlength="10000" required></textarea></div>
          <div class="full"><label for="advice-<?= (int)$o['id'] ?>">Kết quả / khuyến nghị sau sửa <small>(tùy chọn)</small></label><p class="field-help">Tùy chọn — ví dụ lưu ý sử dụng, theo dõi hoặc bảo dưỡng.</p><textarea id="advice-<?= (int)$o['id'] ?>" name="post_repair_advice" rows="3" maxlength="10000"></textarea></div>
          <div class="full"><button class="btn repair-submit">Đã sửa xong – gửi doanh nghiệp xác nhận</button></div>
        </form>
      <?php elseif($o['status']===OrderState::WORK_DONE): ?>
        <div class="note work-done-note">Bạn đã sửa xong. Doanh nghiệp sẽ xác nhận kết quả và chi phí cuối; bạn không thể tự hoàn thành đơn toàn cục.</div>
        <form method="post" action="/technician/repair-report/update" class="mini-card form-grid repair-report-form">
          <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <div class="full"><h3><?= empty($o['repair_report_id']) ? 'Bổ sung biên bản kỹ thuật' : 'Chỉnh sửa biên bản kỹ thuật' ?></h3><p class="muted">Bạn có thể sửa nội dung trong khi doanh nghiệp đang xác nhận. Sau khi đơn rời trạng thái này, biên bản sẽ chỉ đọc.</p></div>
          <div class="full"><label for="update-actual-issue-<?= (int)$o['id'] ?>">Vấn đề thực tế phát hiện <span class="required-mark">*</span></label><textarea id="update-actual-issue-<?= (int)$o['id'] ?>" name="actual_issue" rows="4" maxlength="10000" required><?= e($o['actual_issue'] ?? '') ?></textarea></div>
          <div class="full"><label for="update-resolution-<?= (int)$o['id'] ?>">Cách khắc phục thực tế <span class="required-mark">*</span></label><textarea id="update-resolution-<?= (int)$o['id'] ?>" name="resolution" rows="4" maxlength="10000" required><?= e($o['resolution'] ?? '') ?></textarea></div>
          <div class="full"><label for="update-advice-<?= (int)$o['id'] ?>">Kết quả / khuyến nghị sau sửa <small>(tùy chọn)</small></label><textarea id="update-advice-<?= (int)$o['id'] ?>" name="post_repair_advice" rows="3" maxlength="10000"><?= e($o['post_repair_advice'] ?? '') ?></textarea></div>
          <div class="full"><button class="btn repair-submit"><?= empty($o['repair_report_id']) ? 'Lưu biên bản kỹ thuật' : 'Lưu nội dung chỉnh sửa' ?></button></div>
        </form>
      <?php elseif($o['allowed_statuses']): ?>
        <form method="post" action="/technician/status" class="mini-card form-grid progress-form">
          <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="return_view" value="<?= e($workView) ?>">
          <div class="full"><h3>Cập nhật bước tiếp theo</h3><p class="muted">Chỉ các trạng thái tiến về phía trước được hệ thống cho phép mới xuất hiện.</p></div>
          <div><label>Trạng thái mới</label><select name="status" required><?php foreach($o['allowed_statuses'] as $status): ?><option value="<?= e($status) ?>"><?= e(status_label($status)) ?></option><?php endforeach; ?></select></div>
          <div><label>Ghi chú</label><input name="note" placeholder="Ví dụ: đang trên đường đến khách hàng"></div>
          <div class="full"><button class="btn">Lưu cập nhật</button></div>
        </form>
      <?php else: ?>
        <div class="note success-note"><b><?= e(status_label($o['status'])) ?></b> · Không còn thao tác tiến trình cho đơn này.</div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>
