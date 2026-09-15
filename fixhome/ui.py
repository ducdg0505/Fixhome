from __future__ import annotations

import streamlit as st

from fixhome.data.services import format_vnd


def setup_page():
    st.set_page_config(
        page_title="FixHome Cần Thơ",
        page_icon="🏠",
        layout="wide",
        initial_sidebar_state="expanded",
    )
    inject_css()


def inject_css():
    st.markdown(
        """
<style>
:root {
    --fix-primary: #0f766e;
    --fix-primary-dark: #115e59;
    --fix-accent: #f59e0b;
    --fix-bg: #f8fafc;
    --fix-card: #ffffff;
    --fix-text: #0f172a;
    --fix-muted: #475569;
    --fix-border: #dbe4ee;
    --fix-soft: #ecfeff;
    --fix-danger: #b91c1c;
    --fix-success: #047857;
}

/* Nền tổng thể */
.stApp {
    background: linear-gradient(180deg, #f8fafc 0%, #eef7f7 100%) !important;
    color: var(--fix-text) !important;
}

/* Cưỡng chế màu chữ dễ đọc trên toàn bộ app */
html, body, [class*="css"], .stApp, .stMarkdown, .stText, p, span, div, label,
h1, h2, h3, h4, h5, h6, li, small, strong, em {
    color: var(--fix-text) !important;
}

/* Sidebar */
section[data-testid="stSidebar"] {
    background: #0f172a !important;
    color: #ffffff !important;
}
section[data-testid="stSidebar"] * {
    color: #ffffff !important;
}
section[data-testid="stSidebar"] div[data-testid="stMarkdownContainer"] p,
section[data-testid="stSidebar"] label,
section[data-testid="stSidebar"] span {
    color: #ffffff !important;
}
section[data-testid="stSidebar"] input {
    background: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #cbd5e1 !important;
}
section[data-testid="stSidebar"] button {
    color: #ffffff !important;
    background: var(--fix-primary) !important;
    border: 1px solid rgba(255,255,255,0.25) !important;
}

/* Form controls */
input, textarea, select,
.stTextInput input, .stTextArea textarea, .stNumberInput input, .stDateInput input,
.stTimeInput input, div[data-baseweb="select"] > div, div[data-baseweb="input"] input {
    background-color: #ffffff !important;
    color: #0f172a !important;
    border-color: #cbd5e1 !important;
    caret-color: #0f172a !important;
}
input::placeholder, textarea::placeholder {
    color: #64748b !important;
    opacity: 1 !important;
}
.stSelectbox label, .stMultiSelect label, .stTextInput label, .stTextArea label,
.stDateInput label, .stTimeInput label, .stRadio label, .stCheckbox label,
.stFileUploader label, .stNumberInput label {
    color: #0f172a !important;
    font-weight: 700 !important;
}

/* Multiselect tags */
span[data-baseweb="tag"] {
    background-color: #d1fae5 !important;
    color: #064e3b !important;
    border: 1px solid #99f6e4 !important;
}
span[data-baseweb="tag"] span {
    color: #064e3b !important;
}

/* Buttons - không để chữ trùng màu nền */
.stButton > button, .stDownloadButton > button, button {
    border-radius: 12px !important;
    border: 1px solid #cbd5e1 !important;
    background: #ffffff !important;
    color: #0f172a !important;
    font-weight: 800 !important;
    min-height: 2.7rem !important;
    box-shadow: 0 3px 12px rgba(15,23,42,0.06) !important;
}
.stButton > button:hover, .stDownloadButton > button:hover, button:hover {
    border-color: var(--fix-primary) !important;
    background: #ecfeff !important;
    color: #0f172a !important;
}
button[kind="primary"], .stButton > button[kind="primary"] {
    background: linear-gradient(135deg, #0f766e, #0ea5e9) !important;
    color: #ffffff !important;
    border: none !important;
}
button[kind="primary"] *, .stButton > button[kind="primary"] * {
    color: #ffffff !important;
}

/* Tabs */
button[data-baseweb="tab"] {
    background: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px 12px 0 0 !important;
    margin-right: 4px !important;
}
button[data-baseweb="tab"][aria-selected="true"] {
    background: #ccfbf1 !important;
    color: #0f172a !important;
    border-bottom: 3px solid var(--fix-primary) !important;
}
button[data-baseweb="tab"] p, button[data-baseweb="tab"] span {
    color: #0f172a !important;
}

/* Cards */
.fix-card {
    background: var(--fix-card) !important;
    color: var(--fix-text) !important;
    border: 1px solid var(--fix-border) !important;
    border-radius: 18px !important;
    padding: 18px 20px !important;
    box-shadow: 0 10px 30px rgba(15,23,42,0.06) !important;
    margin-bottom: 16px !important;
}
.fix-card * { color: var(--fix-text) !important; }
.fix-card-muted {
    color: var(--fix-muted) !important;
    font-size: 0.95rem !important;
}
.hero {
    background: linear-gradient(135deg, #0f766e 0%, #0ea5e9 55%, #f59e0b 140%) !important;
    border-radius: 26px !important;
    padding: 34px 34px !important;
    box-shadow: 0 20px 45px rgba(15,118,110,0.22) !important;
    margin-bottom: 22px !important;
}
.hero, .hero * { color: #ffffff !important; }
.hero .subtext { color: #ecfeff !important; font-size: 1.08rem !important; line-height: 1.6 !important; }
.badge {
    display: inline-block !important;
    padding: 7px 12px !important;
    border-radius: 999px !important;
    background: #ccfbf1 !important;
    color: #115e59 !important;
    font-weight: 800 !important;
    font-size: 0.86rem !important;
    margin: 3px 4px 3px 0 !important;
    border: 1px solid #99f6e4 !important;
}
.badge-warning {
    background: #fef3c7 !important;
    color: #92400e !important;
    border-color: #fde68a !important;
}
.badge-danger {
    background: #fee2e2 !important;
    color: #991b1b !important;
    border-color: #fecaca !important;
}
.badge-info {
    background: #dbeafe !important;
    color: #1e3a8a !important;
    border-color: #bfdbfe !important;
}
.metric-card {
    background: #ffffff !important;
    border: 1px solid #dbe4ee !important;
    border-radius: 18px !important;
    padding: 18px !important;
    min-height: 115px !important;
    box-shadow: 0 10px 26px rgba(15,23,42,0.05) !important;
}
.metric-card .value {
    font-size: 1.75rem !important;
    font-weight: 900 !important;
    color: #0f766e !important;
}
.metric-card .label {
    color: #475569 !important;
    font-weight: 700 !important;
}
.price-card {
    background: #ffffff !important;
    border: 1px solid #dbe4ee !important;
    border-left: 6px solid #0f766e !important;
    border-radius: 16px !important;
    padding: 15px 16px !important;
    margin-bottom: 12px !important;
}
.price-card * { color: #0f172a !important; }
.timeline-item {
    border-left: 4px solid #0f766e !important;
    padding: 8px 0 10px 16px !important;
    margin-left: 6px !important;
    background: #ffffff !important;
}
.timeline-time { color: #64748b !important; font-size: 0.9rem !important; font-weight: 700 !important; }
.timeline-status { color: #0f766e !important; font-weight: 900 !important; }

/* Dataframe/table */
div[data-testid="stDataFrame"] *, table, thead, tbody, tr, th, td {
    color: #0f172a !important;
}
thead tr th {
    background: #e0f2fe !important;
    color: #0f172a !important;
}
tbody tr:nth-child(even) td {
    background: #f8fafc !important;
}

/* Alerts */
div[data-testid="stAlert"] {
    background: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 14px !important;
}
div[data-testid="stAlert"] * {
    color: #0f172a !important;
}

/* Expander */
.streamlit-expanderHeader {
    background: #ffffff !important;
    color: #0f172a !important;
    border-radius: 12px !important;
    font-weight: 800 !important;
}
.streamlit-expanderContent {
    background: #ffffff !important;
    color: #0f172a !important;
}

/* File uploader */
section[data-testid="stFileUploaderDropzone"] {
    background: #ffffff !important;
    border: 2px dashed #94a3b8 !important;
    color: #0f172a !important;
}
section[data-testid="stFileUploaderDropzone"] * {
    color: #0f172a !important;
}

hr { border: none; border-top: 1px solid #e2e8f0; margin: 1rem 0; }
.block-container { padding-top: 1.5rem !important; }
</style>
        """,
        unsafe_allow_html=True,
    )


def hero(title: str, subtitle: str, badges: list[str] | None = None):
    badge_html = "".join([f"<span class='badge'>{b}</span>" for b in (badges or [])])
    st.markdown(
        f"""
<div class="hero">
  <div style="font-size:0.95rem;font-weight:900;letter-spacing:0.08em;text-transform:uppercase;opacity:0.95;">FixHome Cần Thơ</div>
  <h1 style="margin:6px 0 10px 0;font-size:2.35rem;line-height:1.15;">{title}</h1>
  <div class="subtext">{subtitle}</div>
  <div style="margin-top:16px;">{badge_html}</div>
</div>
        """,
        unsafe_allow_html=True,
    )


def card(title: str, body: str, badge: str | None = None):
    badge_html = f"<span class='badge'>{badge}</span>" if badge else ""
    st.markdown(
        f"""
<div class="fix-card">
  {badge_html}
  <h3 style="margin:6px 0 8px 0;">{title}</h3>
  <div class="fix-card-muted">{body}</div>
</div>
        """,
        unsafe_allow_html=True,
    )


def metric_card(label: str, value: str, note: str = ""):
    st.markdown(
        f"""
<div class="metric-card">
  <div class="label">{label}</div>
  <div class="value">{value}</div>
  <div style="color:#64748b !important;font-weight:650;">{note}</div>
</div>
        """,
        unsafe_allow_html=True,
    )


def price_card(name: str, price: str, description: str, category: str = ""):
    st.markdown(
        f"""
<div class="price-card">
  <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
    <div>
      <div style="font-weight:900;font-size:1.05rem;">{name}</div>
      <div style="color:#475569 !important;margin-top:4px;">{description}</div>
    </div>
    <div style="white-space:nowrap;font-weight:900;color:#0f766e !important;">{price}</div>
  </div>
  {f'<div style="margin-top:8px;"><span class="badge badge-info">{category}</span></div>' if category else ''}
</div>
        """,
        unsafe_allow_html=True,
    )


def status_badge(status: str):
    danger = ["Đã hủy", "Từ chối", "Khiếu nại mới"]
    warning = ["Chờ phân phối", "Chờ kiểm tra pháp lý", "Chờ khách hàng xác nhận"]
    cls = "badge"
    if status in danger:
        cls += " badge-danger"
    elif status in warning:
        cls += " badge-warning"
    else:
        cls += " badge-info"
    return f"<span class='{cls}'>{status}</span>"


def render_timeline(timeline: list[dict]):
    if not timeline:
        st.info("Chưa có tiến trình.")
        return
    for item in timeline:
        st.markdown(
            f"""
<div class="timeline-item">
  <div class="timeline-time">{item.get('time', '')}</div>
  <div class="timeline-status">{item.get('status', '')}</div>
  <div style="color:#475569 !important;">{item.get('note', '')}</div>
</div>
            """,
            unsafe_allow_html=True,
        )


def vnd(value) -> str:
    if value in [None, "", 0]:
        return "Chưa có"
    return format_vnd(int(value))
