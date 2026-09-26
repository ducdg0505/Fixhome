<?php $unknownMode = !empty($diagnosisPreview); ?>
<section class="container section booking-page" data-reveal>
  <div class="page-head page-head-split">
    <div><span class="section-kicker">ĐẶT DỊCH VỤ</span><h1>Tạo yêu cầu sửa chữa</h1><p>Chọn đúng nhóm dịch vụ trước, hoặc dùng chế độ “Không rõ lỗi” nếu bạn chưa xác định được vấn đề.</p></div>
    <div class="booking-progress"><span class="active">1. Chọn dịch vụ</span><span>2. Điền thông tin</span><span>3. Nhận báo giá</span></div>
  </div>

  <form method="post" action="/customer/book" enctype="multipart/form-data" class="booking-shell" id="bookingForm">
    <?= csrf_field() ?>
    <section class="card booking-section">
      <div class="booking-section-head"><div><small>BƯỚC 1</small><h2>Bạn muốn đặt theo cách nào?</h2></div></div>
      <div class="mode-switch mode-cards">
        <label><input type="radio" name="mode" value="popular" <?= $unknownMode?'':'checked' ?> data-mode><span><b>Chọn dịch vụ phổ biến</b><small>Phù hợp khi bạn đã biết cần vệ sinh, sửa hoặc lắp đặt gì.</small></span></label>
        <label><input type="radio" name="mode" value="unknown" <?= $unknownMode?'checked':'' ?> data-mode><span><b>Không rõ lỗi</b><small>Gửi mô tả và ảnh để FixHome phân loại sơ bộ trước khi chuyển doanh nghiệp.</small></span></label>
      </div>

      <div data-popular>
        <div class="category-picker" aria-label="Chọn nhóm dịch vụ">
          <?php foreach($categories as $index => $c): if($c['name']==='Không rõ lỗi') continue; ?>
            <button type="button" class="category-button <?= $index===0?'active':'' ?>" aria-pressed="<?= $index===0?'true':'false' ?>" data-category-button="<?= (int)$c['id'] ?>"><span><?= e($c['icon']) ?></span><?= e($c['name']) ?></button>
          <?php endforeach; ?>
        </div>

        <div class="service-choice-grid" data-service-grid>
          <?php foreach($services as $s): if($s['category_name']==='Không rõ lỗi') continue; ?>
            <label class="service-choice service-check" data-service-card data-category-id="<?= (int)$s['category_id'] ?>">
              <input type="checkbox" name="service_ids[]" value="<?= (int)$s['id'] ?>" data-category="<?= (int)$s['category_id'] ?>" data-min="<?= (int)$s['min_price'] ?>" data-max="<?= (int)$s['max_price'] ?>" data-service-name="<?= e($s['name']) ?>">
              <span class="service-choice-check">✓</span>
              <span class="service-choice-body"><small><?= e($s['icon'].' '.$s['category_name']) ?></small><b><?= e($s['name']) ?></b><em><?= e($s['description']) ?></em><?php if(!empty($s['common_issues'])): ?><span class="issue-tags"><?php foreach($s['common_issues'] as $issue): ?><span><?= e($issue) ?></span><?php endforeach; ?></span><?php endif; ?><strong><?= vnd($s['min_price']) ?> – <?= vnd($s['max_price']) ?><small>/<?= e($s['unit']) ?></small></strong></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="estimate estimate-panel" data-estimate><b>Chưa chọn dịch vụ.</b><span>Chọn một hoặc nhiều hạng mục trong cùng nhóm.</span></div>
      </div>

      <div data-unknown hidden class="unknown-box">
        <div class="info-steps"><span><b>1</b>Mô tả hiện tượng</span><span><b>2</b>Thêm ảnh nếu có</span><span><b>3</b>FixHome phân loại nhóm phù hợp</span></div>
        <div class="alert info">Phân loại sơ bộ chỉ hỗ trợ chuyển yêu cầu đúng nhóm dịch vụ; kỹ thuật viên vẫn kiểm tra thực tế trước khi sửa.</div>
        <label>Ảnh thiết bị <small class="muted">JPG/PNG/WEBP, tối đa 3MB</small></label>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
      </div>
    </section>

    <section class="card booking-section">
      <div class="booking-section-head"><div><small>BƯỚC 2</small><h2>Thông tin yêu cầu và lịch hẹn</h2><p>Doanh nghiệp chỉ nhận thông tin liên hệ đầy đủ sau khi bạn chọn báo giá của họ.</p></div></div>
      <div class="form-grid">
        <div class="full"><label>Mô tả hiện tượng / yêu cầu</label><textarea name="description" rows="4" placeholder="Ví dụ: máy lạnh không lạnh từ tối qua, có chảy nước ở dàn lạnh..."><?= e($formValues['description'] ?? '') ?></textarea></div>
        <div class="full diagnosis-preview-action" data-unknown-preview <?= $unknownMode?'':'hidden' ?>>
          <button class="btn ghost dark diagnosis-preview-button" type="submit" formaction="/customer/diagnosis/preview" formmethod="post" formnovalidate>Phân tích sơ bộ trước khi gửi</button>
          <?php if(!empty($diagnosisPreview)): ?><div class="diagnosis-preview" role="status"><div><small>THIẾT BỊ</small><b><?= e($diagnosisPreview['device_type']) ?></b></div><div><small>NHÓM PHÙ HỢP</small><b><?= e($diagnosisPreview['category']) ?></b></div><div><small>NHÓM LỖI</small><b><?= e($diagnosisPreview['issue_group']) ?></b></div><div><small>RỦI RO</small><b><?= e($diagnosisPreview['risk_level']) ?></b></div><p><?= e($diagnosisPreview['safety_note']) ?></p></div><?php endif; ?>
        </div>
        <div class="full booking-contact-flow">
          <?php if($savedAddresses): ?>
            <section class="booking-contact-panel booking-saved-panel" aria-labelledby="booking-saved-title">
              <div class="booking-subsection-head">
                <span class="booking-subsection-step" aria-hidden="true">1</span>
                <span class="booking-subsection-icon" aria-hidden="true">⌖</span>
                <div><small>TIỆN ÍCH ĐIỀN NHANH</small><h3 id="booking-saved-title">Chọn nhanh từ sổ địa chỉ</h3><p>Chạm vào một địa chỉ để điền nhanh. Bạn vẫn có thể chỉnh sửa ở phần bên dưới.</p></div>
              </div>
              <div class="booking-address-options" role="group" aria-label="Chọn địa chỉ đã lưu để điền nhanh">
                <?php foreach($savedAddresses as $saved): ?>
                  <button type="button" class="saved-address-option" data-saved-address data-address="<?= e($saved['address']) ?>">
                    <span class="saved-address-pin" aria-hidden="true">⌖</span>
                    <span class="saved-address-copy"><span class="saved-address-title"><?= e($saved['label']) ?><?php if((int)$saved['is_default']===1): ?><span class="saved-address-default">Mặc định</span><?php endif; ?></span><small><?= e($saved['address']) ?></small></span>
                    <span class="saved-address-action" aria-hidden="true">Chọn <b>›</b></span>
                    <span class="saved-address-applied" aria-hidden="true">✓ Đã điền</span>
                  </button>
                <?php endforeach; ?>
              </div>
            </section>
          <?php endif; ?>

          <section class="booking-contact-panel booking-final-panel" aria-labelledby="booking-final-title">
            <div class="booking-subsection-head">
              <span class="booking-subsection-step" aria-hidden="true"><?= $savedAddresses ? '2' : '1' ?></span>
              <span class="booking-subsection-icon" aria-hidden="true">✎</span>
              <div><small>THÔNG TIN CHÍNH THỨC CỦA ĐƠN</small><h3 id="booking-final-title">Xác nhận thông tin liên hệ cho đơn này</h3><p>Hãy kiểm tra và chỉnh sửa nếu cần. Nội dung bên dưới là thông tin cuối cùng được lưu vào đơn mới, không cập nhật hồ sơ hay sổ địa chỉ.</p></div>
            </div>
            <div class="booking-final-fields">
              <div><label for="booking-name">Tên người liên hệ</label><input id="booking-name" name="name" maxlength="150" value="<?= e($formValues['name'] ?? '') ?>" required autocomplete="name"></div>
              <div><label for="booking-phone">Số điện thoại liên hệ</label><input id="booking-phone" type="tel" inputmode="tel" name="phone" maxlength="30" value="<?= e($formValues['phone'] ?? '') ?>" required autocomplete="tel" placeholder="0901234567 hoặc +84901234567"></div>
              <div><label for="booking-email">Email liên hệ <small class="muted">(có thể để trống)</small></label><input id="booking-email" type="email" name="email" maxlength="180" value="<?= e($formValues['email'] ?? '') ?>" autocomplete="email"></div>
              <div><label for="booking-address">Địa chỉ sửa chữa</label><textarea id="booking-address" name="address" maxlength="255" rows="2" required placeholder="Số nhà, đường, phường/xã, quận/huyện" autocomplete="street-address" data-booking-address><?= e($formValues['address'] ?? '') ?></textarea></div>
              <div><label>Ngày hẹn</label><input type="date" name="scheduled_date" min="<?= date('Y-m-d') ?>" value="<?= e($formValues['scheduled_date'] ?? date('Y-m-d',strtotime('+1 day'))) ?>" required></div>
              <div><label>Giờ hẹn</label><input type="time" name="scheduled_time" value="<?= e($formValues['scheduled_time'] ?? '09:00') ?>" required></div>
            </div>
          </section>
        </div>
      </div>
    </section>

    <div class="booking-submit-bar"><div><b>Tiếp theo: doanh nghiệp phù hợp sẽ nhận lời mời báo giá.</b><span>Bạn chưa chọn doanh nghiệp ở bước này.</span></div><button class="btn">Gửi yêu cầu dịch vụ</button></div>
  </form>
</section>
