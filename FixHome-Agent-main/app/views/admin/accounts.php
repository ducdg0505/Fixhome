<?php
$statusLabels = ['active'=>'Đang hoạt động','suspended'=>'Tạm ngưng','banned'=>'Bị cấm','deleted'=>'Đã xóa'];
$previewCounts = $purgePreview['counts'] ?? [];
?>
<section class="container section admin-accounts-page" data-reveal>
  <div class="page-head page-head-split">
    <div><span class="section-kicker">BẢO TRÌ TÀI KHOẢN</span><h1>Khách hàng</h1><p>Quản lý dấu thử nghiệm và trạng thái truy cập. Tài khoản khách hàng thật không thể bị xóa cứng từ đây.</p></div>
    <a class="btn ghost dark" href="/admin">Về tổng quan</a>
  </div>

  <form method="get" action="/admin/accounts" class="card filter-bar account-filter-bar">
    <div><label for="account-search">Tìm khách hàng</label><input id="account-search" type="search" name="q" maxlength="180" value="<?= e($filters['q']) ?>" placeholder="Tên, email hoặc số điện thoại"></div>
    <div><label for="account-kind">Loại tài khoản</label><select id="account-kind" name="kind"><?php foreach(['all'=>'Tất cả','test'=>'Thử nghiệm','real'=>'Khách hàng thật'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $filters['kind']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div><label for="account-status">Trạng thái</label><select id="account-status" name="status"><?php foreach(['all'=>'Tất cả']+$statusLabels as $value=>$label): ?><option value="<?= e($value) ?>" <?= $filters['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <button class="btn">Lọc danh sách</button>
    <a class="btn ghost dark" href="/admin/accounts">Xóa bộ lọc</a>
  </form>

  <?php if($purgePreview): ?>
  <section class="card purge-preview-card" aria-labelledby="purge-preview-title">
    <div class="card-heading"><div><span class="section-kicker">XEM TRƯỚC KHI XÓA</span><h2 id="purge-preview-title"><?= e($purgePreview['target']['name']) ?> <small>#<?= (int)$purgePreview['target_user_id'] ?></small></h2><p><?= e($purgePreview['target']['email']) ?></p></div><span class="pill warning">Tài khoản thử</span></div>
    <dl class="purge-count-grid">
      <div><dt>Đơn</dt><dd><?= (int)($previewCounts['orders']??0) ?></dd></div>
      <div><dt>Địa chỉ / hồ sơ</dt><dd><?= (int)($previewCounts['addresses']??0)+(int)($previewCounts['profile_versions']??0) ?></dd></div>
      <div><dt>Đánh giá / khiếu nại</dt><dd><?= (int)($previewCounts['reviews']??0)+(int)($previewCounts['technician_reviews']??0)+(int)($previewCounts['complaints']??0) ?></dd></div>
      <div><dt>Thông báo</dt><dd><?= (int)($previewCounts['notifications']??0) ?></dd></div>
      <div><dt>Tham chiếu ảnh</dt><dd><?= (int)($previewCounts['uploaded_image_references']??0) ?></dd></div>
    </dl>
    <p class="alert warning">Xóa thử nghiệm sẽ xóa toàn bộ dữ liệu liên quan được liệt kê. Hành động không thể hoàn tác.</p>
    <div class="actions-row"><button type="button" class="btn danger-btn" data-account-action-open data-action-url="/admin/accounts/purge" data-target-id="<?= (int)$purgePreview['target_user_id'] ?>" data-target="<?= e($purgePreview['target']['name'] . ' (#' . $purgePreview['target_user_id'] . ')') ?>" data-title="Xóa tài khoản thử?" data-description="Toàn bộ dữ liệu thử trong bản xem trước sẽ bị xóa vĩnh viễn." data-confirm-label="Xóa tài khoản thử">Xóa tài khoản thử</button><a class="btn ghost dark" href="/admin/accounts">Bỏ bản xem trước</a></div>
  </section>
  <?php endif; ?>

  <div class="card account-table-card">
    <div class="card-heading"><div><h2>Danh sách khách hàng</h2><p class="muted"><?= (int)$pagination['total'] ?> tài khoản phù hợp</p></div></div>
    <?php if(!$customers): ?><div class="empty">Không có tài khoản phù hợp.</div><?php else: ?>
    <div class="table-wrap"><table class="account-maintenance-table"><thead><tr><th>ID</th><th>Khách hàng</th><th>Phân loại</th><th>Trạng thái</th><th>Đơn</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead><tbody>
      <?php foreach($customers as $customer): ?>
      <tr>
        <td><b>#<?= (int)$customer['id'] ?></b></td>
        <td><b><?= e($customer['name']) ?></b><small><?= e($customer['email']) ?></small><small><?= e($customer['phone'] ?: '—') ?></small></td>
        <td><span class="pill <?= (int)$customer['is_test']===1?'warning':'info' ?>"><?= (int)$customer['is_test']===1?'Test':'Thật' ?></span></td>
        <td><span class="pill <?= e(status_class($customer['status'])) ?>"><?= e($statusLabels[$customer['status']] ?? status_label($customer['status'])) ?></span></td>
        <td><?= (int)$customer['order_count'] ?></td>
        <td><?= dt($customer['created_at']) ?></td>
        <td>
          <?php if($customer['status']==='deleted'): ?><span class="muted">Chỉ xem</span><?php else: ?>
          <div class="account-row-actions">
            <?php if((int)$customer['is_test']===1): ?>
              <button type="button" class="btn small ghost dark" data-account-action-open data-action-url="/admin/accounts/test-marker" data-field-name="marker" data-field-value="real" data-target-id="<?= (int)$customer['id'] ?>" data-target="<?= e($customer['name'] . ' (#' . $customer['id'] . ')') ?>" data-title="Gỡ dấu tài khoản thử?" data-description="Tài khoản sẽ trở lại phân loại khách hàng thật và không còn dùng được luồng xóa thử." data-confirm-label="Gỡ dấu Test">Gỡ dấu Test</button>
              <?php $previewUrl='/admin/accounts?' . http_build_query(['q'=>$filters['q'],'kind'=>$filters['kind'],'status'=>$filters['status'],'preview'=>(int)$customer['id']]); ?><a class="btn small danger" href="<?= e($previewUrl) ?>">Xem trước khi xóa</a>
            <?php else: ?>
              <button type="button" class="btn small ghost dark" data-account-action-open data-action-url="/admin/accounts/test-marker" data-field-name="marker" data-field-value="test" data-target-id="<?= (int)$customer['id'] ?>" data-target="<?= e($customer['name'] . ' (#' . $customer['id'] . ')') ?>" data-title="Đánh dấu tài khoản thử?" data-description="Chỉ dùng thao tác này cho tài khoản smoke/test được xác nhận rõ ràng." data-confirm-label="Đánh dấu Test">Đánh dấu Test</button>
              <?php if($customer['status']==='active'): ?><button type="button" class="btn small danger" data-account-action-open data-action-url="/admin/accounts/status" data-field-name="status" data-field-value="suspended" data-requires-reason="true" data-target-id="<?= (int)$customer['id'] ?>" data-target="<?= e($customer['name'] . ' (#' . $customer['id'] . ')') ?>" data-title="Tạm ngưng khách hàng?" data-description="Khách hàng sẽ không thể đăng nhập cho đến khi được kích hoạt lại." data-confirm-label="Tạm ngưng">Tạm ngưng</button><?php endif; ?>
              <?php if(in_array($customer['status'],['active','suspended'],true)): ?><button type="button" class="btn small danger" data-account-action-open data-action-url="/admin/accounts/status" data-field-name="status" data-field-value="banned" data-requires-reason="true" data-target-id="<?= (int)$customer['id'] ?>" data-target="<?= e($customer['name'] . ' (#' . $customer['id'] . ')') ?>" data-title="Cấm khách hàng?" data-description="Khách hàng sẽ bị chặn đăng nhập; email hiện tại vẫn được giữ và không thể đăng ký lại." data-confirm-label="Cấm tài khoản">Cấm</button><?php endif; ?>
              <?php if(in_array($customer['status'],['suspended','banned'],true)): ?><button type="button" class="btn small" data-account-action-open data-action-url="/admin/accounts/status" data-field-name="status" data-field-value="active" data-target-id="<?= (int)$customer['id'] ?>" data-target="<?= e($customer['name'] . ' (#' . $customer['id'] . ')') ?>" data-title="Kích hoạt lại khách hàng?" data-description="Khách hàng sẽ có thể đăng nhập lại bằng thông tin hiện tại." data-confirm-label="Kích hoạt lại">Kích hoạt lại</button><?php endif; ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?= pager('admin/accounts',$pagination) ?>
    <?php endif; ?>
  </div>
</section>

<dialog id="admin-account-action-dialog" class="sensitive-dialog" data-admin-account-dialog aria-labelledby="admin-account-dialog-title">
  <form method="post" class="sensitive-dialog-panel" data-admin-account-form>
    <?= csrf_field() ?>
    <input type="hidden" name="target_user_id" data-dialog-target-id>
    <input type="hidden" data-dialog-action-value disabled>
    <div class="sensitive-dialog-icon" aria-hidden="true">!</div>
    <div class="sensitive-dialog-copy"><span class="section-kicker">XÁC NHẬN QUẢN TRỊ</span><h2 id="admin-account-dialog-title" data-dialog-title>Xác nhận thao tác</h2><p data-dialog-description></p><p class="dialog-target"><b>Tài khoản:</b> <span data-dialog-target></span></p></div>
    <div data-dialog-reason-field hidden><label for="admin-account-reason">Lý do</label><textarea id="admin-account-reason" name="reason" maxlength="<?= AccountMaintenanceService::STATUS_REASON_MAX_LENGTH ?>" rows="3" disabled></textarea></div>
    <div><label for="admin-current-password">Mật khẩu Admin hiện tại</label><input id="admin-current-password" type="password" name="current_password" maxlength="72" autocomplete="current-password" required data-dialog-password></div>
    <div class="sensitive-dialog-actions"><button type="button" class="btn ghost dark" data-dialog-cancel>Quay lại</button><button type="submit" class="btn danger-btn" data-dialog-confirm>Xác nhận</button></div>
  </form>
</dialog>
