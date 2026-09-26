-- FixHome Phase 6D.6A non-destructive migration; MySQL 5.7/8.0 compatible.
-- Production execution remains a separately approved deployment step.

CREATE TABLE IF NOT EXISTS technician_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    technician_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_technician_review_order (order_id),
    KEY idx_technician_review_technician_created (technician_id, created_at),
    KEY idx_technician_review_customer_created (customer_id, created_at),
    CONSTRAINT fk_technician_review_order FOREIGN KEY (order_id) REFERENCES orders (id) ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_technician_review_customer FOREIGN KEY (customer_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_technician_review_technician FOREIGN KEY (technician_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(version,description)
VALUES('005_phase_6d6a','Phase 6D.6A separate company and technician reputation')
ON DUPLICATE KEY UPDATE description=VALUES(description);
