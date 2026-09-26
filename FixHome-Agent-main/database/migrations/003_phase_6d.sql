-- FixHome Phase 6D non-destructive migration; MySQL 5.7/8.0 compatible.
-- Production execution remains a separately approved deployment step.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS service_common_issues (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_id BIGINT UNSIGNED NOT NULL,
    issue_label VARCHAR(120) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_service_common_issue_order (service_id, sort_order),
    UNIQUE KEY uq_service_common_issue_label (service_id, issue_label),
    KEY idx_service_common_issue_service (service_id, sort_order, id),
    CONSTRAINT fk_service_common_issue_service FOREIGN KEY (service_id) REFERENCES services (id) ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO service_common_issues(service_id,issue_label,sort_order)
SELECT s.id, 'Máy lạnh yếu', 1 FROM services s WHERE s.code='ac_clean_wall' UNION ALL
SELECT s.id, 'Có mùi', 2 FROM services s WHERE s.code='ac_clean_wall' UNION ALL
SELECT s.id, 'Chảy nước', 3 FROM services s WHERE s.code='ac_clean_wall' UNION ALL
SELECT s.id, 'Bám bụi', 4 FROM services s WHERE s.code='ac_clean_wall' UNION ALL
SELECT s.id, 'Không lạnh', 1 FROM services s WHERE s.code='ac_repair_general' UNION ALL
SELECT s.id, 'Kém lạnh', 2 FROM services s WHERE s.code='ac_repair_general' UNION ALL
SELECT s.id, 'Tự tắt', 3 FROM services s WHERE s.code='ac_repair_general' UNION ALL
SELECT s.id, 'Báo lỗi', 4 FROM services s WHERE s.code='ac_repair_general' UNION ALL
SELECT s.id, 'Thiếu gas', 1 FROM services s WHERE s.code='ac_gas_check' UNION ALL
SELECT s.id, 'Dàn lạnh đóng tuyết', 2 FROM services s WHERE s.code='ac_gas_check' UNION ALL
SELECT s.id, 'Lạnh yếu', 3 FROM services s WHERE s.code='ac_gas_check' UNION ALL
SELECT s.id, 'Lắp mới', 1 FROM services s WHERE s.code='ac_install' UNION ALL
SELECT s.id, 'Dời vị trí', 2 FROM services s WHERE s.code='ac_install' UNION ALL
SELECT s.id, 'Thay máy', 3 FROM services s WHERE s.code='ac_install' UNION ALL
SELECT s.id, 'Không lạnh', 1 FROM services s WHERE s.code='fridge_repair' UNION ALL
SELECT s.id, 'Đóng tuyết', 2 FROM services s WHERE s.code='fridge_repair' UNION ALL
SELECT s.id, 'Kêu to', 3 FROM services s WHERE s.code='fridge_repair' UNION ALL
SELECT s.id, 'Rò nước', 4 FROM services s WHERE s.code='fridge_repair' UNION ALL
SELECT s.id, 'Không đông', 1 FROM services s WHERE s.code='freezer_repair' UNION ALL
SELECT s.id, 'Tủ mát yếu', 2 FROM services s WHERE s.code='freezer_repair' UNION ALL
SELECT s.id, 'Hao điện', 3 FROM services s WHERE s.code='freezer_repair' UNION ALL
SELECT s.id, 'Kêu lớn', 4 FROM services s WHERE s.code='freezer_repair' UNION ALL
SELECT s.id, 'Không vắt', 1 FROM services s WHERE s.code='washer_repair' UNION ALL
SELECT s.id, 'Không xả', 2 FROM services s WHERE s.code='washer_repair' UNION ALL
SELECT s.id, 'Rung mạnh', 3 FROM services s WHERE s.code='washer_repair' UNION ALL
SELECT s.id, 'Báo lỗi', 4 FROM services s WHERE s.code='washer_repair' UNION ALL
SELECT s.id, 'Có mùi', 1 FROM services s WHERE s.code='washer_clean' UNION ALL
SELECT s.id, 'Cặn bẩn', 2 FROM services s WHERE s.code='washer_clean' UNION ALL
SELECT s.id, 'Giặt không sạch', 3 FROM services s WHERE s.code='washer_clean' UNION ALL
SELECT s.id, 'Không nóng', 1 FROM services s WHERE s.code='water_heater_repair' UNION ALL
SELECT s.id, 'Rò điện', 2 FROM services s WHERE s.code='water_heater_repair' UNION ALL
SELECT s.id, 'Rò nước', 3 FROM services s WHERE s.code='water_heater_repair' UNION ALL
SELECT s.id, 'Tự ngắt', 4 FROM services s WHERE s.code='water_heater_repair' UNION ALL
SELECT s.id, 'Không nóng', 1 FROM services s WHERE s.code='microwave_repair' UNION ALL
SELECT s.id, 'Không quay', 2 FROM services s WHERE s.code='microwave_repair' UNION ALL
SELECT s.id, 'Mất nguồn', 3 FROM services s WHERE s.code='microwave_repair' UNION ALL
SELECT s.id, 'Tia lửa', 4 FROM services s WHERE s.code='microwave_repair' UNION ALL
SELECT s.id, 'Không vào điện', 1 FROM services s WHERE s.code='rice_cooker_repair' UNION ALL
SELECT s.id, 'Cơm sống', 2 FROM services s WHERE s.code='rice_cooker_repair' UNION ALL
SELECT s.id, 'Không giữ ấm', 3 FROM services s WHERE s.code='rice_cooker_repair' UNION ALL
SELECT s.id, 'Báo lỗi', 4 FROM services s WHERE s.code='rice_cooker_repair' UNION ALL
SELECT s.id, 'Không quay', 1 FROM services s WHERE s.code='fan_repair' UNION ALL
SELECT s.id, 'Quay yếu', 2 FROM services s WHERE s.code='fan_repair' UNION ALL
SELECT s.id, 'Kêu lớn', 3 FROM services s WHERE s.code='fan_repair' UNION ALL
SELECT s.id, 'Hỏng remote', 4 FROM services s WHERE s.code='fan_repair' UNION ALL
SELECT s.id, 'Mất điện', 1 FROM services s WHERE s.code='electric_repair' UNION ALL
SELECT s.id, 'Chập điện', 2 FROM services s WHERE s.code='electric_repair' UNION ALL
SELECT s.id, 'Ổ cắm hỏng', 3 FROM services s WHERE s.code='electric_repair' UNION ALL
SELECT s.id, 'Đèn không sáng', 4 FROM services s WHERE s.code='electric_repair' UNION ALL
SELECT s.id, 'Lắp mới', 1 FROM services s WHERE s.code='light_install' UNION ALL
SELECT s.id, 'Thay ổ cắm', 2 FROM services s WHERE s.code='light_install' UNION ALL
SELECT s.id, 'Thay công tắc', 3 FROM services s WHERE s.code='light_install' UNION ALL
SELECT s.id, 'Lắp quạt', 4 FROM services s WHERE s.code='light_install' UNION ALL
SELECT s.id, 'Rò nước', 1 FROM services s WHERE s.code='water_repair' UNION ALL
SELECT s.id, 'Nghẹt ống', 2 FROM services s WHERE s.code='water_repair' UNION ALL
SELECT s.id, 'Vòi hỏng', 3 FROM services s WHERE s.code='water_repair' UNION ALL
SELECT s.id, 'Bồn cầu lỗi', 4 FROM services s WHERE s.code='water_repair' UNION ALL
SELECT s.id, 'Không lên nước', 1 FROM services s WHERE s.code='pump_repair' UNION ALL
SELECT s.id, 'Kêu lớn', 2 FROM services s WHERE s.code='pump_repair' UNION ALL
SELECT s.id, 'Yếu áp', 3 FROM services s WHERE s.code='pump_repair' UNION ALL
SELECT s.id, 'Tự ngắt', 4 FROM services s WHERE s.code='pump_repair' UNION ALL
SELECT s.id, 'Lắp mới', 1 FROM services s WHERE s.code='camera_install' UNION ALL
SELECT s.id, 'Mất hình', 2 FROM services s WHERE s.code='camera_install' UNION ALL
SELECT s.id, 'Không xem từ xa', 3 FROM services s WHERE s.code='camera_install' UNION ALL
SELECT s.id, 'Cài app', 1 FROM services s WHERE s.code='smart_device_install' UNION ALL
SELECT s.id, 'Kết nối Wi-Fi', 2 FROM services s WHERE s.code='smart_device_install' UNION ALL
SELECT s.id, 'Lắp mới', 3 FROM services s WHERE s.code='smart_device_install' UNION ALL
SELECT s.id, 'Cấu hình', 4 FROM services s WHERE s.code='smart_device_install' UNION ALL
SELECT s.id, 'Lắp máy giặt', 1 FROM services s WHERE s.code='appliance_install' UNION ALL
SELECT s.id, 'Lắp bếp', 2 FROM services s WHERE s.code='appliance_install' UNION ALL
SELECT s.id, 'Lắp máy lọc nước', 3 FROM services s WHERE s.code='appliance_install' UNION ALL
SELECT s.id, 'Không rõ lỗi', 1 FROM services s WHERE s.code='unknown_diagnosis' UNION ALL
SELECT s.id, 'Cần kiểm tra', 2 FROM services s WHERE s.code='unknown_diagnosis' UNION ALL
SELECT s.id, 'Cần báo giá', 3 FROM services s WHERE s.code='unknown_diagnosis'
ON DUPLICATE KEY UPDATE issue_label=VALUES(issue_label);

INSERT INTO schema_migrations(version,description)
VALUES('003_phase_6d','Phase 6D catalog metadata and product completion')
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
