-- FixHome explicit test-account marker; MySQL 5.7/8.0 compatible.
-- Existing accounts remain normal accounts. No account is inferred as test data.
-- Production execution remains a separately approved deployment step.

ALTER TABLE users
    ADD COLUMN is_test TINYINT(1) NOT NULL DEFAULT 0 AFTER must_change_password,
    ADD KEY idx_user_test_role_status (is_test, role, status, id);

INSERT INTO schema_migrations(version,description)
VALUES('008_test_account_marker','explicit test account marker for maintenance cleanup')
ON DUPLICATE KEY UPDATE description=VALUES(description);
