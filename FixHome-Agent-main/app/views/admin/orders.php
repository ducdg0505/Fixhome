<section class="container section">
  <div class="page-head page-head-split"><div><span class="section-kicker">MARKETPLACE</span><h1>Đơn và phân phối</h1><p>Mời doanh nghiệp đã xác minh, đang hoạt động và phù hợp nhóm dịch vụ.</p></div><div class="filter-result"><?= (int)$pagination['total'] ?> đơn phù hợp</div></div>
  <?php
  $adminGroupUrl = static function(string $group) use ($filters): string {
      $query = ['group'=>$group,'q'=>$filters['q'],'status'=>$filters['status'],'category'=>$filters['category']];
      return '/admin/orders?' . http_build_query(array_filter($query, static fn($value): bool => $value !== '' && $value !== 0 && $value !== 'all'));
  };
  ?>
  <nav class="order-status-tabs role-work-tabs" aria-label="Lọc vận hành đơn">
    <a href="<?= e($adminGroupUrl('all')) ?>" class="<?= $filters['group']==='all' ? 'active' : '' ?>" <?= $filters['group']==='all' ? 'aria-current="page"' : '' ?>>Tất cả</a>
    <?php foreach($groups as $value=>$label): ?><a href="<?= e($adminGroupUrl($value)) ?>" class="<?= $filters['group']===$value ? 'active' : '' ?>" <?= $filters['group']===$value ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
  </nav>
  <form class="filter-bar admin-order-filter" method="get" action="/admin/orders">
    <input type="hidden" name="group" value="<?= e($filters['group']) ?>">
    <label class="filter-search"><span>Tìm kiếm</span><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Mã đơn, khách hàng, doanh nghiệp..."></label>
    <label><span>Trạng thái</span><select name="status"><option value="all">Tất cả</option><?php foreach($allowedStatuses as $status): if($status==='all')continue; ?><option value="<?= e($status) ?>" <?= $filters['status']===$status?'selected':'' ?>><?= e(status_label($status)) ?></option><?php endforeach; ?></select></label>
    <label><span>Nhóm dịch vụ</span><select name="category"><option value="0">Tất cả nhóm</option><?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)$filters['category']===(int)$category['id']?'selected':'' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
    <div class="filter-actions"><button class="btn">Lọc</button><a class="btn ghost dark" href="/admin/orders">Đặt lại</a></div>
  </form>
  <?php if(!$orders): ?><div class="empty">Không có đơn phù hợp bộ lọc.</div><?php endif; ?>
  <?php foreach($orders as $o): ?>
    <article class="order-card">
      <div class="order-head"><div><small><?= e($o['category_name']) ?></small><h2><?= e($o['order_code']) ?></h2></div><span class="pill <?= status_class($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></div>
      <div class="order-grid"><div><b>Khách hàng</b><p><?= e($o['customer_name']) ?> · <?= e($o['customer_phone']) ?></p></div><div><b>Doanh nghiệp được chọn</b><p><?= e($o['company_name']?:'Chưa chọn') ?></p></div><div><b>Địa chỉ</b><p><?= e($o['address']) ?></p></div><div><b>Lịch hẹn</b><p><?= dt($o['scheduled_at']) ?></p></div></div>
      <?php if(in_array($o['status'],['pending_distribution','waiting_quote','quoted'],true) && !$o['company_id']): ?><div class="split-actions"><form method="post" action="/admin/orders/assign" class="mini-card"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="mode" value="auto"><h3>Mời tất cả phù hợp</h3><p>Tạo lời mời cho mọi doanh nghiệp đủ điều kiện chưa được mời.</p><button class="btn">Mời doanh nghiệp</button></form><form method="post" action="/admin/orders/assign" class="mini-card"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="mode" value="manual"><h3>Mời thủ công</h3><select name="company_id" required><option value="">-- Chọn doanh nghiệp --</option><?php foreach($o['candidates'] as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?> · <?= e($c['rating']) ?>★</option><?php endforeach; ?></select><button class="btn ghost dark">Gửi lời mời</button></form></div><?php endif; ?>
    </article>
  <?php endforeach; ?>
  <?= pager('admin/orders', $pagination) ?>
</section>
