"""Danh mục dịch vụ và giá tham khảo cho MVP FixHome tại Cần Thơ.

Giá dưới đây dùng cho demo/prototype. Khi đưa vào vận hành cần thay bằng bảng giá
được duyệt bởi từng doanh nghiệp đối tác và có điều kiện áp dụng rõ ràng.
"""

SERVICE_CATEGORIES = {
    "Điện lạnh": [
        {
            "id": "ac_clean_wall",
            "name": "Vệ sinh máy lạnh treo tường",
            "unit": "máy",
            "min_price": 150_000,
            "max_price": 250_000,
            "description": "Vệ sinh dàn lạnh, dàn nóng, kiểm tra nước xả và vận hành cơ bản.",
            "common_issues": ["Máy lạnh yếu", "Có mùi", "Chảy nước", "Bám bụi"],
        },
        {
            "id": "ac_repair_general",
            "name": "Sửa máy lạnh không lạnh / kém lạnh",
            "unit": "lần",
            "min_price": 250_000,
            "max_price": 850_000,
            "description": "Kiểm tra block, quạt, gas, board, cảm biến và báo giá trước khi sửa.",
            "common_issues": ["Không lạnh", "Kém lạnh", "Tự tắt", "Báo lỗi"],
        },
        {
            "id": "ac_gas_check",
            "name": "Kiểm tra và nạp gas máy lạnh",
            "unit": "máy",
            "min_price": 200_000,
            "max_price": 650_000,
            "description": "Kiểm tra rò rỉ, áp suất gas và nạp gas theo tình trạng thực tế.",
            "common_issues": ["Thiếu gas", "Dàn lạnh đóng tuyết", "Lạnh yếu"],
        },
        {
            "id": "ac_install",
            "name": "Lắp đặt máy lạnh",
            "unit": "máy",
            "min_price": 350_000,
            "max_price": 1_200_000,
            "description": "Lắp đặt máy lạnh, đi ống đồng, kiểm tra điện và vận hành thử.",
            "common_issues": ["Lắp mới", "Dời vị trí", "Thay máy"],
        },
        {
            "id": "fridge_repair",
            "name": "Sửa tủ lạnh",
            "unit": "lần",
            "min_price": 250_000,
            "max_price": 1_200_000,
            "description": "Xử lý tủ không lạnh, đóng tuyết, kêu to, rò nước, hỏng ron hoặc board.",
            "common_issues": ["Không lạnh", "Đóng tuyết", "Kêu to", "Rò nước"],
        },
        {
            "id": "freezer_repair",
            "name": "Sửa tủ đông / tủ mát",
            "unit": "lần",
            "min_price": 300_000,
            "max_price": 1_500_000,
            "description": "Kiểm tra hệ thống làm lạnh, block, gas, thermostat, quạt và board.",
            "common_issues": ["Không đông", "Tủ mát yếu", "Hao điện", "Kêu lớn"],
        },
    ],
    "Điện gia dụng": [
        {
            "id": "washer_repair",
            "name": "Sửa máy giặt",
            "unit": "lần",
            "min_price": 250_000,
            "max_price": 1_000_000,
            "description": "Kiểm tra lỗi không vắt, không xả, rung mạnh, báo lỗi hoặc không cấp nước.",
            "common_issues": ["Không vắt", "Không xả", "Rung mạnh", "Báo lỗi"],
        },
        {
            "id": "washer_clean",
            "name": "Vệ sinh máy giặt",
            "unit": "máy",
            "min_price": 250_000,
            "max_price": 450_000,
            "description": "Vệ sinh lồng giặt, khử mùi, loại bỏ cặn bẩn và kiểm tra cơ bản.",
            "common_issues": ["Có mùi", "Cặn bẩn", "Giặt không sạch"],
        },
        {
            "id": "water_heater_repair",
            "name": "Sửa máy nước nóng",
            "unit": "lần",
            "min_price": 250_000,
            "max_price": 900_000,
            "description": "Kiểm tra nguồn điện, chống giật, thanh đốt, cảm biến nhiệt và rò nước.",
            "common_issues": ["Không nóng", "Rò điện", "Rò nước", "Tự ngắt"],
        },
        {
            "id": "microwave_repair",
            "name": "Sửa lò vi sóng",
            "unit": "lần",
            "min_price": 200_000,
            "max_price": 750_000,
            "description": "Kiểm tra nguồn, mâm xoay, đèn, board điều khiển và hệ thống phát nhiệt.",
            "common_issues": ["Không nóng", "Không quay", "Mất nguồn", "Tia lửa"],
        },
        {
            "id": "rice_cooker_repair",
            "name": "Sửa nồi cơm điện / nồi áp suất",
            "unit": "lần",
            "min_price": 120_000,
            "max_price": 450_000,
            "description": "Kiểm tra nguồn, mâm nhiệt, cảm biến, nắp, gioăng và bo điều khiển.",
            "common_issues": ["Không vào điện", "Cơm sống", "Không giữ ấm", "Báo lỗi"],
        },
        {
            "id": "fan_repair",
            "name": "Sửa quạt điện / quạt trần",
            "unit": "lần",
            "min_price": 100_000,
            "max_price": 450_000,
            "description": "Kiểm tra tụ, motor, công tắc, điều khiển, bạc đạn và lắp đặt cơ bản.",
            "common_issues": ["Không quay", "Quay yếu", "Kêu lớn", "Hỏng remote"],
        },
    ],
    "Điện - nước": [
        {
            "id": "electric_repair",
            "name": "Sửa điện dân dụng",
            "unit": "lần",
            "min_price": 150_000,
            "max_price": 800_000,
            "description": "Xử lý chập điện, mất điện cục bộ, ổ cắm, công tắc, đèn và aptomat.",
            "common_issues": ["Mất điện", "Chập điện", "Ổ cắm hỏng", "Đèn không sáng"],
        },
        {
            "id": "light_install",
            "name": "Lắp đèn, quạt, công tắc, ổ cắm",
            "unit": "hạng mục",
            "min_price": 120_000,
            "max_price": 600_000,
            "description": "Lắp đặt thiết bị điện cơ bản trong gia đình, phòng trọ và cửa hàng nhỏ.",
            "common_issues": ["Lắp mới", "Thay ổ cắm", "Thay công tắc", "Lắp quạt"],
        },
        {
            "id": "water_repair",
            "name": "Sửa nước dân dụng",
            "unit": "lần",
            "min_price": 150_000,
            "max_price": 850_000,
            "description": "Xử lý rò nước, nghẹt ống, thay vòi, bồn cầu, lavabo và bơm nước.",
            "common_issues": ["Rò nước", "Nghẹt ống", "Vòi hỏng", "Bồn cầu lỗi"],
        },
        {
            "id": "pump_repair",
            "name": "Sửa máy bơm nước",
            "unit": "lần",
            "min_price": 180_000,
            "max_price": 900_000,
            "description": "Kiểm tra motor, tụ, cánh bơm, phao điện, đường nước và áp lực.",
            "common_issues": ["Không lên nước", "Kêu lớn", "Yếu áp", "Tự ngắt"],
        },
    ],
    "Lắp đặt thiết bị": [
        {
            "id": "camera_install",
            "name": "Lắp đặt camera an ninh",
            "unit": "camera",
            "min_price": 250_000,
            "max_price": 1_000_000,
            "description": "Lắp camera, cấu hình xem qua điện thoại và kiểm tra góc quan sát.",
            "common_issues": ["Lắp mới", "Mất hình", "Không xem từ xa"],
        },
        {
            "id": "smart_device_install",
            "name": "Lắp thiết bị thông minh",
            "unit": "thiết bị",
            "min_price": 150_000,
            "max_price": 650_000,
            "description": "Lắp khóa thông minh, cảm biến, công tắc Wi-Fi, thiết bị điều khiển từ xa.",
            "common_issues": ["Cài app", "Kết nối Wi-Fi", "Lắp mới", "Cấu hình"],
        },
        {
            "id": "appliance_install",
            "name": "Lắp đặt thiết bị gia dụng",
            "unit": "thiết bị",
            "min_price": 180_000,
            "max_price": 850_000,
            "description": "Lắp máy giặt, máy rửa chén, bếp điện, máy lọc nước và thiết bị nhà bếp.",
            "common_issues": ["Lắp máy giặt", "Lắp bếp", "Lắp máy lọc nước"],
        },
    ],
    "Không rõ lỗi": [
        {
            "id": "unknown_diagnosis",
            "name": "Chẩn đoán thiết bị chưa rõ lỗi",
            "unit": "yêu cầu",
            "min_price": 120_000,
            "max_price": 350_000,
            "description": "Khách hàng gửi ảnh và mô tả để FixHome phân loại sơ bộ trước khi chuyển doanh nghiệp.",
            "common_issues": ["Không rõ lỗi", "Cần kiểm tra", "Cần báo giá"],
        }
    ],
}

STATUS_FLOW = [
    "Chờ phân phối",
    "Đã chuyển doanh nghiệp",
    "Doanh nghiệp đã báo giá",
    "Khách hàng xác nhận báo giá",
    "Đã phân công kỹ thuật viên",
    "Kỹ thuật viên đã nhận việc",
    "Đang di chuyển",
    "Đang kiểm tra",
    "Đang sửa chữa",
    "Hoàn thành",
]

SERVICE_ICON = {
    "Điện lạnh": "❄️",
    "Điện gia dụng": "🏠",
    "Điện - nước": "⚡",
    "Lắp đặt thiết bị": "🛠️",
    "Không rõ lỗi": "🤖",
}


def all_services():
    result = []
    for category, services in SERVICE_CATEGORIES.items():
        for service in services:
            item = dict(service)
            item["category"] = category
            item["label"] = f"{service['name']} — {service['min_price']:,}đ đến {service['max_price']:,}đ/{service['unit']}".replace(",", ".")
            result.append(item)
    return result


def get_service(service_id: str):
    for service in all_services():
        if service["id"] == service_id:
            return service
    return None


def format_vnd(value: int | float) -> str:
    return f"{int(value):,}đ".replace(",", ".")


def estimate_services(service_ids: list[str]) -> dict:
    selected = [get_service(sid) for sid in service_ids if get_service(sid)]
    min_total = sum(s["min_price"] for s in selected)
    max_total = sum(s["max_price"] for s in selected)
    return {
        "services": selected,
        "min_total": min_total,
        "max_total": max_total,
        "text": f"{format_vnd(min_total)} - {format_vnd(max_total)}" if selected else "Chưa có dữ liệu",
    }
