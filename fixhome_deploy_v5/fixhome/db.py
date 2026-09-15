from __future__ import annotations

import copy
import hashlib
import json
import os
import secrets
import string
import threading
from datetime import datetime, timedelta
from typing import Any

from sqlalchemy import DateTime, Integer, String, Text, UniqueConstraint, create_engine, delete, select
from sqlalchemy.orm import DeclarativeBase, Mapped, mapped_column, sessionmaker

DATABASE_URL = os.getenv("DATABASE_URL", "sqlite:///data/fixhome.db")
# Render provides URLs like postgresql://... . This project uses psycopg v3,
# so select SQLAlchemy's psycopg dialect explicitly.
if DATABASE_URL.startswith("postgres://"):
    DATABASE_URL = "postgresql+psycopg://" + DATABASE_URL[len("postgres://"):]
elif DATABASE_URL.startswith("postgresql://"):
    DATABASE_URL = "postgresql+psycopg://" + DATABASE_URL[len("postgresql://"):]

connect_args = {"check_same_thread": False} if DATABASE_URL.startswith("sqlite") else {}
engine = create_engine(
    DATABASE_URL,
    pool_pre_ping=True,
    pool_recycle=300,
    future=True,
    connect_args=connect_args,
)
SessionLocal = sessionmaker(bind=engine, expire_on_commit=False, future=True)
_state = threading.local()


class Base(DeclarativeBase):
    pass


class AppMeta(Base):
    __tablename__ = "app_meta"
    key: Mapped[str] = mapped_column(String(100), primary_key=True)
    value_json: Mapped[str] = mapped_column(Text, nullable=False)


class EntityRecord(Base):
    __tablename__ = "entity_records"
    pk: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    collection: Mapped[str] = mapped_column(String(80), nullable=False, index=True)
    entity_id: Mapped[str] = mapped_column(String(160), nullable=False)
    payload_json: Mapped[str] = mapped_column(Text, nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow, nullable=False)
    __table_args__ = (UniqueConstraint("collection", "entity_id", name="uq_entity_collection_id"),)


def init_db() -> None:
    Base.metadata.create_all(bind=engine)


def now_iso() -> str:
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def _hash_password(password: str, salt: str | None = None) -> str:
    # PBKDF2-HMAC-SHA256: no plaintext password is stored.
    salt = salt or secrets.token_hex(16)
    iterations = 600_000
    digest = hashlib.pbkdf2_hmac("sha256", password.encode("utf-8"), salt.encode("utf-8"), iterations).hex()
    return f"pbkdf2_sha256${iterations}${salt}${digest}"


def verify_password(password: str, stored_hash: str) -> bool:
    if not stored_hash:
        return False
    if stored_hash.startswith("pbkdf2_sha256$"):
        try:
            _, iterations, salt, digest = stored_hash.split("$", 3)
            candidate = hashlib.pbkdf2_hmac("sha256", password.encode("utf-8"), salt.encode("utf-8"), int(iterations)).hex()
            return secrets.compare_digest(candidate, digest)
        except (ValueError, TypeError):
            return False
    # Backward compatibility with the previous salt$sha256 demo hashes.
    try:
        salt, digest = stored_hash.split("$", 1)
    except ValueError:
        return False
    legacy = hashlib.sha256(f"{salt}:{password}".encode("utf-8")).hexdigest()
    return secrets.compare_digest(legacy, digest)


def generate_password(length: int = 12) -> str:
    alphabet = string.ascii_letters + string.digits
    while True:
        password = "".join(secrets.choice(alphabet) for _ in range(length))
        if any(c.islower() for c in password) and any(c.isupper() for c in password) and any(c.isdigit() for c in password):
            return password


def make_id(prefix: str) -> str:
    return f"{prefix}_{secrets.token_hex(8)}"

def production_seed_data() -> dict[str, Any]:
    """Create the minimum state for a fresh production deployment.

    Admin credentials come from environment variables and the raw password is
    immediately hashed. No demo customer/company accounts are created.
    """
    admin_email = os.getenv("FIXHOME_ADMIN_EMAIL", "admin@fixhome.id.vn").strip().lower()
    admin_password = os.getenv("FIXHOME_ADMIN_PASSWORD", "").strip()
    admin_name = os.getenv("FIXHOME_ADMIN_NAME", "Quản trị FixHome").strip() or "Quản trị FixHome"
    admin_phone = os.getenv("FIXHOME_ADMIN_PHONE", "").strip()
    if not admin_password:
        raise RuntimeError(
            "FIXHOME_ADMIN_PASSWORD is required for a fresh production database. "
            "Set it in Render Environment before the first deploy."
        )
    return {
        "meta": {
            "created_at": now_iso(),
            "version": "5.1.0-render-production",
            "environment": "production",
        },
        "users": [
            {
                "id": "user_admin",
                "role": "admin",
                "name": admin_name,
                "email": admin_email,
                "phone": admin_phone,
                "password_hash": _hash_password(admin_password),
                "status": "active",
                "company_id": None,
                "created_at": now_iso(),
            }
        ],
        "companies": [],
        "partner_applications": [],
        "orders": [],
        "complaints": [],
        "notifications": [],
    }


def seed_data() -> dict[str, Any]:
    admin_password = "admin123"
    customer_password = "123456"
    company_password = "123456"
    tech_password = "123456"

    company_id_1 = "comp_mekong_cool"
    company_id_2 = "comp_ninhkieu_homecare"
    tech_id_1 = "tech_tuan"
    tech_id_2 = "tech_minh"

    base_time = datetime.now()

    return {
        "meta": {
            "created_at": now_iso(),
            "version": "4.0.0-vietnamese-b2b2c",
        },
        "users": [
            {
                "id": "user_admin",
                "role": "admin",
                "name": "Quản trị FixHome",
                "email": "admin@fixhome.local",
                "phone": "0900000000",
                "password_hash": _hash_password(admin_password),
                "status": "active",
                "company_id": None,
                "created_at": now_iso(),
            },
            {
                "id": "user_customer",
                "role": "customer",
                "name": "Nguyễn Minh Anh",
                "email": "customer@fixhome.local",
                "phone": "0912345678",
                "password_hash": _hash_password(customer_password),
                "status": "active",
                "company_id": None,
                "created_at": now_iso(),
            },
            {
                "id": "user_company_1",
                "role": "company",
                "name": "Công ty Điện lạnh Mekong Cool",
                "email": "company1@fixhome.local",
                "phone": "02923888888",
                "password_hash": _hash_password(company_password),
                "status": "active",
                "company_id": company_id_1,
                "created_at": now_iso(),
            },
            {
                "id": "user_company_2",
                "role": "company",
                "name": "Công ty Ninh Kiều HomeCare",
                "email": "company2@fixhome.local",
                "phone": "02923999999",
                "password_hash": _hash_password(company_password),
                "status": "active",
                "company_id": company_id_2,
                "created_at": now_iso(),
            },
            {
                "id": "user_tech_1",
                "role": "technician",
                "name": "Trần Anh Tuấn",
                "email": "tech1@fixhome.local",
                "phone": "0933333333",
                "password_hash": _hash_password(tech_password),
                "status": "active",
                "company_id": company_id_1,
                "created_at": now_iso(),
            },
            {
                "id": "user_tech_2",
                "role": "technician",
                "name": "Lê Hoàng Minh",
                "email": "tech2@fixhome.local",
                "phone": "0944444444",
                "password_hash": _hash_password(tech_password),
                "status": "active",
                "company_id": company_id_2,
                "created_at": now_iso(),
            },
        ],
        "companies": [
            {
                "id": company_id_1,
                "name": "Công ty Điện lạnh Mekong Cool",
                "tax_code": "1800000011",
                "representative": "Võ Quốc Huy",
                "phone": "02923888888",
                "email": "company1@fixhome.local",
                "address": "Ninh Kiều, Cần Thơ",
                "service_categories": ["Điện lạnh", "Điện gia dụng", "Không rõ lỗi"],
                "legal_status": "Đã xác minh",
                "account_status": "Đã cấp tài khoản",
                "rating": 4.7,
                "created_at": now_iso(),
                "approved_at": now_iso(),
                "manager_user_id": "user_company_1",
                "last_generated_email": "company1@fixhome.local",
                "last_generated_password": None,
                "credential_visible_to_admin": False,
            },
            {
                "id": company_id_2,
                "name": "Công ty Ninh Kiều HomeCare",
                "tax_code": "1800000022",
                "representative": "Phạm Thanh Long",
                "phone": "02923999999",
                "email": "company2@fixhome.local",
                "address": "Bình Thủy, Cần Thơ",
                "service_categories": ["Điện - nước", "Lắp đặt thiết bị", "Điện gia dụng"],
                "legal_status": "Đã xác minh",
                "account_status": "Đã cấp tài khoản",
                "rating": 4.6,
                "created_at": now_iso(),
                "approved_at": now_iso(),
                "manager_user_id": "user_company_2",
                "last_generated_email": "company2@fixhome.local",
                "last_generated_password": None,
                "credential_visible_to_admin": False,
            },
        ],
        "partner_applications": [
            {
                "id": "app_cantho_service_demo",
                "company_name": "Công ty Cần Thơ Service 24h",
                "tax_code": "1800000099",
                "representative": "Đặng Hoàng Nam",
                "phone": "0909123456",
                "email": "ctservice24h@example.com",
                "address": "Cái Răng, Cần Thơ",
                "service_categories": ["Điện lạnh", "Điện - nước"],
                "legal_note": "Có giấy đăng ký kinh doanh, đội kỹ thuật nội bộ 6 người, đang chờ đối chiếu hồ sơ.",
                "status": "Chờ kiểm tra pháp lý",
                "submitted_at": now_iso(),
                "review_note": "",
                "generated_credentials": None,
            }
        ],
        "orders": [
            {
                "id": "ord_demo_001",
                "customer_id": "user_customer",
                "customer_name": "Nguyễn Minh Anh",
                "customer_phone": "0912345678",
                "service_ids": ["ac_clean_wall", "ac_gas_check"],
                "service_names": ["Vệ sinh máy lạnh treo tường", "Kiểm tra và nạp gas máy lạnh"],
                "category": "Điện lạnh",
                "mode": "Chọn dịch vụ phổ biến",
                "description": "Máy lạnh phòng trọ lâu chưa vệ sinh, hơi yếu lạnh.",
                "ai_summary": "Khả năng liên quan bụi bẩn dàn lạnh/dàn nóng hoặc thiếu gas. Nên kiểm tra vệ sinh và áp suất gas.",
                "image_name": None,
                "address": "Đường 3/2, Ninh Kiều, Cần Thơ",
                "scheduled_time": (base_time + timedelta(days=1)).strftime("%Y-%m-%d 09:00"),
                "status": "Doanh nghiệp đã báo giá",
                "company_id": company_id_1,
                "company_name": "Công ty Điện lạnh Mekong Cool",
                "technician_id": None,
                "technician_name": None,
                "quote_min": 350_000,
                "quote_max": 650_000,
                "final_price": None,
                "quote_note": "Báo giá chính xác sau khi kiểm tra thực tế, phí kiểm tra được trừ nếu khách đồng ý sửa.",
                "quote_status": "Chờ khách hàng xác nhận",
                "created_at": now_iso(),
                "updated_at": now_iso(),
                "timeline": [
                    {"time": now_iso(), "status": "Chờ phân phối", "note": "Khách hàng tạo yêu cầu."},
                    {"time": now_iso(), "status": "Đã chuyển doanh nghiệp", "note": "FixHome chuyển yêu cầu đến Công ty Điện lạnh Mekong Cool."},
                    {"time": now_iso(), "status": "Doanh nghiệp đã báo giá", "note": "Doanh nghiệp gửi báo giá dự kiến."},
                ],
                "rating": None,
                "review": "",
            }
        ],
        "complaints": [],
        "notifications": [
            {
                "id": "noti_seed",
                "target_role": "admin",
                "target_user_id": "user_admin",
                "title": "Hồ sơ đối tác mới đang chờ duyệt",
                "message": "Công ty Cần Thơ Service 24h đã gửi form đăng ký hợp tác.",
                "created_at": now_iso(),
                "read": False,
            }
        ],
    }



COLLECTIONS = ("users", "companies", "partner_applications", "orders", "complaints", "notifications")

def _record_map(data: dict[str, Any], collection: str) -> dict[str, dict[str, Any]]:
    result = {}
    for item in data.get(collection, []):
        entity_id = str(item.get("id") or make_id(collection[:4]))
        item["id"] = entity_id
        result[entity_id] = item
    return result

def load_db() -> dict[str, Any]:
    init_db()
    with SessionLocal() as session:
        rows = session.execute(select(EntityRecord)).scalars().all()
        if not rows:
            if os.getenv("FIXHOME_SEED_DEMO", "0") == "1":
                data = seed_data()
            else:
                data = production_seed_data()
            _write_full_state(session, data)
            session.commit()
        else:
            data = {"meta": {}}
            meta_rows = session.execute(select(AppMeta)).scalars().all()
            for m in meta_rows:
                data["meta"][m.key] = json.loads(m.value_json)
            for c in COLLECTIONS:
                data[c] = []
            for row in rows:
                if row.collection in COLLECTIONS:
                    data[row.collection].append(json.loads(row.payload_json))
    _state.baseline = copy.deepcopy(data)
    return data

def _write_full_state(session, data: dict[str, Any]) -> None:
    session.execute(delete(EntityRecord))
    session.execute(delete(AppMeta))
    for k, v in data.get("meta", {}).items():
        session.add(AppMeta(key=k, value_json=json.dumps(v, ensure_ascii=False)))
    for collection in COLLECTIONS:
        for entity_id, item in _record_map(data, collection).items():
            session.add(EntityRecord(collection=collection, entity_id=entity_id, payload_json=json.dumps(item, ensure_ascii=False)))

def save_db(data: dict[str, Any]) -> None:
    """Persist only entities changed since this Streamlit run loaded the DB.

    This keeps the current dict-based app compatible while avoiding a whole-file overwrite
    when two users modify different orders concurrently.
    """
    init_db()
    baseline = getattr(_state, "baseline", {"meta": {}})
    with SessionLocal.begin() as session:
        # Upsert meta values that changed.
        old_meta = baseline.get("meta", {})
        for k, v in data.get("meta", {}).items():
            if old_meta.get(k) != v:
                row = session.get(AppMeta, k)
                value_json = json.dumps(v, ensure_ascii=False)
                if row:
                    row.value_json = value_json
                else:
                    session.add(AppMeta(key=k, value_json=value_json))

        for collection in COLLECTIONS:
            before = _record_map(copy.deepcopy(baseline), collection)
            after = _record_map(data, collection)
            # Delete entities explicitly removed in this run.
            removed = set(before) - set(after)
            if removed:
                session.execute(delete(EntityRecord).where(EntityRecord.collection == collection, EntityRecord.entity_id.in_(removed)))
            # Upsert only new/changed entities.
            for entity_id, item in after.items():
                if before.get(entity_id) == item:
                    continue
                row = session.execute(select(EntityRecord).where(EntityRecord.collection == collection, EntityRecord.entity_id == entity_id)).scalar_one_or_none()
                payload = json.dumps(item, ensure_ascii=False)
                if row:
                    row.payload_json = payload
                    row.updated_at = datetime.utcnow()
                else:
                    session.add(EntityRecord(collection=collection, entity_id=entity_id, payload_json=payload))
    _state.baseline = copy.deepcopy(data)

def reset_db() -> None:
    init_db()
    data = seed_data()
    with SessionLocal.begin() as session:
        _write_full_state(session, data)
    _state.baseline = copy.deepcopy(data)

def find_user_by_email(data: dict[str, Any], email: str) -> dict[str, Any] | None:
    email = email.strip().lower()
    return next((u for u in data["users"] if u["email"].lower() == email), None)


def get_user(data: dict[str, Any], user_id: str | None) -> dict[str, Any] | None:
    if not user_id:
        return None
    return next((u for u in data["users"] if u["id"] == user_id), None)


def get_company(data: dict[str, Any], company_id: str | None) -> dict[str, Any] | None:
    if not company_id:
        return None
    return next((c for c in data["companies"] if c["id"] == company_id), None)


def add_user(
    data: dict[str, Any],
    *,
    role: str,
    name: str,
    email: str,
    phone: str,
    password: str,
    company_id: str | None = None,
) -> dict[str, Any]:
    if find_user_by_email(data, email):
        raise ValueError("Email này đã tồn tại trong hệ thống.")
    user = {
        "id": make_id("user"),
        "role": role,
        "name": name.strip(),
        "email": email.strip().lower(),
        "phone": phone.strip(),
        "password_hash": _hash_password(password),
        "status": "active",
        "company_id": company_id,
        "created_at": now_iso(),
    }
    data["users"].append(user)
    return user


def create_customer(data: dict[str, Any], name: str, email: str, phone: str, password: str) -> dict[str, Any]:
    return add_user(data, role="customer", name=name, email=email, phone=phone, password=password)


def create_company_from_application(data: dict[str, Any], application_id: str, admin_note: str = "") -> tuple[dict[str, Any], dict[str, Any], str]:
    app = next((a for a in data["partner_applications"] if a["id"] == application_id), None)
    if not app:
        raise ValueError("Không tìm thấy hồ sơ đăng ký đối tác.")
    if app["status"] == "Đã duyệt":
        existing = next((c for c in data["companies"] if c.get("source_application_id") == application_id), None)
        if existing:
            manager = get_user(data, existing.get("manager_user_id"))
            return existing, manager, "Đã cấp trước đó"
        raise ValueError("Hồ sơ này đã được duyệt nhưng không tìm thấy doanh nghiệp liên kết.")

    company_id = make_id("comp")
    base_email = app["email"].strip().lower()
    account_email = base_email
    if find_user_by_email(data, account_email):
        local, _, domain = base_email.partition("@")
        account_email = f"{local}+fixhome-{secrets.token_hex(2)}@{domain or 'example.com'}"

    raw_password = generate_password(12)
    manager = add_user(
        data,
        role="company",
        name=app["company_name"],
        email=account_email,
        phone=app["phone"],
        password=raw_password,
        company_id=company_id,
    )
    company = {
        "id": company_id,
        "name": app["company_name"],
        "tax_code": app["tax_code"],
        "representative": app["representative"],
        "phone": app["phone"],
        "email": app["email"],
        "address": app["address"],
        "service_categories": app["service_categories"],
        "legal_status": "Đã xác minh",
        "account_status": "Đã cấp tài khoản",
        "rating": 0,
        "created_at": now_iso(),
        "approved_at": now_iso(),
        "manager_user_id": manager["id"],
        "source_application_id": application_id,
        "last_generated_email": account_email,
        "last_generated_password": None,
        "credential_visible_to_admin": False,
    }
    data["companies"].append(company)

    app["status"] = "Đã duyệt"
    app["approved_at"] = now_iso()
    app["review_note"] = admin_note
    app["generated_credentials"] = {
        "email": account_email,
        "password": None,
        "created_at": now_iso(),
        "note": "Admin liên hệ trực tiếp với doanh nghiệp để bàn giao tài khoản.",
    }

    data["notifications"].append({
        "id": make_id("noti"),
        "target_role": "admin",
        "target_user_id": "user_admin",
        "title": "Đã cấp tài khoản đối tác doanh nghiệp",
        "message": f"{app['company_name']} đã được duyệt. Tài khoản: {account_email}. Mật khẩu tạm thời chỉ hiển thị một lần khi cấp.",
        "created_at": now_iso(),
        "read": False,
    })
    return company, manager, raw_password


def create_technician_for_company(
    data: dict[str, Any],
    *,
    company_id: str,
    name: str,
    email: str,
    phone: str,
    skill_note: str,
) -> tuple[dict[str, Any], str]:
    raw_password = generate_password(12)
    technician = add_user(
        data,
        role="technician",
        name=name,
        email=email,
        phone=phone,
        password=raw_password,
        company_id=company_id,
    )
    technician["skill_note"] = skill_note
    technician["created_by_company"] = True
    return technician, raw_password


def append_timeline(order: dict[str, Any], status: str, note: str) -> None:
    order["status"] = status
    order["updated_at"] = now_iso()
    order.setdefault("timeline", []).append({"time": now_iso(), "status": status, "note": note})


def select_company_for_order(data: dict[str, Any], order: dict[str, Any]) -> dict[str, Any] | None:
    candidates = []
    for company in data["companies"]:
        if company.get("legal_status") != "Đã xác minh":
            continue
        if order.get("category") not in company.get("service_categories", []):
            continue
        active_orders = sum(
            1
            for o in data["orders"]
            if o.get("company_id") == company["id"] and o.get("status") not in ["Hoàn thành", "Đã hủy"]
        )
        candidates.append((active_orders, -float(company.get("rating") or 0), company))
    if not candidates:
        return None
    candidates.sort(key=lambda item: (item[0], item[1]))
    return candidates[0][2]


def assign_order_to_company(data: dict[str, Any], order_id: str, company_id: str | None = None) -> dict[str, Any]:
    order = next((o for o in data["orders"] if o["id"] == order_id), None)
    if not order:
        raise ValueError("Không tìm thấy yêu cầu dịch vụ.")
    company = get_company(data, company_id) if company_id else select_company_for_order(data, order)
    if not company:
        raise ValueError("Chưa có doanh nghiệp phù hợp với nhóm dịch vụ này.")
    order["company_id"] = company["id"]
    order["company_name"] = company["name"]
    append_timeline(order, "Đã chuyển doanh nghiệp", f"FixHome chuyển yêu cầu đến {company['name']}.")
    return order


def company_revenue(data: dict[str, Any], company_id: str) -> int:
    total = 0
    for order in data["orders"]:
        if order.get("company_id") != company_id:
            continue
        if order.get("status") == "Hoàn thành" and order.get("final_price"):
            total += int(order["final_price"])
    return total


def company_connection_count(data: dict[str, Any], company_id: str) -> int:
    return sum(1 for o in data["orders"] if o.get("company_id") == company_id)
