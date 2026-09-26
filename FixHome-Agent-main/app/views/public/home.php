<?php
$servicesByCategory = [];
foreach ($services as $service) {
    $categoryId = (int)$service['category_id'];
    $servicesByCategory[$categoryId][] = $service;
}
$firstCategoryId = isset($categories[0]['id']) ? (int)$categories[0]['id'] : 0;
?>
<div class="guest-home-canvas">
  <section class="home-hero python-parity-hero" data-reveal>
    <div class="hero-grid">
      <div class="hero-copy">
        <span class="eyebrow">FIXHOME CẦN THƠ</span>
        <h1>Dịch vụ sửa chữa gia dụng minh bạch cho Cần Thơ</h1>
        <p>FixHome kết nối khách hàng với doanh nghiệp sửa chữa đã được kiểm tra pháp lý, hỗ trợ phân loại sơ bộ, báo giá rõ ràng và theo dõi tiến trình trên một hệ thống.</p>
        <div class="hero-actions"><a class="btn light" href="/register">Bắt đầu đặt dịch vụ</a><a class="btn ghost light" href="/login">Đăng nhập</a><a class="btn ghost light" href="#services">Xem bảng giá</a></div>
        <div class="badges"><span>B2B2C</span><span>Doanh nghiệp xác minh</span><span>Báo giá minh bạch</span><span>Theo dõi tiến trình</span></div>
      </div>
      <div class="hero-art-shell" aria-label="Minh họa quy trình FixHome">
        <img class="hero-art" src="/assets/images/fixhome_hero_workflow_3d.webp" alt="Quy trình FixHome: gửi yêu cầu, nhận báo giá và theo dõi sửa chữa" fetchpriority="high">
        <div class="hero-step-chip hero-step-1"><span class="hero-step-no">1</span><div><strong>Gửi yêu cầu</strong><small>Chọn dịch vụ hoặc mô tả lỗi</small></div></div>
        <div class="hero-step-chip hero-step-2"><span class="hero-step-no">2</span><div><strong>Nhận báo giá</strong><small>So sánh minh bạch, dễ quyết định</small></div></div>
        <div class="hero-step-chip hero-step-3"><span class="hero-step-no">3</span><div><strong>Theo dõi sửa chữa</strong><small>Cập nhật tiến trình rõ ràng</small></div></div>
      </div>
    </div>
  </section>

  <section class="home-role-grid" data-reveal aria-label="Vai trò trên FixHome">
    <article class="home-role-card">
      <span class="home-role-badge">Dễ sử dụng</span>
      <h2>Khách hàng</h2>
      <p>Đặt dịch vụ, gửi ảnh lỗi thiết bị, nhận báo giá và theo dõi tiến trình sửa chữa.</p>
    </article>
    <article class="home-role-card">
      <span class="home-role-badge">Có pháp nhân</span>
      <h2>Doanh nghiệp đối tác</h2>
      <p>Nhận yêu cầu phù hợp, báo giá, phân công kỹ thuật viên và quản lý doanh thu của riêng doanh nghiệp.</p>
    </article>
    <article class="home-role-card">
      <span class="home-role-badge">Trung gian</span>
      <h2>FixHome Admin</h2>
      <p>Kiểm tra hồ sơ đối tác, cấp tài khoản doanh nghiệp và thống kê số lượt kết nối. Không quản lý doanh thu của đối tác.</p>
    </article>
  </section>

  <section id="services" class="home-price-section" data-reveal>
    <h2>Bảng giá tham khảo</h2>
    <div class="home-price-tabs" role="tablist" aria-label="Nhóm dịch vụ">
      <?php foreach($categories as $index => $category): ?>
        <button id="price-tab-<?= (int)$category['id'] ?>" type="button" class="<?= $index === 0 ? 'active' : '' ?>" role="tab" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="price-panel-<?= (int)$category['id'] ?>" tabindex="<?= $index === 0 ? '0' : '-1' ?>" data-home-price-category="<?= (int)$category['id'] ?>"><?= e(($category['icon'] ?? '') . ' ' . $category['name']) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="home-price-panels">
      <?php foreach($categories as $index => $category): ?>
        <?php $categoryId = (int)$category['id']; $categoryServices = array_slice($servicesByCategory[$categoryId] ?? [], 0, 4); ?>
        <div id="price-panel-<?= $categoryId ?>" class="home-price-panel" role="tabpanel" aria-labelledby="price-tab-<?= $categoryId ?>" data-home-price-panel="<?= $categoryId ?>">
          <?php if($categoryServices): ?>
            <?php foreach($categoryServices as $service): ?>
              <article class="home-price-card">
                <div>
                  <strong><?= e($service['name']) ?></strong>
                  <p><?= e($service['description']) ?></p>
                  <?php if(!empty($service['common_issues'])): ?><div class="issue-tags"><?php foreach($service['common_issues'] as $issue): ?><span><?= e($issue) ?></span><?php endforeach; ?></div><?php endif; ?>
                  <span class="category-tag"><?= e($category['name']) ?></span>
                </div>
                <b><?= vnd($service['min_price']) ?> - <?= vnd($service['max_price']) ?>/<?= e($service['unit']) ?></b>
              </article>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty">Chưa có dịch vụ trong nhóm này.</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section id="partner" class="home-partner-section" data-reveal>
    <div class="section-title">
      <span class="section-kicker">ĐỐI TÁC</span>
      <h2>Đăng ký trở thành doanh nghiệp đối tác</h2>
      <p>Doanh nghiệp gửi hồ sơ. Sau khi FixHome kiểm tra pháp lý và phê duyệt, hệ thống sẽ cấp tài khoản đối tác.</p>
    </div>
    <form class="card form-grid" method="post" action="/partner/apply">
      <?= csrf_field() ?>
      <div><label>Tên doanh nghiệp *</label><input name="company_name" required autocomplete="organization"></div>
      <div><label>Mã số thuế / đăng ký kinh doanh *</label><input name="tax_code" required></div>
      <div><label>Người đại diện *</label><input name="representative" required autocomplete="name"></div>
      <div><label>Số điện thoại *</label><input type="tel" inputmode="tel" name="phone" required autocomplete="tel" placeholder="0901234567 hoặc +84901234567"></div>
      <div><label>Email nhận tài khoản *</label><input type="email" name="email" required autocomplete="email"></div>
      <div><label>Địa chỉ hoạt động *</label><input name="address" required autocomplete="street-address"></div>
      <div class="full"><label>Nhóm dịch vụ *</label><div class="check-grid"><?php foreach($categories as $c): ?><label class="check"><input type="checkbox" name="categories[]" value="<?= (int)$c['id'] ?>"> <?= e(($c['icon'] ?? '') . ' ' . $c['name']) ?></label><?php endforeach; ?></div></div>
      <div class="full"><label>Mô tả hồ sơ pháp lý và năng lực</label><textarea name="legal_note" rows="4" placeholder="Giấy đăng ký kinh doanh, đội kỹ thuật, khu vực phục vụ, chính sách bảo hành..."></textarea></div>
      <div class="full"><button class="btn">Gửi hồ sơ đối tác</button></div>
    </form>
  </section>

  <section id="process" class="home-showcase-section" data-reveal>
    <div class="section-title">
      <span class="section-kicker">HỆ SINH THÁI FIXHOME TẠI CẦN THƠ</span>
      <h2>Kết nối khách hàng, doanh nghiệp và kỹ thuật viên trên một luồng chung</h2>
    </div>
    <figure class="home-showcase-visual"><img src="/assets/images/fixhome_home_bottom_showcase.webp" alt="Hệ sinh thái FixHome tại Cần Thơ" loading="lazy"><figcaption>Minh họa định hướng sản phẩm web-first của FixHome.</figcaption></figure>
    <div class="home-showcase-copy">
      <article><h3>FixHome giải quyết bài toán gì?</h3><p>Khách hàng cần biết gọi ai, mức giá tham khảo và tiến trình sửa chữa. Doanh nghiệp cần nơi nhận yêu cầu phù hợp và quản lý kỹ thuật viên rõ ràng hơn.</p></article>
      <article><h3>Quy trình vận hành trên web</h3><p>Khách gửi yêu cầu → hệ thống phân phối → doanh nghiệp gửi báo giá → khách chọn → kỹ thuật viên xử lý và cập nhật tiến trình.</p></article>
    </div>
  </section>

  <section id="about" class="home-about-section" data-reveal>
    <div><span class="section-kicker">VỀ FIXHOME</span><h2>Một luồng rõ ràng từ yêu cầu đến hoàn thành</h2><p>FixHome giúp khách hàng tìm doanh nghiệp phù hợp, so sánh báo giá và theo dõi sửa chữa. FixHome là nền tảng kết nối; doanh nghiệp được chọn chịu trách nhiệm khảo sát, báo giá cuối và thực hiện dịch vụ.</p></div>
    <div class="home-contact-card"><h3>Cần hỗ trợ?</h3><a href="tel:0949161719"><b>Điện thoại</b><span>094 916 17 19</span></a><a href="mailto:duongtta.cs191525@gmail.com"><b>Email</b><span>duongtta.cs191525@gmail.com</span></a><a href="https://www.facebook.com/profile.php?id=61591633523951" target="_blank" rel="noopener noreferrer"><b>Facebook</b><span>Mở trang FixHome ↗</span></a></div>
  </section>
</div>
