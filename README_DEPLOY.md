# FixHome Deploy v5

Bản này giữ giao diện Streamlit và logic nghiệp vụ của MVP, nhưng thay file `db.json` bằng database có transaction thông qua SQLAlchemy. Môi trường Docker Compose dùng PostgreSQL 16 + FixHome + Caddy reverse proxy/HTTPS.

## 1. Kiến trúc

Internet -> `fixhome.id.vn` -> Caddy :80/:443 -> Streamlit :8501 -> PostgreSQL :5432

- Caddy tự xin và gia hạn TLS khi DNS đã trỏ đúng về VPS.
- PostgreSQL nằm trong private Docker network, không publish cổng 5432 ra Internet.
- Ảnh upload nằm trong Docker volume `uploads_data`.
- Database nằm trong volume `postgres_data`.
- Password mới dùng PBKDF2-HMAC-SHA256 600.000 iterations; có compatibility với hash demo cũ.
- Mật khẩu tạm thời doanh nghiệp chỉ hiển thị một lần; không lưu plaintext.

## 2. Chạy local không Docker

```bash
python -m venv .venv
# Windows
.venv\Scripts\activate
# Linux/macOS
source .venv/bin/activate

pip install -r requirements.txt
streamlit run app.py
```

Nếu không có `DATABASE_URL`, hệ thống tự dùng SQLite tại `data/fixhome.db` cho dev local.

## 3. Deploy lên VPS

VPS nên dùng Ubuntu 24.04 LTS, tối thiểu khoảng 2 vCPU / 2 GB RAM cho MVP không bật model vision local.

```bash
git clone <repo-cua-ban> fixhome
cd fixhome
cp .env.example .env
nano .env
```

Đổi `POSTGRES_PASSWORD` và đồng thời cập nhật password tương ứng trong `DATABASE_URL`.

Sau đó:

```bash
docker compose up -d --build
docker compose ps
docker compose logs -f app
```

Kiểm tra nội bộ:

```bash
curl -I http://127.0.0.1
```

## 4. DNS tên miền fixhome.id.vn

Tại nơi quản lý DNS của tên miền:

- A record: host `@` -> Public IPv4 của VPS
- A record: host `www` -> Public IPv4 của VPS (hoặc CNAME `www` -> `fixhome.id.vn`)

Sau khi DNS propagate, Caddy sẽ tự xin HTTPS certificate. Phải mở firewall TCP 80 và 443.

Ví dụ UFW:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

Không mở PostgreSQL 5432 ra Internet.

## 5. Import db.json cũ

Đặt file JSON cũ vào server rồi chạy:

```bash
docker compose cp db.json app:/tmp/db.json
docker compose exec app python migrate_json_to_db.py /tmp/db.json
```

Lưu ý: import sẽ thay dữ liệu hiện có trong database.

## 6. Backup database

```bash
docker compose exec -T db pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB" > fixhome_backup.sql
```

Restore:

```bash
cat fixhome_backup.sql | docker compose exec -T db psql -U "$POSTGRES_USER" "$POSTGRES_DB"
```

Nên tạo backup tự động hằng ngày và lưu thêm một bản ngoài VPS.

## 7. Production checklist

- Thay toàn bộ tài khoản demo và reset password admin.
- Không commit `.env` lên Git.
- Bật backup PostgreSQL tự động.
- Giới hạn kích thước file upload và validate MIME type.
- Khi dữ liệu ảnh tăng, chuyển uploads sang S3/R2/MinIO.
- Thêm email/OTP reset password thay cho bàn giao password thủ công.
- Thêm audit log cho admin, doanh nghiệp và kỹ thuật viên.
- Thêm rate limiting/WAF nếu mở công khai.
- Với traffic lớn, tách backend FastAPI và frontend riêng; PostgreSQL hiện tại vẫn giữ được.

## 8. Database layer của v5

Để không phải rewrite ngay gần 1.000 dòng UI, v5 dùng bảng entity records trên PostgreSQL và persistence theo từng entity. `save_db()` so sánh snapshot đầu phiên với dữ liệu hiện tại và chỉ upsert record thay đổi, thay vì ghi đè toàn bộ file JSON. Đây là bước chuyển phù hợp cho MVP deploy thật.

Khi sản phẩm bước qua MVP, nên migration sang các bảng typed hoàn toàn: `users`, `companies`, `partner_applications`, `orders`, `order_services`, `order_timeline`, `complaints`, `notifications`, `reviews`, `service_catalog`.
