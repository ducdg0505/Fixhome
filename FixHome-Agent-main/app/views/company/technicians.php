<section class="container section" data-reveal>
  <div class="page-head"><span class="section-kicker">NHÂN SỰ</span><h1>Quản lý kỹ thuật viên</h1><p>Quản lý trạng thái, năng lực xử lý và ghi chú chuyên môn. Lịch sử công việc luôn được giữ nguyên.</p></div>
  <nav class="order-status-tabs role-work-tabs" aria-label="Lọc kỹ thuật viên">
    <?php foreach(['all'=>'Tất cả','active'=>'Đang hoạt động','inactive'=>'Ngừng hoạt động'] as $value=>$label): ?><a href="/company/technicians<?= $value==='all' ? '' : '?status='.rawurlencode($value) ?>" class="<?= $status===$value ? 'active' : '' ?>" <?= $status===$value ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
  </nav>

  <div class="workforce-layout">
    <div class="workforce-list">
      <div class="section-heading-inline"><div><h2>Đội ngũ kỹ thuật</h2><p><?= count($technicians) ?> tài khoản trong bộ lọc hiện tại.</p></div></div>
      <?php if(!$technicians): ?><div class="empty">Chưa có kỹ thuật viên.</div><?php endif; ?>
      <?php foreach($technicians as $t): ?>
      <article class="card technician-management-card">
        <div class="technician-card-head">
          <div><h3><?= e($t['name']) ?></h3><p><?= e($t['email']) ?> · <?= e($t['phone']) ?></p></div>
          <span class="pill <?= $t['status']==='active' ? 'success' : 'info' ?>"><?= e(status_label($t['status'])) ?></span>
        </div>
        <div class="capability-summary">
          <b>Năng lực xử lý</b>
          <div class="capability-chips"><?php if(!$t['capabilities']): ?><span class="muted">Chưa khai báo năng lực</span><?php else: ?><?php foreach($t['capabilities'] as $capability): ?><span><?= e($capability['service_name']) ?></span><?php endforeach; ?><?php endif; ?></div>
          <?php if($t['skill_note']): ?><p><b>Ghi chú chuyên môn:</b> <?= e($t['skill_note']) ?></p><?php endif; ?>
        </div>
        <div class="technician-actions">
          <details class="capability-editor">
            <summary class="btn small ghost dark">Chỉnh năng lực</summary>
            <form method="post" action="/company/technicians/capabilities" class="capability-form">
              <?= csrf_field() ?><input type="hidden" name="technician_id" value="<?= (int)$t['id'] ?>">
              <fieldset><legend>Năng lực xử lý</legend><div class="capability-groups">
                <?php foreach($capabilityCatalog as $category): ?><section class="capability-group"><h4><?= e(($category['icon'] ? $category['icon'].' ' : '').$category['name']) ?></h4><?php foreach($category['services'] as $service): $inputId='edit-cap-'.$t['id'].'-'.$service['id']; ?><label class="check-option" for="<?= e($inputId) ?>"><input id="<?= e($inputId) ?>" type="checkbox" name="capability_ids[]" value="<?= (int)$service['id'] ?>" <?= in_array((int)$service['id'],$t['capability_ids'],true)?'checked':'' ?>><span><?= e($service['name']) ?></span></label><?php endforeach; ?></section><?php endforeach; ?>
              </div></fieldset>
              <label for="skill-note-<?= (int)$t['id'] ?>">Ghi chú chuyên môn (tùy chọn)</label>
              <textarea id="skill-note-<?= (int)$t['id'] ?>" name="skill_note" maxlength="255" rows="3"><?= e($t['skill_note']) ?></textarea>
              <div class="disclosure-actions"><button class="btn small">Lưu năng lực</button><button type="button" class="btn small ghost dark" data-disclosure-cancel>Hủy</button></div>
            </form>
          </details>
          <?php if(in_array($t['status'], ['active','inactive'], true)): ?>
          <form method="post" action="/company/technicians/status" class="inline-form technician-status-form">
            <?= csrf_field() ?><input type="hidden" name="technician_id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="status" value="<?= $t['status']==='active' ? 'inactive' : 'active' ?>">
            <button class="btn small <?= $t['status']==='active' ? 'ghost dark' : '' ?>"><?= $t['status']==='active' ? 'Ngừng hoạt động' : 'Kích hoạt lại' ?></button>
          </form>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <form method="post" action="/company/technicians" class="card create-technician-card">
      <?= csrf_field() ?>
      <span class="section-kicker">THÊM NHÂN SỰ</span><h2>Thêm kỹ thuật viên</h2>
      <div class="form-grid"><div><label for="new-tech-name">Họ tên</label><input id="new-tech-name" name="name" maxlength="150" autocomplete="name" required></div><div><label for="new-tech-email">Email đăng nhập</label><input id="new-tech-email" type="email" name="email" maxlength="180" autocomplete="email" required></div><div><label for="new-tech-phone">Số điện thoại</label><input id="new-tech-phone" type="tel" inputmode="tel" name="phone" maxlength="30" autocomplete="tel" placeholder="0901234567 hoặc +84901234567" required></div></div>
      <fieldset><legend>Năng lực xử lý <span aria-hidden="true">*</span></legend><p class="muted">Chọn ít nhất một dịch vụ. Có thể chọn nhiều năng lực ở nhiều nhóm.</p><div class="capability-groups">
        <?php foreach($capabilityCatalog as $category): ?><section class="capability-group"><h3><?= e(($category['icon'] ? $category['icon'].' ' : '').$category['name']) ?></h3><?php foreach($category['services'] as $service): $inputId='new-cap-'.$service['id']; ?><label class="check-option" for="<?= e($inputId) ?>"><input id="<?= e($inputId) ?>" type="checkbox" name="capability_ids[]" value="<?= (int)$service['id'] ?>"><span><?= e($service['name']) ?></span></label><?php endforeach; ?></section><?php endforeach; ?>
      </div></fieldset>
      <label for="new-tech-note">Ghi chú chuyên môn (tùy chọn)</label><textarea id="new-tech-note" name="skill_note" maxlength="255" rows="3"></textarea>
      <button class="btn">Tạo tài khoản kỹ thuật viên</button><p class="muted">Mật khẩu tạm thời được hiển thị một lần sau khi tạo.</p>
    </form>
  </div>
</section>
