-- FixHome Phase 6D.6B non-destructive migration; MySQL 5.7/8.0 compatible.
-- No historical repair reports are fabricated or backfilled.
-- Production execution remains a separately approved deployment step.

CREATE TABLE IF NOT EXISTS repair_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    technician_id BIGINT UNSIGNED NOT NULL,
    actual_issue TEXT NOT NULL,
    resolution TEXT NOT NULL,
    post_repair_advice TEXT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_repair_report_order (order_id),
    KEY idx_repair_report_technician_submitted (technician_id, submitted_at, id),
    CONSTRAINT fk_repair_report_order FOREIGN KEY (order_id) REFERENCES orders (id) ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_repair_report_technician FOREIGN KEY (technician_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(version,description)
VALUES('006_phase_6d6b','Phase 6D.6B structured repair knowledge capture')
ON DUPLICATE KEY UPDATE description=VALUES(description);
