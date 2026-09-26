<?php $partnerStatuses = ['all'=>'Tất cả','pending_review'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối']; ?>
<section class="container section">
  <div class="page-head page-head-split"><div><span class="section-kicker">ĐỐI TÁC</span><h1>Duyệt doanh nghiệp đối tác</h1><p>Chỉ duyệt sau khi đã kiểm tra giấy phép, thông tin liên hệ và năng lực kỹ thuật.</p></div><span class="filter-result"><?= (int)$pagination['total'] ?> hồ sơ</span></div>
  <nav class="order-status-tabs role-work-tabs" aria-label="Lọc hồ sơ đối tác">
    <?php foreach($partnerStatuses as $value=>$label): ?><a href="/admin/partners<?= $value==='all' ? '' : '?status='.rawurlencode($value) ?>" class="<?= $status===$value ? 'active' : '' ?>" <?= $status===$value ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
  </nav>
  <?php if(!$apps): ?><div class="empty">Không có hồ sơ trong nhóm này.</div><?php endif; ?>
  <?php foreach($apps as $a): ?>
    <article class="order-card">
      <div class="order-head"><div><small><?= dt($a['submitted_at']) ?></small><h2><?= e($a['company_name']) ?></h2></div><span class="pill <?= status_class($a['status']) ?>"><?= e(status_label($a['status'])) ?></span></div>
      <div class="order-grid"><div><b>MST/ĐKKD</b><p><?= e($a['tax_code']) ?></p></div><div><b>Đại diện</b><p><?= e($a['representative']) ?></p></div><div><b>Liên hệ</b><p><?= e($a['phone']) ?><br><?= e($a['email']) ?></p></div><div><b>Địa chỉ</b><p><?= e($a['address']) ?></p></div></div>
      <p><b>Nhóm dịch vụ:</b> <?php foreach($a['categories'] as $c): ?><?= e($c['name']) ?>; <?php endforeach; ?></p>
      <div class="note"><?= nl2br(e($a['legal_note'])) ?></div>
      <?php if($a['status']==='pending_review'): ?>
        <form method="post" action="/admin/partners/review" class="mini-card"><?= csrf_field() ?><input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>"><label>Ghi chú kiểm tra</label><textarea name="review_note" rows="3"></textarea><div class="actions-row"><button class="btn" name="action" value="approve">Duyệt và cấp tài khoản</button><button class="btn danger-btn" name="action" value="reject">Từ chối</button></div></form>
      <?php else: ?><div class="note"><b>Ghi chú duyệt:</b> <?= e($a['review_note']) ?></div><?php endif; ?>
    </article>
  <?php endforeach; ?>
  <?= pager('admin/partners', $pagination) ?>
</section>
