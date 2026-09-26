<section class="container section account-page" data-reveal>
  <div class="page-head"><span class="section-kicker">TÀI KHOẢN</span><h1>Hồ sơ và bảo mật</h1><p>Cập nhật thông tin liên hệ hiện tại mà không thay đổi danh tính tài khoản hoặc dữ liệu của các đơn cũ.</p></div>
  <?php if(!empty($account['must_change_password'])): ?><div class="alert warning mandatory-password-note" role="alert"><b>Bắt buộc đổi mật khẩu.</b> Đây là mật khẩu tạm thời được cấp khi tạo tài khoản. Bạn chỉ có thể sử dụng các chức năng khác sau khi đổi mật khẩu thành công.</div><?php endif; ?>

  <div class="profile-card">
    <div class="profile-avatar" aria-hidden="true">👤</div>
    <div><h2><?= e($account['name']) ?></h2><span class="pill info"><?= e(role_label($account['role'])) ?></span><p><?= e($account['email']) ?><br><?= e($account['phone']) ?></p></div>
  </div>

  <div class="account-grid">
    <form method="post" action="/account/profile" class="card account-profile-form">
      <?= csrf_field() ?>
      <div><span class="section-kicker">TÀI KHOẢN CÁ NHÂN</span><h2>Thông tin của bạn</h2><p class="muted">Tên và số điện thoại hiện tại được dùng cho liên hệ mới. Email đăng nhập chưa hỗ trợ tự thay đổi.</p></div>
      <label for="account-name">Họ tên / tên hiển thị</label>
      <input id="account-name" name="name" maxlength="150" autocomplete="name" value="<?= e($account['name']) ?>" required>
      <label for="account-phone">Số điện thoại cá nhân</label>
      <input id="account-phone" type="tel" inputmode="tel" name="phone" maxlength="30" autocomplete="tel" value="<?= e($account['phone']) ?>" required>
      <label for="account-email">Email đăng nhập</label>
      <input id="account-email" type="email" value="<?= e($account['email']) ?>" readonly aria-describedby="account-email-note">
      <small id="account-email-note" class="muted">Email là định danh đăng nhập và chỉ được hiển thị trong giai đoạn này.</small>
      <button class="btn">Lưu tài khoản cá nhân</button>
    </form>

    <?php if($account['role']==='company' && $companyProfile): ?>
    <form method="post" action="/account/company" class="card company-profile-form">
      <?= csrf_field() ?>
      <div><span class="section-kicker">HỒ SƠ DOANH NGHIỆP</span><h2>Thông tin vận hành</h2><p class="muted">Hồ sơ doanh nghiệp tách biệt với tài khoản cá nhân. Tên pháp lý và mã số thuế chỉ đọc.</p></div>
      <label for="company-legal-name">Tên pháp lý</label>
      <input id="company-legal-name" value="<?= e($companyProfile['name']) ?>" readonly>
      <label for="company-tax-code">Mã số thuế</label>
      <input id="company-tax-code" value="<?= e($companyProfile['tax_code']) ?>" readonly>
      <label for="company-representative">Người đại diện</label>
      <input id="company-representative" name="representative" maxlength="150" value="<?= e($companyProfile['representative']) ?>" required>
      <label for="company-phone">Hotline / điện thoại</label>
      <input id="company-phone" type="tel" inputmode="tel" name="phone" maxlength="30" autocomplete="tel" value="<?= e($companyProfile['phone']) ?>" required>
      <label for="company-email">Email doanh nghiệp</label>
      <input id="company-email" type="email" name="email" maxlength="180" value="<?= e($companyProfile['email']) ?>" required>
      <label for="company-address">Địa chỉ doanh nghiệp</label>
      <textarea id="company-address" name="address" maxlength="255" rows="3" required><?= e($companyProfile['address']) ?></textarea>
      <button class="btn">Lưu hồ sơ doanh nghiệp</button>
    </form>
    <?php endif; ?>

    <?php if($account['role']==='technician'): ?>
    <section class="card technician-work-context" aria-labelledby="technician-work-context-title">
      <div><span class="section-kicker">THÔNG TIN CÔNG VIỆC</span><h2 id="technician-work-context-title">Thông tin công việc</h2><p class="muted">Thông tin trực thuộc do hệ thống và doanh nghiệp quản lý.</p></div>
      <dl class="work-context-list">
        <div><dt>Vai trò</dt><dd><?= e(role_label($account['role'])) ?></dd></div>
        <div><dt>Doanh nghiệp trực thuộc</dt><dd><?= e($technicianCompanyName ?: 'Chưa có doanh nghiệp trực thuộc') ?></dd></div>
      </dl>
    </section>
    <?php endif; ?>
  </div>

  <?php if($account['role']==='customer'): ?>
  <section class="card address-book-section" aria-labelledby="saved-addresses-title">
    <div class="address-book-head"><div><span class="section-kicker">SỔ ĐỊA CHỈ</span><h2 id="saved-addresses-title">Địa chỉ đã lưu</h2><p class="muted">Lưu các địa chỉ thường dùng để điền nhanh khi đặt dịch vụ. Bạn vẫn có thể sửa địa chỉ cho từng đơn.</p></div><span class="pill info"><?= count($savedAddresses) ?> địa chỉ</span></div>
    <div class="address-book-layout">
      <form method="post" action="/account/addresses/add" class="address-create-form">
        <?= csrf_field() ?>
        <h3>Thêm địa chỉ</h3>
        <label for="new-address-label">Nhãn địa chỉ</label>
        <input id="new-address-label" name="label" maxlength="<?= CustomerAddressService::LABEL_MAX_LENGTH ?>" placeholder="Nhà, Công ty..." required>
        <label for="new-address-value">Địa chỉ</label>
        <textarea id="new-address-value" name="address" maxlength="<?= CustomerAddressService::ADDRESS_MAX_LENGTH ?>" rows="3" placeholder="Số nhà, đường, phường/xã, quận/huyện" required></textarea>
        <label class="check-option address-default-option"><input type="checkbox" name="is_default" value="1"><span>Đặt làm địa chỉ mặc định</span></label>
        <button class="btn">Lưu địa chỉ</button>
      </form>

      <div class="saved-address-list">
        <?php if(!$savedAddresses): ?><div class="empty address-empty"><b>Chưa có địa chỉ đã lưu.</b><span>Địa chỉ đầu tiên bạn thêm sẽ tự động trở thành mặc định.</span></div><?php endif; ?>
        <?php foreach($savedAddresses as $address): ?>
        <article class="saved-address-card <?= (int)$address['is_default']===1?'is-default':'' ?>">
          <div class="saved-address-heading"><div><h3><?= e($address['label']) ?></h3><?php if((int)$address['is_default']===1): ?><span class="pill success">Mặc định</span><?php endif; ?></div><p><?= e($address['address']) ?></p></div>
          <div class="saved-address-actions">
            <?php if((int)$address['is_default']!==1): ?><form method="post" action="/account/addresses/default"><?= csrf_field() ?><input type="hidden" name="address_id" value="<?= (int)$address['id'] ?>"><button class="btn small ghost dark">Đặt mặc định</button></form><?php endif; ?>
            <details class="address-edit-disclosure">
              <summary class="btn small ghost dark">Chỉnh sửa</summary>
              <form method="post" action="/account/addresses/edit" class="address-edit-form">
                <?= csrf_field() ?>
                <input type="hidden" name="address_id" value="<?= (int)$address['id'] ?>">
                <label for="address-label-<?= (int)$address['id'] ?>">Nhãn địa chỉ</label>
                <input id="address-label-<?= (int)$address['id'] ?>" name="label" maxlength="<?= CustomerAddressService::LABEL_MAX_LENGTH ?>" value="<?= e($address['label']) ?>" required>
                <label for="address-value-<?= (int)$address['id'] ?>">Địa chỉ</label>
                <textarea id="address-value-<?= (int)$address['id'] ?>" name="address" maxlength="<?= CustomerAddressService::ADDRESS_MAX_LENGTH ?>" rows="3" required><?= e($address['address']) ?></textarea>
                <div class="address-form-actions"><button class="btn small">Lưu thay đổi</button><button type="button" class="btn small ghost dark" data-disclosure-cancel>Hủy</button></div>
              </form>
            </details>
            <details class="address-delete-disclosure">
              <summary class="btn small danger">Xóa</summary>
              <form method="post" action="/account/addresses/delete" class="address-delete-confirmation">
                <?= csrf_field() ?>
                <input type="hidden" name="address_id" value="<?= (int)$address['id'] ?>">
                <p><strong>Xóa địa chỉ này?</strong><span>Hành động này không thể hoàn tác.</span></p>
                <div class="address-form-actions"><button class="btn small danger">Xác nhận xóa</button><button type="button" class="btn small ghost dark" data-disclosure-cancel>Không xóa</button></div>
              </form>
            </details>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <div class="card security-card"><div><span class="section-kicker">BẢO MẬT</span><h2>Đổi mật khẩu</h2><p class="muted"><?= e(PasswordPolicy::HELP_TEXT) ?></p></div><form method="post" action="/account/password"><?= csrf_field() ?><label for="current-password">Mật khẩu hiện tại</label><span class="password-field"><input id="current-password" type="password" name="old_password" autocomplete="current-password" required><?= password_visibility_button() ?></span><label for="new-password">Mật khẩu mới</label><span class="password-field"><input id="new-password" type="password" name="new_password" minlength="12" maxlength="72" autocomplete="new-password" required><?= password_visibility_button() ?></span><label for="new-password-confirm">Nhập lại mật khẩu mới</label><span class="password-field"><input id="new-password-confirm" type="password" name="new_password2" minlength="12" maxlength="72" autocomplete="new-password" required><?= password_visibility_button() ?></span><button class="btn">Đổi mật khẩu</button></form></div>

  <?php if($account['role']==='customer' && (int)$account['is_test']===0 && $account['status']==='active'): ?>
  <section class="card account-danger-zone" aria-labelledby="account-danger-title">
    <div><span class="section-kicker">VÙNG NGUY HIỂM</span><h2 id="account-danger-title">Xóa tài khoản</h2><p>Thông tin đăng nhập, hồ sơ, địa chỉ đã lưu và ảnh đơn hàng sẽ bị xóa hoặc ẩn danh. Hồ sơ giao dịch cần thiết có thể vẫn được giữ lại dưới dạng ẩn danh.</p><p>Email cũ có thể dùng để tạo tài khoản mới sau này, nhưng tài khoản mới sẽ không nhận lại lịch sử cũ. Hành động này không thể hoàn tác.</p></div>
    <button type="button" class="btn danger-btn" data-dialog-open="customer-delete-dialog">Xóa tài khoản</button>
  </section>
  <?php endif; ?>
</section>

<?php if($account['role']==='customer' && (int)$account['is_test']===0 && $account['status']==='active'): ?>
<dialog id="customer-delete-dialog" class="sensitive-dialog" aria-labelledby="customer-delete-dialog-title">
  <form method="post" action="/account/delete" class="sensitive-dialog-panel">
    <?= csrf_field() ?>
    <div class="sensitive-dialog-icon" aria-hidden="true">!</div>
    <div class="sensitive-dialog-copy"><span class="section-kicker">XÁC NHẬN XÓA</span><h2 id="customer-delete-dialog-title">Xóa tài khoản?</h2><p>Hồ sơ cá nhân và ảnh đơn hàng sẽ bị gỡ bỏ; lịch sử nghiệp vụ cần thiết sẽ được giữ lại ở dạng ẩn danh.</p><p class="dialog-target"><b>Tài khoản:</b> <?= e($account['email']) ?></p></div>
    <div><label for="delete-account-password">Mật khẩu hiện tại</label><input id="delete-account-password" type="password" name="current_password" maxlength="72" autocomplete="current-password" required data-dialog-password></div>
    <div class="sensitive-dialog-actions"><button type="button" class="btn ghost dark" data-dialog-cancel>Quay lại</button><button type="submit" class="btn danger-btn">Xóa tài khoản</button></div>
  </form>
</dialog>
<?php endif; ?>
