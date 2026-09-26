<?php
$statusTabs = [
    'all' => 'Tất cả',
    'waiting' => 'Chờ báo giá',
    'action' => 'Cần chọn báo giá',
    'active' => 'Đang thực hiện',
    'work_done' => 'Chờ xác nhận',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy',
];
$statusUrl = static function (string $status) use ($filters): string {
    $query = [
        'status' => $status,
        'q' => $filters['q'],
        'category' => $filters['category'],
        'period' => $filters['period'],
    ];
    return '/customer/orders?' . http_build_query(array_filter($query, static fn($value): bool => $value !== '' && $value !== 0 && $value !== 'all'));
};
?>
<section class="container section" data-reveal>
  <div class="page-head page-head-split">
    <div><span class="section-kicker">ĐƠN CỦA TÔI</span><h1>Theo dõi sửa chữa</h1><p>Xem báo giá, doanh nghiệp được chọn, kỹ thuật viên và toàn bộ tiến trình.</p></div>
    <a class="btn" href="/customer/book">+ Đặt dịch vụ mới</a>
  </div>

  <nav class="order-status-tabs" aria-label="Lọc đơn theo trạng thái">
    <?php foreach($statusTabs as $value => $label): ?><a href="<?= e($statusUrl($value)) ?>" class="<?= $filters['status']===$value ? 'active' : '' ?>" <?= $filters['status']===$value ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
  </nav>

  <form class="filter-bar customer-order-filters" method="get" action="/customer/orders" data-order-filter>
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <label class="filter-search"><span>Tìm kiếm</span><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Mã đơn, dịch vụ, doanh nghiệp..."></label>
    <label><span>Nhóm dịch vụ</span><select name="category"><option value="0">Tất cả nhóm</option><?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)$filters['category']===(int)$category['id']?'selected':'' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
    <label><span>Thời gian</span><select name="period"><?php foreach(['all'=>'Tất cả','today'=>'Hôm nay','7'=>'7 ngày qua','30'=>'30 ngày qua'] as $value=>$label): ?><option value="<?= e((string)$value) ?>" <?= $filters['period']===(string)$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <div class="filter-actions"><button class="btn">Lọc đơn</button><a class="btn ghost dark" href="/customer/orders">Đặt lại</a></div>
    <div class="filter-result"><?= (int)$pagination['total'] ?> đơn phù hợp</div>
  </form>

  <?php if(!$orders): ?><div class="empty"><b>Không có đơn phù hợp.</b><p>Thử đổi bộ lọc hoặc tạo yêu cầu dịch vụ mới.</p><a class="btn small" href="/customer/book">Đặt dịch vụ</a></div><?php endif; ?>

  <div data-order-list>
  <?php foreach($orders as $o):
    $serviceNames = implode(', ', array_map(static fn(array $sv): string => (string)$sv['name_snapshot'], $o['services']));
    $isQuoteAction = $o['status'] === OrderState::QUOTED;
    $nextAction = match ($o['status']) {
        OrderState::PENDING_DISTRIBUTION, OrderState::WAITING_QUOTE => 'FixHome đang chờ doanh nghiệp gửi báo giá.',
        OrderState::QUOTED => 'Cần bạn xem và chọn một báo giá.',
        OrderState::QUOTE_ACCEPTED => 'Doanh nghiệp đang chuẩn bị phân công kỹ thuật viên.',
        OrderState::TECH_ASSIGNED => 'Kỹ thuật viên đã được phân công và sẽ xác nhận công việc.',
        OrderState::TECH_ACCEPTED => 'Kỹ thuật viên đã nhận công việc.',
        OrderState::ON_THE_WAY => 'Kỹ thuật viên đang di chuyển đến địa chỉ hẹn.',
        OrderState::INSPECTING => 'Kỹ thuật viên đang kiểm tra hiện trạng.',
        OrderState::REPAIRING => 'Kỹ thuật viên đang sửa chữa.',
        OrderState::WORK_DONE => 'Doanh nghiệp cần xác nhận kết quả và chi phí cuối.',
        OrderState::COMPLETED => 'Đơn đã hoàn thành.',
        OrderState::CANCELLED => 'Đơn đã hủy.',
        default => status_label($o['status']),
    };
  ?>
    <article class="order-card ux-order-card <?= $isQuoteAction ? 'needs-action' : '' ?>" data-order-card>
      <div class="order-head">
        <div><span class="category-tag"><?= e($o['category_name']) ?></span><h2><?= e($o['order_code']) ?></h2><small>Tạo lúc <?= dt($o['created_at']) ?></small></div>
        <span class="pill <?= status_class($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
      </div>

      <div class="order-highlight-grid compact-order-summary">
        <div><small>Dịch vụ</small><b><?= e($serviceNames ?: $o['category_name']) ?></b></div>
        <div><small>Lịch hẹn</small><b><?= dt($o['scheduled_at']) ?></b></div>
        <div><small>Doanh nghiệp</small><b><?= e($o['company_name'] ?: 'Chưa chọn') ?></b></div>
        <div><small>Kỹ thuật viên của doanh nghiệp</small><b><?= e($o['technician_name'] ?: 'Chưa phân công') ?></b><?php if($o['technician_name']): ?><span class="reputation-line"><?= e(FeedbackService::formatReputation($o['technician_reputation'])) ?></span><?php endif; ?></div>
      </div>

      <div class="next-action <?= $isQuoteAction ? 'urgent' : '' ?>"><span><?= $isQuoteAction ? 'CẦN THAO TÁC' : 'BƯỚC TIẾP THEO' ?></span><b><?= e($nextAction) ?></b><?php if($isQuoteAction): ?><a href="#order-quotes-<?= (int)$o['id'] ?>">Xem báo giá ↓</a><?php endif; ?></div>

      <details class="order-details" <?= $isQuoteAction ? 'open' : '' ?>>
        <summary><span><?= $isQuoteAction ? 'Báo giá và chi tiết đang mở' : 'Xem chi tiết đơn và thao tác' ?></span><small>Mô tả, địa chỉ, báo giá, tiến trình</small></summary>
        <div class="order-details-body">
          <div class="order-detail-strip"><div><b>Dịch vụ</b><p><?php foreach($o['services'] as $sv): ?><span class="service-inline-tag"><?= e($sv['name_snapshot']) ?></span><?php endforeach; ?></p></div><div><b>Liên hệ đã xác nhận</b><p><strong><?= e($o['customer_name']) ?></strong><br><?= e($o['customer_phone']) ?><?php if($o['customer_email']): ?><br><?= e($o['customer_email']) ?><?php endif; ?></p></div><div><b>Địa chỉ sửa chữa đã xác nhận</b><p><?= e($o['address']) ?></p></div></div>

          <?php if($o['description']): ?><div class="note"><b>Mô tả:</b> <?= nl2br(e($o['description'])) ?></div><?php endif; ?>
          <?php if($o['diagnosis_summary']): ?><div class="note ai"><b>Phân loại sơ bộ:</b> <?= e($o['diagnosis_summary']) ?><?php if($o['diagnosis_risk']): ?><br><b>Mức rủi ro:</b> <?= e($o['diagnosis_risk']) ?><?php endif; ?></div><?php endif; ?>
          <?php if($o['status']===OrderState::WORK_DONE): ?><div class="note work-done-note"><b>Kỹ thuật viên đã sửa xong.</b> Doanh nghiệp đang kiểm tra kết quả và xác nhận chi phí cuối trước khi đơn chuyển sang hoàn thành.</div><?php endif; ?>
          <?php if($o['status']===OrderState::COMPLETED && !empty($o['repair_report_id'])): ?>
            <section class="repair-report-card customer-repair-report" aria-label="Kết quả kỹ thuật">
              <div class="repair-report-heading"><div><span class="section-kicker">KẾT QUẢ KỸ THUẬT</span><h3>Công việc đã thực hiện</h3></div></div>
              <dl class="repair-report-fields">
                <div><dt>Vấn đề thực tế phát hiện</dt><dd><?= nl2br(e($o['actual_issue'])) ?></dd></div>
                <div><dt>Cách khắc phục thực tế</dt><dd><?= nl2br(e($o['resolution'])) ?></dd></div>
                <?php if($o['post_repair_advice']): ?><div><dt>Kết quả / khuyến nghị sau sửa</dt><dd><?= nl2br(e($o['post_repair_advice'])) ?></dd></div><?php endif; ?>
              </dl>
            </section>
          <?php endif; ?>
          <?php if($o['image_name']): ?><p><a class="text-link" target="_blank" rel="noopener noreferrer" href="/media/order-image?order_id=<?= (int)$o['id'] ?>">↗ Xem ảnh đã gửi</a></p><?php endif; ?>

          <?php if($o['quotes']): ?>
            <section class="quote-section" id="order-quotes-<?= (int)$o['id'] ?>">
              <div class="card-heading"><div><h3>Báo giá doanh nghiệp</h3><p class="muted"><?= count($o['quotes']) ?> phiên bản báo giá. Phiên bản hiện tại được đánh dấu rõ ràng.</p></div></div>
              <div class="quote-grid">
              <?php foreach($o['quotes'] as $q): ?>
                <article class="quote-card <?= $q['status']==='submitted' ? 'actionable' : '' ?> <?= (int)($o['selected_quote_id'] ?? 0)===(int)$q['id'] ? 'selected' : '' ?>">
                  <div class="quote-card-head"><div><small>DOANH NGHIỆP</small><h4><?= e($q['company_name']) ?></h4><span class="reputation-line"><?= e(FeedbackService::formatReputation($q['company_reputation'])) ?></span></div><span class="pill <?= status_class($q['status']) ?>"><?= e(status_label($q['status'])) ?></span></div>
                  <div class="quote-price"><?= vnd($q['min_price']) ?> – <?= vnd($q['max_price']) ?></div>
                  <p><b>Lần báo giá:</b> <?= (int)$q['revision'] ?> <?= !empty($q['is_current']) ? '· Hiện tại' : '· Lịch sử' ?></p>
                  <p><b>Có thể đến:</b> <?= e($q['estimated_arrival'] ?: 'Doanh nghiệp sẽ liên hệ') ?></p>
                  <p class="muted"><?= e($q['note'] ?: 'Không có ghi chú') ?></p>
                  <?php if(!empty($q['is_current']) && $q['status']==='submitted' && $o['company_id']===null && $o['status']===OrderState::QUOTED): ?>
                    <form method="post" action="/customer/quote" class="quote-actions">
                      <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="quote_id" value="<?= (int)$q['id'] ?>">
                      <button class="btn full-btn" name="action" value="accept">Chọn báo giá này</button>
                      <button class="btn ghost dark full-btn" name="action" value="changes">Yêu cầu điều chỉnh</button>
                    </form>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
              </div>
            </section>
          <?php elseif(in_array($o['status'],[OrderState::PENDING_DISTRIBUTION,OrderState::WAITING_QUOTE],true)): ?>
            <div class="empty compact-empty"><b>Đang chờ doanh nghiệp báo giá</b><p>FixHome đã chuyển yêu cầu đến doanh nghiệp phù hợp. Báo giá sẽ xuất hiện tại đây.</p></div>
          <?php endif; ?>

          <details class="timeline">
            <summary><span>Lịch sử tiến trình</span><small><?= count($o['timeline']) ?> cập nhật</small></summary>
            <div class="timeline-list"><?php foreach($o['timeline'] as $t): ?><div class="timeline-item"><span><?= dt($t['created_at']) ?></span><b><?= e(status_label($t['to_status'] ?: $t['event_type'])) ?></b><p><?= e($t['note']) ?></p></div><?php endforeach; ?></div>
          </details>

          <div class="order-actions-footer">
            <?php if(!OrderState::isTerminal($o['status'])): ?>
              <details class="danger-disclosure"><summary>Hủy đơn</summary><form method="post" action="/customer/orders/cancel" class="mini-card spaced"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><label>Lý do hủy</label><textarea name="cancel_reason" rows="2" required></textarea><div class="disclosure-actions"><button class="btn danger-btn">Xác nhận hủy đơn</button><button type="button" class="btn ghost dark" data-disclosure-cancel>Không hủy đơn</button></div></form></details>
            <?php endif; ?>
          </div>

          <?php
          $missingCompanyReview = $o['status']===OrderState::COMPLETED && $o['final_price']!==null && !$o['company_review'];
          $missingTechnicianReview = $o['status']===OrderState::COMPLETED && $o['final_price']!==null && !empty($o['technician_id']) && !$o['technician_review'];
          ?>
          <?php if($missingCompanyReview || $missingTechnicianReview): ?>
            <form method="post" action="/customer/feedback" class="mini-card feedback-card">
              <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="kind" value="review">
              <div class="feedback-card-head"><span class="section-kicker">PHẢN HỒI SAU DỊCH VỤ</span><h3>Đánh giá đơn hàng</h3><p class="muted">Mỗi đánh giá được lưu riêng để phản ánh đúng trải nghiệm của bạn.</p></div>
              <?php if($missingCompanyReview): ?>
                <fieldset class="feedback-target"><legend>Doanh nghiệp <?= e($o['company_name']) ?></legend><p>Trải nghiệm tổng thể với doanh nghiệp</p><div class="star-rating" aria-label="Đánh giá doanh nghiệp từ 1 đến 5 sao"><?php for($rating=5;$rating>=1;$rating--): $ratingId='company-rating-'.$o['id'].'-'.$rating; ?><label for="<?= e($ratingId) ?>"><input id="<?= e($ratingId) ?>" type="radio" name="company_rating" value="<?= $rating ?>" required><span aria-hidden="true">★</span><span class="sr-only"><?= $rating ?> sao</span></label><?php endfor; ?></div><label for="company-comment-<?= (int)$o['id'] ?>">Nhận xét về doanh nghiệp <small>(tùy chọn)</small></label><textarea id="company-comment-<?= (int)$o['id'] ?>" name="company_comment" rows="3" maxlength="2000"></textarea></fieldset>
              <?php endif; ?>
              <?php if($missingTechnicianReview): ?>
                <fieldset class="feedback-target"><legend>Kỹ thuật viên <?= e($o['technician_name']) ?></legend><p>Trải nghiệm trực tiếp với kỹ thuật viên</p><div class="star-rating" aria-label="Đánh giá kỹ thuật viên từ 1 đến 5 sao"><?php for($rating=5;$rating>=1;$rating--): $ratingId='technician-rating-'.$o['id'].'-'.$rating; ?><label for="<?= e($ratingId) ?>"><input id="<?= e($ratingId) ?>" type="radio" name="technician_rating" value="<?= $rating ?>" required><span aria-hidden="true">★</span><span class="sr-only"><?= $rating ?> sao</span></label><?php endfor; ?></div><label for="technician-comment-<?= (int)$o['id'] ?>">Nhận xét về kỹ thuật viên <small>(tùy chọn)</small></label><textarea id="technician-comment-<?= (int)$o['id'] ?>" name="technician_comment" rows="3" maxlength="2000"></textarea></fieldset>
              <?php endif; ?>
              <button class="btn">Gửi đánh giá</button>
            </form>
          <?php endif; ?>
          <?php if($o['company_review'] || $o['technician_review']): ?><div class="feedback-recorded"><?php if($o['company_review']): ?><div class="note success-note">Đã đánh giá doanh nghiệp: <b><?= (int)$o['company_review']['rating'] ?>/5 sao</b><?php if($o['company_review']['comment']): ?> · <?= e($o['company_review']['comment']) ?><?php endif; ?></div><?php endif; ?><?php if($o['technician_review']): ?><div class="note success-note">Đã đánh giá kỹ thuật viên: <b><?= (int)$o['technician_review']['rating'] ?>/5 sao</b><?php if($o['technician_review']['comment']): ?> · <?= e($o['technician_review']['comment']) ?><?php endif; ?></div><?php endif; ?></div><?php endif; ?>
          <details class="complaint-disclosure"><summary>Gửi khiếu nại cho đơn này</summary><form method="post" action="/customer/feedback" class="mini-card spaced"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="kind" value="complaint"><label>Tiêu đề</label><input name="subject" required><label>Nội dung</label><textarea name="detail" rows="3" required></textarea><div class="disclosure-actions"><button class="btn ghost dark">Gửi khiếu nại</button><button type="button" class="btn ghost dark" data-disclosure-cancel>Đóng</button></div></form></details>
        </div>
      </details>
    </article>
  <?php endforeach; ?>
  </div>
  <?= pager('customer/orders', $pagination) ?>
</section>
