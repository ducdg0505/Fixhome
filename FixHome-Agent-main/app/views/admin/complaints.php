<?php $complaintViews = ['all'=>'Tất cả','unresolved'=>'Chưa đóng','open'=>'Mới','in_progress'=>'Đang xử lý','responded'=>'Đã phản hồi','closed'=>'Đã đóng']; ?>
<section class="container section">
  <div class="page-head page-head-split"><div><span class="section-kicker">HỖ TRỢ</span><h1>Khiếu nại khách hàng</h1><p>Theo dõi và ghi nhận xử lý khiếu nại.</p></div><span class="filter-result"><?= (int)$pagination['total'] ?> khiếu nại</span></div>
  <nav class="order-status-tabs role-work-tabs" aria-label="Lọc khiếu nại">
    <?php foreach($complaintViews as $value=>$label): ?><a href="/admin/complaints<?= $value==='all' ? '' : '?view='.rawurlencode($value) ?>" class="<?= $complaintView===$value ? 'active' : '' ?>" <?= $complaintView===$value ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
  </nav>
  <?php if(!$complaints): ?><div class="empty">Không có khiếu nại trong nhóm này.</div><?php endif; ?>
  <?php foreach($complaints as $c): ?>
    <article class="order-card">
      <div class="order-head"><div><small><?= e($c['order_code'].' · '.$c['customer_name']) ?></small><h2><?= e($c['subject']) ?></h2></div><span class="pill <?= status_class($c['status']) ?>"><?= e(status_label($c['status'])) ?></span></div>
      <div class="note"><?= nl2br(e($c['detail'])) ?></div>
      <form method="post" action="/admin/complaints" class="mini-card form-grid"><?= csrf_field() ?><input type="hidden" name="complaint_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="return_view" value="<?= e($complaintView) ?>"><div><label>Trạng thái</label><select name="status"><?php foreach(['open','in_progress','responded','closed'] as $st): ?><option value="<?= e($st) ?>" <?= $c['status']===$st?'selected':'' ?>><?= e(status_label($st)) ?></option><?php endforeach; ?></select></div><div class="full"><label>Ghi chú Admin</label><textarea name="admin_note" rows="3"><?= e($c['admin_note']) ?></textarea></div><div class="full"><button class="btn">Lưu xử lý</button></div></form>
    </article>
  <?php endforeach; ?>
  <?= pager('admin/complaints', $pagination) ?>
</section>
