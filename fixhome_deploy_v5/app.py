from __future__ import annotations

from datetime import datetime, timedelta
from pathlib import Path

import pandas as pd
import streamlit as st

from fixhome.ai.diagnosis import get_diagnosis_model
from fixhome.data.services import (
    SERVICE_CATEGORIES,
    SERVICE_ICON,
    STATUS_FLOW,
    all_services,
    estimate_services,
    format_vnd,
    get_service,
)
from fixhome.db import (
    append_timeline,
    assign_order_to_company,
    company_connection_count,
    company_revenue,
    create_company_from_application,
    create_customer,
    create_technician_for_company,
    find_user_by_email,
    get_company,
    get_user,
    load_db,
    make_id,
    now_iso,
    reset_db,
    save_db,
    select_company_for_order,
    verify_password,
)
from fixhome.ui import card, hero, metric_card, price_card, render_timeline, setup_page, status_badge, vnd

UPLOAD_DIR = Path("uploads")
UPLOAD_DIR.mkdir(exist_ok=True)


def get_data():
    if "db" not in st.session_state:
        st.session_state.db = load_db()
    return st.session_state.db


def persist():
    save_db(st.session_state.db)


def current_user():
    data = get_data()
    return get_user(data, st.session_state.get("user_id"))


def require_role(*roles):
    user = current_user()
    return user is not None and user.get("role") in roles


def page_title(title: str, subtitle: str = ""):
    st.markdown(f"### {title}")
    if subtitle:
        st.caption(subtitle)


def sidebar_auth():
    data = get_data()
    with st.sidebar:
        st.markdown("## 🏠 FixHome")
        st.markdown("**Nền tảng sửa chữa gia dụng tại Cần Thơ**")
        st.divider()

        user = current_user()
        if user:
            role_name = {
                "admin": "Quản trị FixHome",
                "customer": "Khách hàng",
                "company": "Doanh nghiệp đối tác",
                "technician": "Kỹ thuật viên doanh nghiệp",
            }.get(user["role"], user["role"])
            st.markdown(f"**Đang đăng nhập**  \n{user['name']}")
            st.markdown(f"Vai trò: **{role_name}**")
            if st.button("Đăng xuất", use_container_width=True):
                st.session_state.pop("user_id", None)
                st.rerun()
            st.divider()
            st.caption("FixHome chỉ đóng vai trò nền tảng trung gian. Doanh nghiệp đối tác chịu trách nhiệm thực hiện dịch vụ, báo giá cuối cùng và bảo hành.")
            return

        tab_login, tab_register = st.tabs(["Đăng nhập", "Đăng ký khách hàng"])
        with tab_login:
            with st.form("login_form"):
                email = st.text_input("Email", placeholder="Nhập email")
                password = st.text_input("Mật khẩu", type="password", placeholder="Nhập mật khẩu")
                submitted = st.form_submit_button("Đăng nhập", use_container_width=True, type="primary")
            if submitted:
                user = find_user_by_email(data, email)
                if user and user.get("status") == "active" and verify_password(password, user["password_hash"]):
                    st.session_state.user_id = user["id"]
                    st.success("Đăng nhập thành công.")
                    st.rerun()
                else:
                    st.error("Email hoặc mật khẩu không đúng, hoặc tài khoản chưa được kích hoạt.")

        with tab_register:
            with st.form("customer_register_form"):
                name = st.text_input("Họ và tên")
                phone = st.text_input("Số điện thoại")
                email = st.text_input("Email đăng nhập")
                password = st.text_input("Mật khẩu", type="password")
                password2 = st.text_input("Nhập lại mật khẩu", type="password")
                submitted = st.form_submit_button("Tạo tài khoản khách hàng", use_container_width=True, type="primary")
            if submitted:
                if not name or not phone or not email or not password:
                    st.error("Vui lòng nhập đầy đủ thông tin.")
                elif password != password2:
                    st.error("Mật khẩu nhập lại chưa khớp.")
                else:
                    try:
                        user = create_customer(data, name, email, phone, password)
                        persist()
                        st.session_state.user_id = user["id"]
                        st.success("Tạo tài khoản thành công.")
                        st.rerun()
                    except ValueError as e:
                        st.error(str(e))

        st.divider()
        st.caption("Tài khoản demo được ghi trong README.md, không hiển thị tại trang chủ để giao diện giống sản phẩm thật hơn.")


def guest_home():
    hero(
        "Dịch vụ sửa chữa gia dụng minh bạch cho Cần Thơ",
        "FixHome kết nối khách hàng với doanh nghiệp sửa chữa đã được kiểm tra pháp lý, hỗ trợ chẩn đoán sơ bộ bằng AI, báo giá rõ ràng và theo dõi tiến trình trên một hệ thống.",
        ["B2B2C", "Doanh nghiệp xác minh", "Báo giá minh bạch", "Theo dõi tiến trình"],
    )

    c1, c2, c3 = st.columns(3)
    with c1:
        card("Khách hàng", "Đặt dịch vụ, gửi ảnh lỗi thiết bị, nhận báo giá và theo dõi tiến trình sửa chữa.", "Dễ sử dụng")
    with c2:
        card("Doanh nghiệp đối tác", "Nhận yêu cầu phù hợp, báo giá, phân công kỹ thuật viên và quản lý doanh thu của riêng doanh nghiệp.", "Có pháp nhân")
    with c3:
        card("FixHome Admin", "Kiểm tra hồ sơ đối tác, cấp tài khoản doanh nghiệp và thống kê số lượt kết nối. Không quản lý doanh thu của đối tác.", "Trung gian")

    st.markdown("### Bảng giá tham khảo")
    render_price_overview(limit_per_category=4)

    st.markdown("### Đăng ký trở thành doanh nghiệp đối tác")
    st.info("Doanh nghiệp gửi form đăng ký. Sau khi FixHome kiểm tra hồ sơ pháp lý và phê duyệt, hệ thống sẽ tự tạo tài khoản đối tác doanh nghiệp để admin liên hệ bàn giao.")
    partner_application_form()


def render_price_overview(limit_per_category: int | None = None):
    services_by_cat = SERVICE_CATEGORIES
    tabs = st.tabs([f"{SERVICE_ICON.get(cat, '🛠️')} {cat}" for cat in services_by_cat.keys()])
    for tab, (category, services) in zip(tabs, services_by_cat.items()):
        with tab:
            show_services = services[:limit_per_category] if limit_per_category else services
            for service in show_services:
                price = f"{format_vnd(service['min_price'])} - {format_vnd(service['max_price'])}/{service['unit']}"
                price_card(service["name"], price, service["description"], category)
            if limit_per_category and len(services) > limit_per_category:
                st.caption(f"Còn {len(services) - limit_per_category} dịch vụ khác trong nhóm {category}.")
    st.caption("Giá chỉ là khoảng tham khảo cho MVP. Báo giá cuối cùng do doanh nghiệp đối tác xác nhận sau khi kiểm tra tình trạng thực tế.")


def partner_application_form():
    data = get_data()
    with st.form("partner_application_form", clear_on_submit=True):
        c1, c2 = st.columns(2)
        with c1:
            company_name = st.text_input("Tên doanh nghiệp")
            tax_code = st.text_input("Mã số thuế / mã đăng ký kinh doanh")
            representative = st.text_input("Người đại diện")
            phone = st.text_input("Số điện thoại liên hệ")
        with c2:
            email = st.text_input("Email nhận tài khoản")
            address = st.text_area("Địa chỉ hoạt động tại Cần Thơ", height=90)
            categories = st.multiselect("Nhóm dịch vụ doanh nghiệp có thể thực hiện", list(SERVICE_CATEGORIES.keys()))
        legal_note = st.text_area("Mô tả hồ sơ pháp lý và năng lực kỹ thuật", placeholder="Ví dụ: có giấy đăng ký kinh doanh, đội kỹ thuật nội bộ, khu vực phục vụ, chính sách bảo hành...")
        submitted = st.form_submit_button("Gửi hồ sơ đăng ký đối tác", type="primary", use_container_width=True)

    if submitted:
        if not company_name or not tax_code or not representative or not phone or not email or not address or not categories:
            st.error("Vui lòng nhập đầy đủ thông tin bắt buộc.")
            return
        app = {
            "id": make_id("app"),
            "company_name": company_name.strip(),
            "tax_code": tax_code.strip(),
            "representative": representative.strip(),
            "phone": phone.strip(),
            "email": email.strip().lower(),
            "address": address.strip(),
            "service_categories": categories,
            "legal_note": legal_note.strip(),
            "status": "Chờ kiểm tra pháp lý",
            "submitted_at": now_iso(),
            "review_note": "",
            "generated_credentials": None,
        }
        data["partner_applications"].append(app)
        data["notifications"].append({
            "id": make_id("noti"),
            "target_role": "admin",
            "target_user_id": "user_admin",
            "title": "Hồ sơ đối tác mới",
            "message": f"{company_name} đã gửi form đăng ký hợp tác.",
            "created_at": now_iso(),
            "read": False,
        })
        persist()
        st.success("Đã gửi hồ sơ. FixHome sẽ kiểm tra pháp lý trước khi cấp tài khoản doanh nghiệp.")


def customer_app():
    data = get_data()
    user = current_user()
    nav = st.sidebar.radio(
        "Menu khách hàng",
        ["Trang chủ", "Đặt dịch vụ", "Theo dõi đơn", "Đánh giá & khiếu nại", "Tài khoản"],
    )
    if nav == "Trang chủ":
        hero(
            "Xin chào, cần sửa gì hôm nay?",
            "Chọn dịch vụ phổ biến hoặc gửi ảnh thiết bị nếu bạn chưa biết lỗi chính xác. FixHome sẽ chuyển yêu cầu đến doanh nghiệp phù hợp tại Cần Thơ.",
            ["Báo giá trước", "Theo dõi tiến trình", "Doanh nghiệp xác minh"],
        )
        render_customer_dashboard(user)
        st.markdown("### Bảng giá dịch vụ phổ biến")
        render_price_overview()
    elif nav == "Đặt dịch vụ":
        customer_booking_page()
    elif nav == "Theo dõi đơn":
        customer_orders_page()
    elif nav == "Đánh giá & khiếu nại":
        customer_feedback_page()
    else:
        account_page(user)


def render_customer_dashboard(user):
    data = get_data()
    orders = [o for o in data["orders"] if o.get("customer_id") == user["id"]]
    active = [o for o in orders if o.get("status") != "Hoàn thành"]
    completed = [o for o in orders if o.get("status") == "Hoàn thành"]
    c1, c2, c3 = st.columns(3)
    with c1:
        metric_card("Đơn đang xử lý", str(len(active)), "Có thể theo dõi theo thời gian thực")
    with c2:
        metric_card("Đơn đã hoàn thành", str(len(completed)), "Lưu lịch sử sửa chữa")
    with c3:
        metric_card("Nhóm dịch vụ", str(len(SERVICE_CATEGORIES)), "Điện lạnh, điện nước, gia dụng")


def customer_booking_page():
    data = get_data()
    user = current_user()
    page_title("Đặt dịch vụ sửa chữa", "Giao diện ưu tiên dễ dùng cho khách hàng không rành công nghệ.")

    mode = st.radio(
        "Bạn muốn đặt dịch vụ theo cách nào?",
        ["Chọn dịch vụ phổ biến", "Không rõ lỗi - gửi ảnh và mô tả"],
        horizontal=True,
    )

    if mode == "Chọn dịch vụ phổ biến":
        selected_ids = popular_service_selector()
        estimate = estimate_services(selected_ids)
        if selected_ids:
            st.success(f"Khoảng giá tham khảo: {estimate['text']}")
        category = estimate["services"][0]["category"] if estimate["services"] else "Không rõ lỗi"
        ai_summary = "Khách hàng chọn dịch vụ cụ thể. Hệ thống chuyển yêu cầu theo nhóm dịch vụ đã chọn."
        image_name = None
        description_default = ""
    else:
        selected_ids = ["unknown_diagnosis"]
        estimate = estimate_services(selected_ids)
        st.info("Dùng khi bạn không biết chính xác thiết bị lỗi gì. Bạn có thể gửi ảnh và mô tả hiện tượng, hệ thống sẽ phân loại sơ bộ trước khi chuyển doanh nghiệp.")
        uploaded = st.file_uploader("Tải ảnh thiết bị hoặc vị trí lỗi", type=["png", "jpg", "jpeg", "webp"])
        description_default = st.text_area(
            "Mô tả lỗi bạn đang gặp",
            placeholder="Ví dụ: máy lạnh chạy nhưng không lạnh, có tiếng kêu lớn, đèn báo lỗi nhấp nháy...",
            height=130,
        )
        diagnosis_model = get_diagnosis_model()
        if st.button("Phân tích sơ bộ bằng AI", type="primary", use_container_width=True):
            result = diagnosis_model.analyze(description_default, uploaded)
            st.session_state.last_diagnosis = result
            st.session_state.last_uploaded_file = uploaded.name if uploaded else None
        result = st.session_state.get("last_diagnosis")
        if result:
            category = result.suggested_service_category
            ai_summary = result.summary
            c1, c2, c3 = st.columns(3)
            with c1:
                metric_card("Thiết bị", result.device_type, f"Độ tin cậy: {int(result.confidence * 100)}%")
            with c2:
                metric_card("Nhóm lỗi", result.issue_group, f"Rủi ro: {result.risk_level}")
            with c3:
                metric_card("Nhóm dịch vụ", result.suggested_service_category, result.model_name)
            st.warning(result.safety_note)
        else:
            category = "Không rõ lỗi"
            ai_summary = "Khách hàng chưa chạy phân tích AI. Doanh nghiệp cần kiểm tra trực tiếp."
        image_name = uploaded.name if 'uploaded' in locals() and uploaded else None

    st.markdown("### Thông tin đặt lịch")
    with st.form("booking_form"):
        c1, c2 = st.columns(2)
        with c1:
            scheduled_date = st.date_input("Ngày mong muốn", min_value=datetime.now().date(), value=(datetime.now() + timedelta(days=1)).date())
            scheduled_time = st.time_input("Giờ mong muốn", value=datetime.strptime("09:00", "%H:%M").time())
            phone = st.text_input("Số điện thoại liên hệ", value=user.get("phone", ""))
        with c2:
            address = st.text_area("Địa chỉ tại Cần Thơ", placeholder="Số nhà, đường, phường/xã, quận/huyện", height=115)
            description = st.text_area("Ghi chú thêm cho doanh nghiệp", value=description_default, height=115)
        agree = st.checkbox("Tôi hiểu rằng giá hiển thị là giá tham khảo. Báo giá cuối cùng do doanh nghiệp đối tác xác nhận sau khi kiểm tra.")
        submitted = st.form_submit_button("Gửi yêu cầu sửa chữa", type="primary", use_container_width=True)

    if submitted:
        if not selected_ids:
            st.error("Vui lòng chọn ít nhất một dịch vụ.")
            return
        if not address or not phone:
            st.error("Vui lòng nhập số điện thoại và địa chỉ.")
            return
        if not agree:
            st.error("Vui lòng xác nhận điều kiện báo giá tham khảo.")
            return

        saved_image_name = None
        if mode != "Chọn dịch vụ phổ biến" and image_name and 'uploaded' in locals() and uploaded:
            safe_name = f"{make_id('img')}_{uploaded.name}"
            path = UPLOAD_DIR / safe_name
            path.write_bytes(uploaded.getvalue())
            saved_image_name = safe_name

        selected_services = [get_service(sid) for sid in selected_ids if get_service(sid)]
        service_names = [s["name"] for s in selected_services]
        final_category = category if category in SERVICE_CATEGORIES else (selected_services[0]["category"] if selected_services else "Không rõ lỗi")

        order = {
            "id": make_id("ord"),
            "customer_id": user["id"],
            "customer_name": user["name"],
            "customer_phone": phone.strip(),
            "service_ids": selected_ids,
            "service_names": service_names,
            "category": final_category,
            "mode": mode,
            "description": description.strip(),
            "ai_summary": ai_summary,
            "image_name": saved_image_name,
            "address": address.strip(),
            "scheduled_time": f"{scheduled_date} {scheduled_time.strftime('%H:%M')}",
            "status": "Chờ phân phối",
            "company_id": None,
            "company_name": None,
            "technician_id": None,
            "technician_name": None,
            "quote_min": estimate.get("min_total"),
            "quote_max": estimate.get("max_total"),
            "final_price": None,
            "quote_note": "",
            "quote_status": "Chưa có báo giá",
            "created_at": now_iso(),
            "updated_at": now_iso(),
            "timeline": [{"time": now_iso(), "status": "Chờ phân phối", "note": "Khách hàng gửi yêu cầu dịch vụ."}],
            "rating": None,
            "review": "",
        }
        data["orders"].append(order)

        # Tự động phân phối nếu có doanh nghiệp phù hợp.
        company = select_company_for_order(data, order)
        if company:
            order["company_id"] = company["id"]
            order["company_name"] = company["name"]
            append_timeline(order, "Đã chuyển doanh nghiệp", f"FixHome tự động chuyển yêu cầu đến {company['name']} dựa trên nhóm dịch vụ và tải đơn hiện tại.")
            message = f"Yêu cầu mới từ {user['name']} đã được chuyển đến doanh nghiệp."
        else:
            message = "Yêu cầu đã được tạo. Admin sẽ phân phối khi có doanh nghiệp phù hợp."
        persist()
        st.success(message)
        st.rerun()


def popular_service_selector() -> list[str]:
    st.markdown("### Chọn một hoặc nhiều dịch vụ phổ biến")
    service_options = all_services()
    selected_ids: list[str] = []
    for category, services in SERVICE_CATEGORIES.items():
        if category == "Không rõ lỗi":
            continue
        with st.expander(f"{SERVICE_ICON.get(category, '🛠️')} {category}", expanded=category in ["Điện lạnh", "Điện gia dụng"]):
            cols = st.columns(2)
            for idx, service in enumerate(services):
                price_text = f"{format_vnd(service['min_price'])} - {format_vnd(service['max_price'])}/{service['unit']}"
                with cols[idx % 2]:
                    checked = st.checkbox(
                        f"{service['name']}\n\n{price_text}",
                        key=f"service_{service['id']}",
                    )
                    st.caption(service["description"])
                    if checked:
                        selected_ids.append(service["id"])
    return selected_ids


def customer_orders_page():
    data = get_data()
    user = current_user()
    page_title("Theo dõi đơn sửa chữa", "Khách hàng xem báo giá, xác nhận và theo dõi toàn bộ tiến trình.")
    orders = [o for o in data["orders"] if o.get("customer_id") == user["id"]]
    if not orders:
        st.info("Bạn chưa có yêu cầu dịch vụ nào.")
        return
    orders = sorted(orders, key=lambda x: x.get("created_at", ""), reverse=True)
    for order in orders:
        with st.expander(f"{order['id']} — {', '.join(order.get('service_names', []))} — {order.get('status')}", expanded=order.get("status") != "Hoàn thành"):
            render_order_detail(order, viewer="customer")
            if order.get("quote_status") == "Chờ khách hàng xác nhận":
                st.markdown("#### Báo giá từ doanh nghiệp")
                st.write(f"Doanh nghiệp: **{order.get('company_name')}**")
                st.write(f"Khoảng giá: **{vnd(order.get('quote_min'))} - {vnd(order.get('quote_max'))}**")
                st.write(f"Ghi chú: {order.get('quote_note') or 'Không có'}")
                c1, c2 = st.columns(2)
                with c1:
                    if st.button("Xác nhận báo giá", key=f"approve_quote_{order['id']}", type="primary", use_container_width=True):
                        order["quote_status"] = "Khách hàng đã xác nhận"
                        append_timeline(order, "Khách hàng xác nhận báo giá", "Khách hàng đồng ý với báo giá dự kiến và chờ doanh nghiệp phân công kỹ thuật viên.")
                        persist()
                        st.success("Đã xác nhận báo giá.")
                        st.rerun()
                with c2:
                    if st.button("Chưa đồng ý", key=f"reject_quote_{order['id']}", use_container_width=True):
                        order["quote_status"] = "Khách hàng yêu cầu báo giá lại"
                        append_timeline(order, "Đã chuyển doanh nghiệp", "Khách hàng chưa đồng ý báo giá và yêu cầu doanh nghiệp điều chỉnh/thuyết minh thêm.")
                        persist()
                        st.info("Đã gửi yêu cầu xem lại báo giá.")
                        st.rerun()


def render_order_detail(order: dict, viewer: str = "admin"):
    st.markdown(f"**Trạng thái:** {status_badge(order.get('status', ''))}", unsafe_allow_html=True)
    c1, c2 = st.columns(2)
    with c1:
        st.write(f"**Khách hàng:** {order.get('customer_name')}")
        st.write(f"**SĐT:** {order.get('customer_phone')}")
        st.write(f"**Địa chỉ:** {order.get('address')}")
        st.write(f"**Lịch mong muốn:** {order.get('scheduled_time')}")
    with c2:
        st.write(f"**Nhóm dịch vụ:** {order.get('category')}")
        st.write(f"**Dịch vụ:** {', '.join(order.get('service_names', []))}")
        st.write(f"**Doanh nghiệp:** {order.get('company_name') or 'Chưa phân phối'}")
        st.write(f"**Kỹ thuật viên:** {order.get('technician_name') or 'Chưa phân công'}")
    st.write(f"**Mô tả:** {order.get('description') or 'Không có'}")
    if order.get("ai_summary"):
        st.info(order["ai_summary"])
    if order.get("image_name"):
        st.caption(f"Ảnh đính kèm: {order['image_name']}")
    st.markdown("#### Tiến trình")
    render_timeline(order.get("timeline", []))


def customer_feedback_page():
    data = get_data()
    user = current_user()
    page_title("Đánh giá & khiếu nại", "Gửi phản hồi sau khi hoàn thành hoặc khi cần hỗ trợ.")
    orders = [o for o in data["orders"] if o.get("customer_id") == user["id"]]
    if not orders:
        st.info("Bạn chưa có đơn để phản hồi.")
        return
    order_map = {f"{o['id']} — {', '.join(o.get('service_names', []))} — {o.get('status')}": o for o in orders}
    selected_label = st.selectbox("Chọn đơn", list(order_map.keys()))
    order = order_map[selected_label]

    tab_review, tab_complaint = st.tabs(["Đánh giá", "Gửi khiếu nại"])
    with tab_review:
        if order.get("status") != "Hoàn thành":
            st.warning("Bạn chỉ nên đánh giá sau khi dịch vụ hoàn thành.")
        with st.form(f"review_{order['id']}"):
            rating = st.slider("Mức hài lòng", 1, 5, int(order.get("rating") or 5))
            review = st.text_area("Nhận xét", value=order.get("review", ""))
            submitted = st.form_submit_button("Lưu đánh giá", type="primary")
        if submitted:
            order["rating"] = rating
            order["review"] = review.strip()
            persist()
            st.success("Đã lưu đánh giá.")

    with tab_complaint:
        with st.form(f"complaint_{order['id']}"):
            title = st.text_input("Tiêu đề khiếu nại")
            content = st.text_area("Nội dung", height=130)
            submitted = st.form_submit_button("Gửi khiếu nại", type="primary")
        if submitted:
            if not title or not content:
                st.error("Vui lòng nhập tiêu đề và nội dung khiếu nại.")
            else:
                complaint = {
                    "id": make_id("cmp"),
                    "order_id": order["id"],
                    "customer_id": user["id"],
                    "customer_name": user["name"],
                    "company_id": order.get("company_id"),
                    "company_name": order.get("company_name"),
                    "title": title.strip(),
                    "content": content.strip(),
                    "status": "Khiếu nại mới",
                    "admin_note": "",
                    "created_at": now_iso(),
                    "updated_at": now_iso(),
                }
                data["complaints"].append(complaint)
                persist()
                st.success("Đã gửi khiếu nại. FixHome sẽ theo dõi và làm việc với doanh nghiệp liên quan.")
                st.rerun()


def account_page(user):
    page_title("Thông tin tài khoản")
    st.write(f"**Họ tên:** {user.get('name')}")
    st.write(f"**Email:** {user.get('email')}")
    st.write(f"**Số điện thoại:** {user.get('phone')}")
    st.write(f"**Vai trò:** {user.get('role')}")


def admin_app():
    nav = st.sidebar.radio(
        "Menu quản trị",
        ["Tổng quan", "Duyệt đối tác", "Phân phối khách hàng", "Quản lý doanh nghiệp", "Khiếu nại", "Dữ liệu demo"],
    )
    if nav == "Tổng quan":
        admin_dashboard()
    elif nav == "Duyệt đối tác":
        admin_partner_review()
    elif nav == "Phân phối khách hàng":
        admin_order_distribution()
    elif nav == "Quản lý doanh nghiệp":
        admin_company_management()
    elif nav == "Khiếu nại":
        admin_complaints()
    else:
        admin_demo_data()


def admin_dashboard():
    data = get_data()
    hero(
        "Bảng điều khiển quản trị FixHome",
        "Admin đóng vai trò trung gian: kiểm tra pháp lý đối tác, cấp tài khoản doanh nghiệp, phân phối kết nối khách hàng và xử lý khiếu nại. Admin không xem doanh thu của doanh nghiệp.",
        ["Không quản lý doanh thu", "Theo dõi số lượt kết nối", "Duyệt pháp lý đối tác"],
    )
    c1, c2, c3, c4 = st.columns(4)
    with c1:
        metric_card("Hồ sơ đối tác chờ duyệt", str(sum(1 for a in data["partner_applications"] if a["status"] == "Chờ kiểm tra pháp lý")))
    with c2:
        metric_card("Doanh nghiệp đã xác minh", str(len(data["companies"])))
    with c3:
        metric_card("Tổng yêu cầu dịch vụ", str(len(data["orders"])))
    with c4:
        metric_card("Khiếu nại đang xử lý", str(sum(1 for c in data["complaints"] if c["status"] != "Đã xử lý")))

    st.markdown("### Số lượt kết nối khách hàng theo doanh nghiệp")
    rows = []
    for company in data["companies"]:
        rows.append({
            "Doanh nghiệp": company["name"],
            "Nhóm dịch vụ": ", ".join(company.get("service_categories", [])),
            "Số lượt kết nối": company_connection_count(data, company["id"]),
            "Trạng thái pháp lý": company.get("legal_status"),
        })
    st.dataframe(pd.DataFrame(rows), use_container_width=True, hide_index=True)
    st.caption("Admin chỉ thống kê số lượt kết nối/đơn được chuyển đến doanh nghiệp, không thống kê doanh thu của doanh nghiệp đối tác.")


def admin_partner_review():
    data = get_data()
    page_title("Duyệt hồ sơ đối tác doanh nghiệp", "Khi duyệt thành công, hệ thống tự tạo tài khoản đối tác và hiển thị email/mật khẩu để admin liên hệ bàn giao.")
    apps = sorted(data["partner_applications"], key=lambda a: a.get("submitted_at", ""), reverse=True)
    if not apps:
        st.info("Chưa có hồ sơ đăng ký đối tác.")
        return
    for app in apps:
        with st.expander(f"{app['company_name']} — {app['status']}", expanded=app["status"] == "Chờ kiểm tra pháp lý"):
            st.markdown(f"**Trạng thái:** {status_badge(app['status'])}", unsafe_allow_html=True)
            c1, c2 = st.columns(2)
            with c1:
                st.write(f"**Mã số thuế:** {app.get('tax_code')}")
                st.write(f"**Người đại diện:** {app.get('representative')}")
                st.write(f"**SĐT:** {app.get('phone')}")
                st.write(f"**Email:** {app.get('email')}")
            with c2:
                st.write(f"**Địa chỉ:** {app.get('address')}")
                st.write(f"**Nhóm dịch vụ:** {', '.join(app.get('service_categories', []))}")
                st.write(f"**Ngày gửi:** {app.get('submitted_at')}")
            st.write(f"**Ghi chú pháp lý/năng lực:** {app.get('legal_note') or 'Không có'}")

            if app.get("generated_credentials"):
                creds = app["generated_credentials"]
                st.success("Tài khoản doanh nghiệp đã được tạo.")
                st.code(f"Email đăng nhập: {creds['email']}\nMật khẩu tạm thời không được lưu lại vì lý do bảo mật.", language="text")

            if app["status"] == "Chờ kiểm tra pháp lý":
                note = st.text_area("Ghi chú kiểm tra pháp lý", key=f"note_{app['id']}")
                c1, c2 = st.columns(2)
                with c1:
                    if st.button("Duyệt và cấp tài khoản", key=f"approve_{app['id']}", type="primary", use_container_width=True):
                        try:
                            company, manager, raw_password = create_company_from_application(data, app["id"], note)
                            persist()
                            st.success("Đã duyệt hồ sơ và tự động tạo tài khoản doanh nghiệp.")
                            st.warning("Hãy sao chép mật khẩu ngay. Hệ thống không lưu plaintext password.")
                            st.code(f"Email đăng nhập: {manager['email']}\nMật khẩu tạm thời: {raw_password}", language="text")
                        except ValueError as e:
                            st.error(str(e))
                with c2:
                    if st.button("Từ chối hồ sơ", key=f"reject_{app['id']}", use_container_width=True):
                        app["status"] = "Từ chối"
                        app["review_note"] = note or "Hồ sơ chưa đáp ứng yêu cầu xác minh."
                        app["reviewed_at"] = now_iso()
                        persist()
                        st.warning("Đã từ chối hồ sơ.")
                        st.rerun()


def admin_order_distribution():
    data = get_data()
    page_title("Phân phối kết nối khách hàng", "FixHome có thể tự động phân phối dựa trên nhóm dịch vụ, pháp lý doanh nghiệp và số đơn đang xử lý.")
    orders = sorted(data["orders"], key=lambda o: o.get("created_at", ""), reverse=True)
    if not orders:
        st.info("Chưa có yêu cầu dịch vụ.")
        return
    for order in orders:
        with st.expander(f"{order['id']} — {order.get('category')} — {order.get('status')}"):
            render_order_detail(order, viewer="admin")
            if order.get("status") == "Chờ phân phối":
                c1, c2 = st.columns(2)
                with c1:
                    if st.button("Tự động phân phối", key=f"auto_assign_{order['id']}", type="primary", use_container_width=True):
                        try:
                            assign_order_to_company(data, order["id"])
                            persist()
                            st.success("Đã tự động phân phối yêu cầu.")
                            st.rerun()
                        except ValueError as e:
                            st.error(str(e))
                with c2:
                    company_labels = {c["name"]: c["id"] for c in data["companies"] if order.get("category") in c.get("service_categories", [])}
                    if company_labels:
                        selected = st.selectbox("Hoặc chọn doanh nghiệp thủ công", list(company_labels.keys()), key=f"manual_company_{order['id']}")
                        if st.button("Chuyển đến doanh nghiệp đã chọn", key=f"manual_assign_{order['id']}", use_container_width=True):
                            assign_order_to_company(data, order["id"], company_labels[selected])
                            persist()
                            st.success("Đã chuyển yêu cầu.")
                            st.rerun()
                    else:
                        st.warning("Chưa có doanh nghiệp phù hợp với nhóm dịch vụ này.")
            else:
                st.caption("Đơn này đã được phân phối hoặc đang xử lý.")


def admin_company_management():
    data = get_data()
    page_title("Quản lý doanh nghiệp đối tác", "Admin xem trạng thái pháp lý, số lượt kết nối và thông tin tài khoản đã cấp. Không hiển thị doanh thu.")
    for company in data["companies"]:
        with st.expander(f"{company['name']} — {company.get('legal_status')}"):
            c1, c2 = st.columns(2)
            with c1:
                st.write(f"**Mã số thuế:** {company.get('tax_code')}")
                st.write(f"**Đại diện:** {company.get('representative')}")
                st.write(f"**SĐT:** {company.get('phone')}")
                st.write(f"**Email liên hệ:** {company.get('email')}")
                st.write(f"**Địa chỉ:** {company.get('address')}")
            with c2:
                st.write(f"**Nhóm dịch vụ:** {', '.join(company.get('service_categories', []))}")
                st.write(f"**Số lượt kết nối:** {company_connection_count(data, company['id'])}")
                st.write(f"**Trạng thái tài khoản:** {company.get('account_status')}")
                st.caption(f"Email đăng nhập: {company.get('last_generated_email') or company.get('email')}. Mật khẩu không được lưu dạng plaintext.")


def admin_complaints():
    data = get_data()
    page_title("Xử lý khiếu nại", "FixHome theo dõi khiếu nại và làm việc với doanh nghiệp đối tác liên quan.")
    if not data["complaints"]:
        st.info("Chưa có khiếu nại.")
        return
    for complaint in sorted(data["complaints"], key=lambda c: c.get("created_at", ""), reverse=True):
        with st.expander(f"{complaint['title']} — {complaint['status']}"):
            st.write(f"**Khách hàng:** {complaint.get('customer_name')}")
            st.write(f"**Doanh nghiệp:** {complaint.get('company_name') or 'Chưa xác định'}")
            st.write(f"**Mã đơn:** {complaint.get('order_id')}")
            st.write(f"**Nội dung:** {complaint.get('content')}")
            note = st.text_area("Ghi chú xử lý", value=complaint.get("admin_note", ""), key=f"cmp_note_{complaint['id']}")
            status = st.selectbox("Trạng thái", ["Khiếu nại mới", "Đang làm việc với doanh nghiệp", "Đã xử lý"], index=["Khiếu nại mới", "Đang làm việc với doanh nghiệp", "Đã xử lý"].index(complaint.get("status", "Khiếu nại mới")), key=f"cmp_status_{complaint['id']}")
            if st.button("Cập nhật khiếu nại", key=f"update_cmp_{complaint['id']}", type="primary"):
                complaint["admin_note"] = note.strip()
                complaint["status"] = status
                complaint["updated_at"] = now_iso()
                persist()
                st.success("Đã cập nhật khiếu nại.")
                st.rerun()


def admin_demo_data():
    page_title("Dữ liệu demo")
    st.warning("Chức năng này chỉ dành cho prototype. Khi bấm reset, dữ liệu local trong file data/db.json sẽ được tạo lại.")
    if st.button("Reset dữ liệu demo", type="primary"):
        reset_db()
        st.session_state.db = load_db()
        st.success("Đã reset dữ liệu demo.")
        st.rerun()


def company_app():
    data = get_data()
    user = current_user()
    company = get_company(data, user.get("company_id"))
    if not company:
        st.error("Tài khoản này chưa được liên kết với doanh nghiệp.")
        return
    nav = st.sidebar.radio(
        "Menu doanh nghiệp",
        ["Tổng quan", "Đơn được kết nối", "Quản lý kỹ thuật viên", "Doanh thu của doanh nghiệp", "Tài khoản"],
    )
    if nav == "Tổng quan":
        company_dashboard(company)
    elif nav == "Đơn được kết nối":
        company_orders_page(company)
    elif nav == "Quản lý kỹ thuật viên":
        company_technician_management(company)
    elif nav == "Doanh thu của doanh nghiệp":
        company_revenue_page(company)
    else:
        account_page(user)


def company_dashboard(company):
    data = get_data()
    hero(
        f"Khu vực doanh nghiệp: {company['name']}",
        "Doanh nghiệp nhận yêu cầu do FixHome kết nối, báo giá, phân công kỹ thuật viên nội bộ và theo dõi doanh thu của chính doanh nghiệp.",
        ["Tự quản lý kỹ thuật viên", "Tự báo giá", "Chỉ xem doanh thu của mình"],
    )
    orders = [o for o in data["orders"] if o.get("company_id") == company["id"]]
    active = [o for o in orders if o.get("status") != "Hoàn thành"]
    techs = [u for u in data["users"] if u.get("role") == "technician" and u.get("company_id") == company["id"]]
    c1, c2, c3 = st.columns(3)
    with c1:
        metric_card("Đơn được kết nối", str(len(orders)))
    with c2:
        metric_card("Đơn đang xử lý", str(len(active)))
    with c3:
        metric_card("Kỹ thuật viên nội bộ", str(len(techs)))


def company_orders_page(company):
    data = get_data()
    page_title("Đơn khách hàng được FixHome kết nối", "Doanh nghiệp báo giá, phân công kỹ thuật viên và cập nhật kết quả.")
    orders = [o for o in data["orders"] if o.get("company_id") == company["id"]]
    if not orders:
        st.info("Chưa có đơn được kết nối đến doanh nghiệp của bạn.")
        return
    for order in sorted(orders, key=lambda o: o.get("created_at", ""), reverse=True):
        with st.expander(f"{order['id']} — {order.get('category')} — {order.get('status')}", expanded=order.get("status") != "Hoàn thành"):
            render_order_detail(order, viewer="company")
            company_quote_form(order, company)
            company_assign_technician(order, company)
            company_complete_order(order, company)


def company_quote_form(order: dict, company: dict):
    if order.get("status") not in ["Đã chuyển doanh nghiệp", "Doanh nghiệp đã báo giá"]:
        return
    st.markdown("#### Báo giá cho khách hàng")
    with st.form(f"quote_form_{order['id']}"):
        c1, c2, c3 = st.columns(3)
        with c1:
            quote_min = st.number_input("Giá thấp nhất dự kiến", min_value=0, step=50_000, value=int(order.get("quote_min") or 0))
        with c2:
            quote_max = st.number_input("Giá cao nhất dự kiến", min_value=0, step=50_000, value=int(order.get("quote_max") or 0))
        with c3:
            estimated_arrival = st.text_input("Thời gian có thể đến", value="Trong 2-4 giờ")
        quote_note = st.text_area("Ghi chú báo giá", value=order.get("quote_note", "Báo giá chính xác sau khi kiểm tra thực tế."))
        submitted = st.form_submit_button("Gửi báo giá cho khách hàng", type="primary", use_container_width=True)
    if submitted:
        if quote_max < quote_min:
            st.error("Giá cao nhất phải lớn hơn hoặc bằng giá thấp nhất.")
            return
        order["quote_min"] = int(quote_min)
        order["quote_max"] = int(quote_max)
        order["quote_note"] = f"{quote_note.strip()} Thời gian dự kiến: {estimated_arrival}."
        order["quote_status"] = "Chờ khách hàng xác nhận"
        append_timeline(order, "Doanh nghiệp đã báo giá", f"{company['name']} đã gửi báo giá dự kiến cho khách hàng.")
        persist()
        st.success("Đã gửi báo giá.")
        st.rerun()


def company_assign_technician(order: dict, company: dict):
    data = get_data()
    if order.get("quote_status") != "Khách hàng đã xác nhận" or order.get("status") not in ["Khách hàng xác nhận báo giá", "Đã phân công kỹ thuật viên"]:
        return
    st.markdown("#### Phân công kỹ thuật viên")
    techs = [u for u in data["users"] if u.get("role") == "technician" and u.get("company_id") == company["id"] and u.get("status") == "active"]
    if not techs:
        st.warning("Doanh nghiệp chưa có kỹ thuật viên. Hãy tạo tài khoản kỹ thuật viên trước.")
        return
    labels = {f"{t['name']} — {t.get('phone', '')}": t for t in techs}
    selected = st.selectbox("Chọn kỹ thuật viên", list(labels.keys()), key=f"assign_tech_{order['id']}")
    if st.button("Gửi thông tin đơn cho kỹ thuật viên", key=f"assign_btn_{order['id']}", type="primary", use_container_width=True):
        tech = labels[selected]
        order["technician_id"] = tech["id"]
        order["technician_name"] = tech["name"]
        append_timeline(order, "Đã phân công kỹ thuật viên", f"{company['name']} đã phân công {tech['name']} đến xử lý đơn.")
        persist()
        st.success("Đã phân công kỹ thuật viên.")
        st.rerun()


def company_complete_order(order: dict, company: dict):
    if order.get("status") != "Hoàn thành":
        st.markdown("#### Chốt chi phí sau khi hoàn thành")
        with st.form(f"complete_price_{order['id']}"):
            final_price = st.number_input("Chi phí thực tế cuối cùng", min_value=0, step=50_000, value=int(order.get("final_price") or order.get("quote_min") or 0))
            note = st.text_area("Ghi chú hoàn thành", value="Hoàn thành dịch vụ và bàn giao cho khách hàng.")
            submitted = st.form_submit_button("Xác nhận hoàn thành đơn", type="primary")
        if submitted:
            order["final_price"] = int(final_price)
            order["quote_status"] = "Đã hoàn tất"
            append_timeline(order, "Hoàn thành", note.strip() or "Doanh nghiệp xác nhận hoàn thành dịch vụ.")
            persist()
            st.success("Đơn đã hoàn thành.")
            st.rerun()


def company_technician_management(company):
    data = get_data()
    page_title("Quản lý kỹ thuật viên nội bộ", "Chỉ doanh nghiệp mới có quyền tự tạo tài khoản kỹ thuật viên của chính doanh nghiệp mình.")
    tab_list, tab_create = st.tabs(["Danh sách kỹ thuật viên", "Tạo tài khoản kỹ thuật viên"])
    with tab_list:
        techs = [u for u in data["users"] if u.get("role") == "technician" and u.get("company_id") == company["id"]]
        if not techs:
            st.info("Chưa có kỹ thuật viên.")
        else:
            rows = [{"Họ tên": t["name"], "Email": t["email"], "SĐT": t.get("phone"), "Kỹ năng": t.get("skill_note", ""), "Trạng thái": t.get("status")} for t in techs]
            st.dataframe(pd.DataFrame(rows), use_container_width=True, hide_index=True)
    with tab_create:
        with st.form("create_technician_form"):
            name = st.text_input("Họ tên kỹ thuật viên")
            email = st.text_input("Email đăng nhập")
            phone = st.text_input("Số điện thoại")
            skill_note = st.text_area("Ghi chú kỹ năng/chuyên môn", placeholder="Ví dụ: sửa máy lạnh, vệ sinh máy lạnh, điện dân dụng...")
            submitted = st.form_submit_button("Tạo tài khoản kỹ thuật viên", type="primary", use_container_width=True)
        if submitted:
            if not name or not email or not phone:
                st.error("Vui lòng nhập họ tên, email và số điện thoại.")
            else:
                try:
                    tech, raw_password = create_technician_for_company(
                        data,
                        company_id=company["id"],
                        name=name,
                        email=email,
                        phone=phone,
                        skill_note=skill_note,
                    )
                    persist()
                    st.success("Đã tạo tài khoản kỹ thuật viên. Doanh nghiệp tự bàn giao thông tin này cho nhân sự của mình.")
                    st.code(f"Email đăng nhập: {tech['email']}\nMật khẩu tạm thời: {raw_password}", language="text")
                except ValueError as e:
                    st.error(str(e))


def company_revenue_page(company):
    data = get_data()
    page_title("Doanh thu của doanh nghiệp", "Chỉ tài khoản doanh nghiệp mới xem được doanh thu của chính doanh nghiệp đó. Admin FixHome không có trang doanh thu này.")
    revenue = company_revenue(data, company["id"])
    completed = [o for o in data["orders"] if o.get("company_id") == company["id"] and o.get("status") == "Hoàn thành"]
    c1, c2, c3 = st.columns(3)
    with c1:
        metric_card("Doanh thu hoàn thành", format_vnd(revenue), "Từ các đơn đã chốt chi phí")
    with c2:
        metric_card("Đơn hoàn thành", str(len(completed)))
    with c3:
        avg = int(revenue / len(completed)) if completed else 0
        metric_card("Giá trị trung bình/đơn", format_vnd(avg) if avg else "0đ")
    if completed:
        rows = []
        for o in completed:
            rows.append({
                "Mã đơn": o["id"],
                "Khách hàng": o.get("customer_name"),
                "Dịch vụ": ", ".join(o.get("service_names", [])),
                "Hoàn thành": o.get("updated_at"),
                "Doanh thu": format_vnd(o.get("final_price") or 0),
            })
        st.dataframe(pd.DataFrame(rows), use_container_width=True, hide_index=True)


def technician_app():
    data = get_data()
    user = current_user()
    company = get_company(data, user.get("company_id"))
    nav = st.sidebar.radio("Menu kỹ thuật viên", ["Lịch làm việc", "Cập nhật tiến trình", "Tài khoản"])
    if nav == "Tài khoản":
        account_page(user)
        return
    orders = [o for o in data["orders"] if o.get("technician_id") == user["id"]]
    if nav == "Lịch làm việc":
        hero(
            "Lịch làm việc kỹ thuật viên",
            f"Bạn nhận đơn từ doanh nghiệp {company['name'] if company else ''}. FixHome không trực tiếp tuyển dụng hoặc điều phối bạn ngoài doanh nghiệp.",
            ["Nhận đơn từ doanh nghiệp", "Cập nhật trạng thái", "Minh bạch tiến trình"],
        )
        if not orders:
            st.info("Chưa có đơn được doanh nghiệp phân công.")
            return
        rows = []
        for o in orders:
            rows.append({
                "Mã đơn": o["id"],
                "Khách hàng": o.get("customer_name"),
                "Dịch vụ": ", ".join(o.get("service_names", [])),
                "Lịch hẹn": o.get("scheduled_time"),
                "Địa chỉ": o.get("address"),
                "Trạng thái": o.get("status"),
            })
        st.dataframe(pd.DataFrame(rows), use_container_width=True, hide_index=True)
    else:
        page_title("Cập nhật tiến trình công việc")
        if not orders:
            st.info("Chưa có đơn được phân công.")
            return
        for order in sorted(orders, key=lambda o: o.get("scheduled_time", "")):
            with st.expander(f"{order['id']} — {order.get('status')}", expanded=order.get("status") != "Hoàn thành"):
                render_order_detail(order, viewer="technician")
                allowed = [s for s in STATUS_FLOW if s not in ["Chờ phân phối", "Đã chuyển doanh nghiệp", "Doanh nghiệp đã báo giá", "Khách hàng xác nhận báo giá"]]
                current_status = order.get("status")
                current_index = allowed.index(current_status) if current_status in allowed else 0
                new_status = st.selectbox("Cập nhật trạng thái", allowed, index=current_index, key=f"tech_status_{order['id']}")
                note = st.text_area("Ghi chú cho khách hàng/doanh nghiệp", key=f"tech_note_{order['id']}")
                if st.button("Lưu cập nhật", key=f"tech_update_{order['id']}", type="primary"):
                    append_timeline(order, new_status, note.strip() or f"Kỹ thuật viên cập nhật trạng thái: {new_status}.")
                    persist()
                    st.success("Đã cập nhật tiến trình.")
                    st.rerun()


def main():
    # Nạp snapshot mới ở đầu mỗi lần Streamlit rerun để giảm dữ liệu stale giữa nhiều session.
    st.session_state.db = load_db()
    setup_page()
    sidebar_auth()
    user = current_user()
    if not user:
        guest_home()
        return
    if user["role"] == "admin":
        admin_app()
    elif user["role"] == "customer":
        customer_app()
    elif user["role"] == "company":
        company_app()
    elif user["role"] == "technician":
        technician_app()
    else:
        st.error("Vai trò tài khoản không hợp lệ.")


if __name__ == "__main__":
    main()
