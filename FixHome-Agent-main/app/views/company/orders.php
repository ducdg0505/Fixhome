<section class="container section" data-reveal>
  <div class="page-head page-head-split">
    <div><span class="section-kicker">MARKETPLACE</span><h1>Cơ hội và công việc</h1><p>Báo giá cho lời mời của doanh nghiệp; thông tin liên hệ đầy đủ chỉ mở sau khi khách chọn.</p></div>
    <div class="section-counts"><span><b><?= $groupCounts['opportunities'] ?></b> cần phản hồi</span><span><b><?= $groupCounts['active'] ?></b> đang làm</span><span><b><?= $groupCounts['confirmation'] ?></b> chờ chốt</span></div>
  </div>

  <?php
  $companyGroupUrl = static function(string $group) use ($filters): string {
      $query = ['group'=>$group,'q'=>$filters['q'],'category'=>$filters['category']];
      return '/company/orders?' . http_build_query(array_filter($query, static fn($value): bool => $value !== '' && $value !== 0 && $value !== 'all'));
  };
  ?>
  <nav class="order-status-tabs role-work-tabs" aria-label="Lọc công việc doanh nghiệp">
    <a href="<?= e($companyGroupUrl('all')) ?>" class="<?= $filters['group']==='all' ? 'active' : '' ?>" <?= $filters['group']==='all' ? 'aria-current="page"' : '' ?>>Tất cả <span><?= array_sum($groupCounts) ?></span></a>
    <?php foreach($groups as $value=>$label): ?><a href="<?= e($companyGroupUrl($value)) ?>" class="<?= $filters['group']===$value ? 'active' : '' ?>" <?= $filters['group']===$value ? 'aria-current="page"' : '' ?>><?= e($label) ?> <span><?= $groupCounts[$value] ?></span></a><?php endforeach; ?>
  </nav>

  <form class="filter-bar company-filter" method="get" action="/company/orders">
    <input type="hidden" name="group" value="<?= e($filters['group']) ?>">
    <label class="filter-search"><span>Tìm kiếm</span><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Mã đơn, khách hàng, mô tả..."></label>
    <label><span>Nhóm dịch vụ</span><select name="category"><option value="0">Tất cả nhóm</option><?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)$filters['category']===(int)$category['id']?'selected':'' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
    <div class="filter-actions"><button class="btn">Lọc</button><a class="btn ghost dark" href="/company/orders">Đặt lại</a></div>
  </form>

  <?php foreach ([
    ['key'=>'opportunities','title' => 'Cơ hội mới', 'subtitle' => 'Cần gửi báo giá hoặc phản hồi yêu cầu điều chỉnh.', 'empty' => 'Chưa có cơ hội cần phản hồi.', 'orders' => $activeOpportunities, 'class' => 'open-opportunities'],
    ['key'=>'waiting_customer','title' => 'Chờ khách quyết định', 'subtitle' => 'Báo giá hiện tại đã gửi và đang chờ khách lựa chọn.', 'empty' => 'Không có báo giá đang chờ khách.', 'orders' => $waitingCustomer, 'class' => 'waiting-customer'],
    ['key'=>'active','title' => 'Đang thực hiện', 'subtitle' => 'Khách đã chọn doanh nghiệp; phân công và theo dõi kỹ thuật viên.', 'empty' => 'Chưa có công việc đang thực hiện.', 'orders' => $selectedActive, 'class' => 'selected-work'],
    ['key'=>'confirmation','title' => 'Chờ chốt kết quả', 'subtitle' => 'Kỹ thuật viên đã sửa xong; cần xác nhận kết quả và chi phí cuối.', 'empty' => 'Không có công việc chờ chốt.', 'orders' => $awaitingConfirmation, 'class' => 'awaiting-confirmation'],
    ['key'=>'closed','title' => 'Đã đóng', 'subtitle' => 'Phân biệt rõ công việc hoàn thành, đơn hủy, cơ hội không được chọn hoặc đã từ chối.', 'empty' => 'Chưa có công việc hoặc cơ hội đã đóng.', 'orders' => $closedOrders, 'class' => 'closed-work'],
  ] as $section): if($filters['group'] !== 'all' && $filters['group'] !== $section['key']) continue; ?>
    <section class="company-order-section <?= e($section['class']) ?>">
      <div class="section-heading-inline"><div><h2><?= e($section['title']) ?> <span><?= count($section['orders']) ?></span></h2><p><?= e($section['subtitle']) ?></p></div></div>
      <?php if(!$section['orders']): ?><div class="empty compact-empty"><?= e($section['empty']) ?></div><?php endif; ?>

      <?php foreach($section['orders'] as $o): ?>
      <article class="order-card ux-order-card">
        <div class="order-head">
          <div><span class="category-tag"><?= e($o['category_name']) ?></span><h3><?= e($o['order_code']) ?></h3><small>Lịch hẹn <?= dt($o['scheduled_at']) ?></small></div>
          <div class="status-stack"><span class="pill <?= status_class($o['request_status']) ?>"><?= e(status_label($o['request_status'])) ?></span><small><?= e(status_label($o['status'])) ?></small></div>
        </div>

        <?php if(!$o['contact_visible']): ?>
          <div class="privacy-banner"><b>🔒 Thông tin khách hàng đang được bảo vệ</b><span>Tên, điện thoại, email, địa chỉ và dữ liệu vận hành của doanh nghiệp thắng chỉ hiển thị sau khi khách chọn báo giá của bạn.</span></div>
        <?php else: ?>
          <div class="contact-panel"><div><small>KHÁCH HÀNG</small><b><?= e($o['customer_name']) ?></b><span><?= e($o['customer_phone']) ?> · <?= e($o['customer_email']) ?></span></div><div><small>ĐỊA CHỈ</small><b><?= e($o['address']) ?></b></div><div><small>KỸ THUẬT VIÊN</small><b><?= e($o['technician_name'] ?: 'Chưa phân công') ?></b></div></div>
        <?php endif; ?>

        <div class="order-detail-strip"><div><b>Dịch vụ</b><p><?php foreach($o['services'] as $sv): ?><span class="service-inline-tag"><?= e($sv['name_snapshot']) ?></span><?php endforeach; ?></p></div><div><b>Giá tham khảo</b><p><?= vnd($o['estimate_min']) ?> – <?= vnd($o['estimate_max']) ?></p></div></div>
        <?php if($o['description']): ?><div class="note"><b>Mô tả:</b> <?= nl2br(e($o['description'])) ?></div><?php endif; ?>
        <?php if($o['diagnosis_summary']): ?><div class="note ai"><b>Phân loại sơ bộ:</b> <?= e($o['diagnosis_summary']) ?></div><?php endif; ?>
        <?php if(!empty($o['image_visible'])): ?><p><a class="text-link" target="_blank" rel="noopener noreferrer" href="/media/order-image?order_id=<?= (int)$o['id'] ?>">↗ Xem ảnh thiết bị</a></p><?php endif; ?>

        <?php if($o['latest_quote']): ?>
          <div class="quote-summary"><div><small>BÁO GIÁ CỦA BẠN · LẦN <?= (int)$o['latest_quote']['revision'] ?></small><strong><?= vnd($o['latest_quote']['min_price']) ?> – <?= vnd($o['latest_quote']['max_price']) ?></strong></div><span class="pill <?= status_class($o['latest_quote']['status']) ?>"><?= e(status_label($o['latest_quote']['status'])) ?></span></div>
        <?php endif; ?>
        <?php if(count($o['quote_history']) > 1): ?><details class="quote-history"><summary>Lịch sử <?= count($o['quote_history']) ?> lần báo giá</summary><div><?php foreach($o['quote_history'] as $quote): ?><p><b>Lần <?= (int)$quote['revision'] ?>:</b> <?= vnd($quote['min_price']) ?> – <?= vnd($quote['max_price']) ?> · <?= e(status_label($quote['status'])) ?></p><?php endforeach; ?></div></details><?php endif; ?>

        <?php if(in_array($o['request_status'],['invited','viewed','quote_submitted'],true) && !$o['company_id'] && !OrderState::isTerminal($o['status'])): ?>
          <form method="post" action="/company/orders/quote" class="mini-card form-grid quote-form">
            <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
            <div class="full"><h3><?= $o['latest_quote'] ? 'Cập nhật báo giá' : 'Gửi báo giá' ?></h3><p class="muted">Giá có thể được điều chỉnh bằng revision mới; lịch sử báo giá cũ vẫn được giữ.</p></div>
            <div><label>Giá thấp nhất</label><input type="number" name="quote_min" min="0" step="50000" value="<?= (int)($o['latest_quote']['min_price'] ?? $o['estimate_min']) ?>" required></div>
            <div><label>Giá cao nhất</label><input type="number" name="quote_max" min="0" step="50000" value="<?= (int)($o['latest_quote']['max_price'] ?? $o['estimate_max']) ?>" required></div>
            <div><label>Thời gian có thể đến</label><input name="estimated_arrival" value="<?= e($o['latest_quote']['estimated_arrival'] ?? 'Trong 2-4 giờ') ?>"></div>
            <div class="full"><label>Ghi chú</label><textarea name="quote_note" rows="3"><?= e($o['latest_quote']['note'] ?? 'Báo giá chính xác sau khi kiểm tra thực tế.') ?></textarea></div>
            <div class="full"><button class="btn">Gửi báo giá cho khách</button></div>
          </form>
          <details class="danger-disclosure"><summary>Từ chối cơ hội này</summary><form method="post" action="/company/orders/decline" class="mini-card spaced"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><p class="muted">Đơn của khách vẫn tiếp tục với doanh nghiệp khác.</p><label>Lý do</label><textarea name="reason" rows="2"></textarea><div class="disclosure-actions"><button class="btn danger-btn">Từ chối cơ hội</button><button type="button" class="btn ghost dark" data-disclosure-cancel>Không từ chối</button></div></form></details>
        <?php endif; ?>

        <?php if($o['request_status']==='selected' && $o['status']===OrderState::QUOTE_ACCEPTED): ?>
          <?php $assignmentOptions = $technicianRecommendations[(int)$o['id']] ?? []; ?>
          <form method="post" action="/company/orders/assign" class="mini-card action-card">
            <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><div><h3>Phân công kỹ thuật viên</h3><p class="muted">Gợi ý ưu tiên năng lực, số việc đang xử lý, sau đó mới xét uy tín đủ mẫu và kinh nghiệm. Doanh nghiệp vẫn có thể chọn bất kỳ kỹ thuật viên đang hoạt động nào.</p></div>
            <?php if(!$assignmentOptions): ?><div class="empty compact-empty">Chưa có kỹ thuật viên đang hoạt động để nhận việc. <a href="/company/technicians">Quản lý đội ngũ</a></div><?php else: ?>
            <fieldset class="assignment-options"><legend>Chọn kỹ thuật viên</legend>
              <?php foreach([true=>'PHÙ HỢP',false=>'KHÁC'] as $recommended=>$groupLabel): $group = array_values(array_filter($assignmentOptions,static fn(array $option): bool => (bool)$option['recommended']===(bool)$recommended)); if(!$group) continue; ?>
              <div class="assignment-group"><b><?= e($groupLabel) ?></b><?php foreach($group as $index=>$option): $radioId='assign-'.$o['id'].'-'.$option['id']; ?><label class="assignment-option" for="<?= e($radioId) ?>"><input id="<?= e($radioId) ?>" type="radio" name="technician_id" value="<?= (int)$option['id'] ?>" required <?= $recommended && $index===0 ? 'checked' : '' ?>><span><strong><?= e($option['name']) ?></strong><small><?= e($option['match_label']) ?> · <?= (int)$option['workload'] ?> việc đang xử lý · <?= e($option['phone']) ?></small><small class="assignment-reputation"><?= e(FeedbackService::formatReputation($option)) ?> · <?= (int)$option['completed_job_count'] ?> công việc hoàn thành</small></span></label><?php endforeach; ?></div>
              <?php endforeach; ?>
            </fieldset>
            <button class="btn">Phân công kỹ thuật viên đã chọn</button>
            <?php endif; ?>
          </form>
        <?php endif; ?>

        <?php if(!empty($o['repair_report_visible'])): ?>
          <section class="repair-report-card company-repair-report" aria-label="Biên bản kỹ thuật">
            <div class="repair-report-heading"><div><span class="section-kicker">BIÊN BẢN KỸ THUẬT</span><h3>Kết quả sửa chữa thực tế</h3></div><small>Kỹ thuật viên: <?= e($o['repair_report_technician_name']) ?></small></div>
            <dl class="repair-report-fields">
              <div><dt>Vấn đề thực tế phát hiện</dt><dd><?= nl2br(e($o['actual_issue'])) ?></dd></div>
              <div><dt>Cách khắc phục thực tế</dt><dd><?= nl2br(e($o['resolution'])) ?></dd></div>
              <div><dt>Kết quả / khuyến nghị sau sửa</dt><dd><?= $o['post_repair_advice'] ? nl2br(e($o['post_repair_advice'])) : 'Không có khuyến nghị thêm' ?></dd></div>
            </dl>
          </section>
        <?php endif; ?>

        <?php if($o['request_status']==='selected' && $o['status']===OrderState::WORK_DONE): ?>
          <?php if(!empty($o['repair_report_visible'])): ?>
          <form method="post" action="/company/orders/complete" class="mini-card form-grid complete-card">
            <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><div class="full"><h3>Chốt hoàn thành</h3><p class="muted">Chỉ xác nhận khi công việc đã bàn giao cho khách.</p></div>
            <div><label>Chi phí thực tế cuối cùng</label><input type="number" name="final_price" min="0" step="50000" value="<?= (int)($o['final_price'] ?? $o['latest_quote']['min_price'] ?? 0) ?>" required></div>
            <div class="full"><label>Kết quả công việc</label><textarea name="complete_note" rows="2" required placeholder="Xác nhận hạng mục đã xử lý và kết quả bàn giao"></textarea></div>
            <div class="full"><button class="btn">Xác nhận hoàn thành</button></div>
          </form>
          <?php else: ?>
            <div class="note work-done-note"><b>Chờ kỹ thuật viên hoàn thiện biên bản kỹ thuật.</b> Doanh nghiệp chỉ có thể xác nhận chi phí và hoàn thành đơn sau khi biên bản hợp lệ được gửi.</div>
          <?php endif; ?>
        <?php endif; ?>
        <?php if($o['contact_visible'] && $o['status']===OrderState::COMPLETED): ?><div class="note success-note"><b>Đơn hoàn thành.</b> Chi phí cuối: <?= vnd($o['final_price'] ?? 0) ?></div><?php endif; ?>
      </article>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</section>
