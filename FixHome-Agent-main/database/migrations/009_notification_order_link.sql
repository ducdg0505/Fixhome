-- FixHome structured order notification linkage; MySQL 5.7/8.0 compatible.
-- Existing notifications remain unlinked. No order is inferred from message text.
-- Production execution remains a separately approved deployment step.

ALTER TABLE notifications
    ADD COLUMN order_id BIGINT UNSIGNED NULL AFTER target_user_id,
    ADD KEY idx_notification_order (order_id),
    ADD CONSTRAINT fk_notification_order FOREIGN KEY (order_id) REFERENCES orders (id) ON UPDATE RESTRICT ON DELETE CASCADE;

INSERT INTO schema_migrations(version,description)
VALUES('009_notification_order_link','structured order notification linkage for cleanup')
ON DUPLICATE KEY UPDATE description=VALUES(description);
