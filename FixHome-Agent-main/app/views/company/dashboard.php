<section class="container section" data-reveal>
  <div class="page-head page-head-split">
    <div><span class="section-kicker">DOANH NGHIỆP ĐỐI TÁC</span><h1><?= e($company['name']) ?></h1><p>Nhận cơ hội phù hợp, gửi báo giá, phân công kỹ thuật viên và theo dõi công việc đã được khách chọn.</p></div>
    <div class="actions-row"><a class="btn" href="/company/orders">Xử lý cơ hội & công việc</a><a class="btn ghost dark" href="/company/technicians">Kỹ thuật viên</a></div>
  </div>
  <div class="metrics dashboard-metrics">
    <a class="metric metric-link" href="/company/orders?group=opportunities"><span>Cơ hội mới</span><b><?= (int)($stats['opportunities']??0) ?></b><small>Cần báo giá hoặc điều chỉnh →</small></a>
    <a class="metric metric-link" href="/company/orders?group=waiting_customer"><span>Chờ khách quyết định</span><b><?= (int)($stats['waiting_customer']??0) ?></b><small>Báo giá đang chờ lựa chọn →</small></a>
    <a class="metric metric-link" href="/company/orders?group=active"><span>Đang thực hiện</span><b><?= (int)($stats['active']??0) ?></b><small>Công việc doanh nghiệp đã được chọn →</small></a>
    <a class="metric metric-link" href="/company/orders?group=confirmation"><span>Chờ chốt kết quả</span><b><?= (int)($stats['awaiting_confirmation']??0) ?></b><small>Xác nhận kết quả và chi phí →</small></a>
    <a class="metric metric-link" href="/company/technicians?status=active"><span>Kỹ thuật viên hoạt động</span><b><?= (int)($stats['techs']??0) ?></b><small>Mở đúng danh sách nhân sự →</small></a>
    <a class="metric metric-link" href="/company/revenue"><span>Doanh thu hoàn thành</span><b><?= vnd($stats['revenue']??0) ?></b><small>Dữ liệu riêng của doanh nghiệp →</small></a>
  </div>
  <div class="two-col dashboard-guides">
    <article class="card"><span class="section-kicker">VIỆC CẦN LÀM</span><h2>Ưu tiên cơ hội mới</h2><p>Kiểm tra mô tả, lịch hẹn và khoảng giá tham khảo rồi phản hồi bằng báo giá. Việc từ chối một cơ hội không hủy đơn của khách.</p><a class="text-link" href="/company/orders">Mở danh sách cơ hội →</a></article>
    <article class="card"><span class="section-kicker">SAU KHI ĐƯỢC CHỌN</span><h2>Phân công đúng người</h2><p>Thông tin liên hệ khách chỉ mở cho doanh nghiệp thắng. Hãy phân công kỹ thuật viên nội bộ phù hợp và theo dõi tiến trình.</p><a class="text-link" href="/company/technicians">Quản lý kỹ thuật viên →</a></article>
  </div>
</section>
