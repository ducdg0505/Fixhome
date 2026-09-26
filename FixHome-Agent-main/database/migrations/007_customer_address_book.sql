-- FixHome Customer Address Book non-destructive migration; MySQL 5.7/8.0 compatible.
-- Saved addresses are mutable convenience data. Existing orders remain text snapshots.
-- Production execution remains a separately approved deployment step.

CREATE TABLE IF NOT EXISTS customer_addresses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(60) NOT NULL,
    address VARCHAR(255) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    default_user_id BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN is_default = 1 THEN user_id ELSE NULL END
    ) VIRTUAL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customer_address_one_default (default_user_id),
    KEY idx_customer_address_user_default_id (user_id, is_default, id),
    CONSTRAINT fk_customer_address_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(version,description)
VALUES('007_customer_address_book','Customer saved addresses and booking prefill')
ON DUPLICATE KEY UPDATE description=VALUES(description);
