from __future__ import annotations

import os
from dataclasses import dataclass
from typing import Any

try:
    from PIL import Image
except Exception:  # pragma: no cover
    Image = None


@dataclass
class DiagnosisResult:
    device_type: str
    issue_group: str
    summary: str
    risk_level: str
    suggested_service_category: str
    confidence: float
    safety_note: str
    model_name: str


class BaseDiagnosisModel:
    """Interface để sau này thay bằng model AI thật.

    Ứng dụng chỉ gọi hàm `analyze()`. Khi có model chuyên chẩn đoán lỗi thiết bị,
    chỉ cần tạo class mới kế thừa BaseDiagnosisModel và giữ nguyên format trả về.
    """

    model_name = "base-diagnosis-model"

    def analyze(self, description: str, image: Any | None = None) -> DiagnosisResult:
        raise NotImplementedError


class RuleBasedVietnameseDiagnosis(BaseDiagnosisModel):
    """Model luật đơn giản cho MVP, chạy offline, ổn định khi demo trên máy local."""

    model_name = "FixHome Rule-based Diagnosis v1"

    KEYWORDS = [
        ("máy lạnh", "Điện lạnh", "Máy lạnh", ["không lạnh", "kém lạnh", "chảy nước", "báo lỗi", "đóng tuyết", "hôi", "mùi"]),
        ("điều hòa", "Điện lạnh", "Máy lạnh", ["không lạnh", "kém lạnh", "chảy nước", "báo lỗi", "đóng tuyết", "hôi", "mùi"]),
        ("tủ lạnh", "Điện lạnh", "Tủ lạnh", ["không lạnh", "đóng tuyết", "rò nước", "kêu", "hôi", "không đông"]),
        ("tủ đông", "Điện lạnh", "Tủ đông / tủ mát", ["không đông", "không lạnh", "kêu", "hao điện"]),
        ("máy giặt", "Điện gia dụng", "Máy giặt", ["không vắt", "không xả", "rung", "báo lỗi", "không cấp nước"]),
        ("máy nước nóng", "Điện gia dụng", "Máy nước nóng", ["không nóng", "rò điện", "rò nước", "tự ngắt"]),
        ("lò vi sóng", "Điện gia dụng", "Lò vi sóng", ["không nóng", "không quay", "mất nguồn", "tia lửa"]),
        ("nồi cơm", "Điện gia dụng", "Nồi cơm điện", ["không chín", "cơm sống", "không giữ ấm", "mất nguồn"]),
        ("quạt", "Điện gia dụng", "Quạt điện", ["không quay", "quay yếu", "kêu", "hỏng điều khiển"]),
        ("điện", "Điện - nước", "Hệ thống điện dân dụng", ["chập", "mất điện", "ổ cắm", "công tắc", "aptomat", "tia lửa"]),
        ("nước", "Điện - nước", "Hệ thống nước dân dụng", ["rò", "nghẹt", "bồn cầu", "vòi", "ống nước"]),
        ("camera", "Lắp đặt thiết bị", "Camera an ninh", ["mất hình", "không xem", "lắp", "wifi"]),
    ]

    DANGER_WORDS = ["cháy", "khét", "tia lửa", "rò điện", "giật", "nổ", "bốc khói", "chập điện"]

    def analyze(self, description: str, image: Any | None = None) -> DiagnosisResult:
        text = (description or "").strip().lower()
        device_type = "Thiết bị chưa xác định"
        category = "Không rõ lỗi"
        matched_issues = []
        score = 0.25

        for key, cat, device, issues in self.KEYWORDS:
            if key in text:
                device_type = device
                category = cat
                score += 0.35
                for issue in issues:
                    if issue in text:
                        matched_issues.append(issue)
                        score += 0.08
                break

        if not matched_issues:
            for _, cat, device, issues in self.KEYWORDS:
                issue_hits = [issue for issue in issues if issue in text]
                if issue_hits:
                    device_type = device
                    category = cat
                    matched_issues.extend(issue_hits)
                    score += 0.25 + 0.06 * len(issue_hits)
                    break

        has_image = image is not None
        if has_image:
            score += 0.1

        risk_level = "Trung bình"
        safety_note = "Không tự tháo thiết bị nếu chưa ngắt nguồn điện và chưa có kỹ thuật viên kiểm tra."
        if any(word in text for word in self.DANGER_WORDS):
            risk_level = "Cao"
            safety_note = "Nên ngắt nguồn điện, không tiếp tục sử dụng thiết bị và chờ kỹ thuật viên kiểm tra."
            score += 0.1
        elif not text:
            risk_level = "Thấp"

        issue_group = ", ".join(sorted(set(matched_issues))) if matched_issues else "Cần kiểm tra trực tiếp"
        summary = (
            f"Hệ thống ghi nhận thiết bị: {device_type}. Nhóm lỗi sơ bộ: {issue_group}. "
            f"Yêu cầu nên được chuyển đến nhóm dịch vụ: {category}."
        )
        if has_image:
            summary += " Ảnh thiết bị đã được đính kèm để doanh nghiệp đối tác đối chiếu trước khi báo giá."

        return DiagnosisResult(
            device_type=device_type,
            issue_group=issue_group,
            summary=summary,
            risk_level=risk_level,
            suggested_service_category=category,
            confidence=min(score, 0.92),
            safety_note=safety_note,
            model_name=self.model_name,
        )


class OptionalBLIPDiagnosis(BaseDiagnosisModel):
    """Model vision-language tùy chọn.

    Chỉ dùng khi người chạy đã cài requirements-ai.txt và bật biến môi trường
    FIXHOME_ENABLE_VISION_AI=1. Nếu model không tải được, hệ thống tự fallback về
    RuleBasedVietnameseDiagnosis để demo không bị lỗi.
    """

    model_name = "BLIP image captioning + FixHome rule-based classifier"

    def __init__(self):
        self.fallback = RuleBasedVietnameseDiagnosis()
        self.ready = False
        self.processor = None
        self.model = None
        if os.getenv("FIXHOME_ENABLE_VISION_AI") != "1":
            return
        try:
            from transformers import BlipForConditionalGeneration, BlipProcessor

            model_name = os.getenv("FIXHOME_VISION_MODEL", "Salesforce/blip-image-captioning-base")
            self.processor = BlipProcessor.from_pretrained(model_name)
            self.model = BlipForConditionalGeneration.from_pretrained(model_name)
            self.ready = True
        except Exception:
            self.ready = False

    def _caption(self, image: Any | None) -> str:
        if not self.ready or image is None or self.processor is None or self.model is None:
            return ""
        try:
            if Image is not None and not isinstance(image, Image.Image):
                image = Image.open(image).convert("RGB")
            elif hasattr(image, "convert"):
                image = image.convert("RGB")
            inputs = self.processor(image, return_tensors="pt")
            output = self.model.generate(**inputs, max_new_tokens=35)
            return self.processor.decode(output[0], skip_special_tokens=True)
        except Exception:
            return ""

    def analyze(self, description: str, image: Any | None = None) -> DiagnosisResult:
        caption = self._caption(image)
        combined = description
        if caption:
            combined = f"{description}\nMô tả ảnh từ model: {caption}"
        result = self.fallback.analyze(combined, image=image)
        result.model_name = self.model_name if self.ready else self.fallback.model_name
        if caption:
            result.summary += f" Ghi nhận từ ảnh: {caption}."
        return result


def get_diagnosis_model() -> BaseDiagnosisModel:
    return OptionalBLIPDiagnosis()
