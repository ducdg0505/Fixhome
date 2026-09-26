-- FixHome vNext single target schema.
-- WARNING: This script drops and recreates all known FixHome tables in the
-- currently selected database. Run it only against an empty, development, or
-- newly provisioned FixHome database. It never creates or drops a database.
-- Compatible with MySQL 5.7 and MySQL 8.0.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @FIXHOME_OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS complaints;
DROP TABLE IF EXISTS technician_reviews;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS repair_reports;
DROP TABLE IF EXISTS order_timeline;
DROP TABLE IF EXISTS quotes;
DROP TABLE IF EXISTS order_company_requests;
DROP TABLE IF EXISTS order_services;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS company_service_categories;
DROP TABLE IF EXISTS partner_application_categories;
DROP TABLE IF EXISTS technician_service_capabilities;
DROP TABLE IF EXISTS company_profile_versions;
DROP TABLE IF EXISTS user_profile_versions;
DROP TABLE IF EXISTS customer_addresses;
DROP TABLE IF EXISTS service_common_issues;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS partner_applications;
DROP TABLE IF EXISTS service_categories;
DROP TABLE IF EXISTS schema_migrations;

SET FOREIGN_KEY_CHECKS = @FIXHOME_OLD_FOREIGN_KEY_CHECKS;

CREATE TABLE schema_migrations (
    version VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(32) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_service_category_code (code),
    UNIQUE KEY uq_service_category_name (name),
    KEY idx_service_category_active_name (active, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(180) NOT NULL,
    unit VARCHAR(40) NOT NULL,
    min_price DECIMAL(15,0) NOT NULL DEFAULT 0,
    max_price DECIMAL(15,0) NOT NULL DEFAULT 0,
    description TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_service_code (code),
    KEY idx_service_category_active (category_id, active, id),
    CONSTRAINT fk_service_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_common_issues (
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

CREATE TABLE partner_applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_name VARCHAR(180) NOT NULL,
    tax_code VARCHAR(60) NOT NULL,
    representative VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(180) NOT NULL,
    address VARCHAR(255) NOT NULL,
    legal_note TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending_review',
    review_note TEXT NULL,
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_partner_application_status_submitted (status, submitted_at, id),
    KEY idx_partner_application_tax_code (tax_code),
    KEY idx_partner_application_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE companies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    tax_code VARCHAR(60) NOT NULL,
    representative VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(180) NOT NULL,
    address VARCHAR(255) NOT NULL,
    legal_status VARCHAR(40) NOT NULL DEFAULT 'pending_verification',
    account_status VARCHAR(40) NOT NULL DEFAULT 'not_provisioned',
    rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    manager_user_id BIGINT UNSIGNED NULL,
    source_application_id BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_company_tax_code (tax_code),
    UNIQUE KEY uq_company_email (email),
    KEY idx_company_status_name (legal_status, account_status, name),
    KEY idx_company_manager (manager_user_id),
    UNIQUE KEY uq_company_source_application (source_application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role VARCHAR(30) NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(180) NOT NULL,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    company_id BIGINT UNSIGNED NULL,
    skill_note VARCHAR(255) NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    is_test TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_email (email),
    UNIQUE KEY uq_user_id_company (id, company_id),
    KEY idx_user_company_role_status (company_id, role, status),
    KEY idx_user_role_status (role, status),
    KEY idx_user_test_role_status (is_test, role, status, id),
    CONSTRAINT fk_user_company FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE partner_applications
    ADD CONSTRAINT fk_partner_application_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TABLE companies
    ADD CONSTRAINT fk_company_manager FOREIGN KEY (manager_user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE SET NULL,
    ADD CONSTRAINT fk_company_source_application FOREIGN KEY (source_application_id) REFERENCES partner_applications (id) ON UPDATE RESTRICT ON DELETE RESTRICT;

CREATE TABLE customer_addresses (
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

CREATE TABLE user_profile_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(180) NOT NULL,
    phone VARCHAR(30) NULL,
    valid_from DATETIME NOT NULL,
    valid_to DATETIME NULL,
    changed_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_profile_version (user_id, version_no),
    KEY idx_user_profile_current (user_id, valid_to, version_no),
    KEY idx_user_profile_changed_by (changed_by_user_id),
    CONSTRAINT fk_user_profile_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_user_profile_changed_by FOREIGN KEY (changed_by_user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE company_profile_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    representative VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(180) NOT NULL,
    address VARCHAR(255) NOT NULL,
    tax_code VARCHAR(60) NOT NULL,
    valid_from DATETIME NOT NULL,
    valid_to DATETIME NULL,
    changed_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_company_profile_version (company_id, version_no),
    KEY idx_company_profile_current (company_id, valid_to, version_no),
    KEY idx_company_profile_changed_by (changed_by_user_id),
    CONSTRAINT fk_company_profile_company FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_company_profile_changed_by FOREIGN KEY (changed_by_user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE technician_service_capabilities (
    technician_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (technician_id, service_id),
    KEY idx_technician_capability_service (service_id, technician_id),
    KEY idx_technician_capability_actor (created_by_user_id),
    CONSTRAINT fk_technician_capability_technician FOREIGN KEY (technician_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_technician_capability_service FOREIGN KEY (service_id) REFERENCES services (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_technician_capability_actor FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE partner_application_categories (
    application_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (application_id, category_id),
    KEY idx_partner_category_routing (category_id, application_id),
    CONSTRAINT fk_partner_category_application FOREIGN KEY (application_id) REFERENCES partner_applications (id) ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_partner_category_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE company_service_categories (
    company_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (company_id, category_id),
    KEY idx_company_category_routing (category_id, company_id),
    CONSTRAINT fk_company_category_company FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_company_category_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_code VARCHAR(32) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    customer_email VARCHAR(180) NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    mode VARCHAR(80) NOT NULL,
    description TEXT NULL,
    diagnosis_summary TEXT NULL,
    diagnosis_device VARCHAR(120) NULL,
    diagnosis_issue_group VARCHAR(255) NULL,
    diagnosis_risk VARCHAR(30) NULL,
    image_name VARCHAR(255) NULL,
    address VARCHAR(255) NOT NULL,
    scheduled_at DATETIME NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending_distribution',
    company_id BIGINT UNSIGNED NULL,
    selected_quote_id BIGINT UNSIGNED NULL,
    technician_id BIGINT UNSIGNED NULL,
    estimate_min DECIMAL(15,0) NULL,
    estimate_max DECIMAL(15,0) NULL,
    final_price DECIMAL(15,0) NULL,
    cancelled_by_user_id BIGINT UNSIGNED NULL,
    cancelled_by_role VARCHAR(30) NULL,
    cancel_reason TEXT NULL,
    cancelled_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_code (order_code),
    KEY idx_order_customer_created (customer_id, created_at, id),
    KEY idx_order_company_status (company_id, status, updated_at, id),
    KEY idx_order_technician_status (technician_id, status, scheduled_at, id),
    KEY idx_order_status_created (status, created_at, id),
    KEY idx_order_category_status (category_id, status),
    KEY idx_order_selected_quote (selected_quote_id),
    KEY idx_order_cancelled_by (cancelled_by_user_id),
    CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_order_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_order_company FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_order_technician FOREIGN KEY (technician_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_order_technician_company FOREIGN KEY (technician_id, company_id) REFERENCES users (id, company_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_order_cancelled_by FOREIGN KEY (cancelled_by_user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_services (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    service_code_snapshot VARCHAR(80) NOT NULL,
    name_snapshot VARCHAR(180) NOT NULL,
    unit_snapshot VARCHAR(40) NOT NULL,
    min_price_snapshot DECIMAL(15,0) NOT NULL DEFAULT 0,
    max_price_snapshot DECIMAL(15,0) NOT NULL DEFAULT 0,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_service (order_id, service_id),
    KEY idx_order_service_catalog (service_id, order_id),
    CONSTRAINT fk_order_service_order FOREIGN KEY (order_id) REFERENCES orders (id) ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_order_service_service FOREIGN KEY (service_id) REFERENCES services (id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_company_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'invited',
    selected_order_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'selected' THEN order_id ELSE NULL END) VIRTUAL,
    current_quote_id BIGINT UNSIGNED NULL,
    invited_by_user_id BIGINT UNSIGNED NULL,
    invited_at DATETIME NOT NULL,
    viewed_at DATETIME NULL,
    quote_submitted_at DATETIME NULL,
    selected_at DATETIME NULL,
    closed_at DATETIME NULL,
    close_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_company_request (order_id, company_id),
    UNIQUE KEY uq_request_identity (id, order_id, company_id),
    UNIQUE KEY uq_request_one_selected_per_order (selected_order_id),
    KEY idx_request_company_status (company_id, status, invited_at, id),
    KEY idx_request_order_status (order_id, status),
    KEY idx_request_current_quote (current_quote_id),
    KEY idx_request_invited_by (invited_by_user_id),
    CONSTRAINT fk_request_order FOREIGN KEY (order_id) REFERENCES orders (id) ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_request_company FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_request_invited_by FOREIGN KEY (invited_by_user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_company_request_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    revision INT UNSIGNED NOT NULL,
    min_price DECIMAL(15,0) NOT NULL,
    max_price DECIMAL(15,0) NOT NULL,
    note TEXT NULL,
    estimated_arrival VARCHAR(120) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'submitted',
    accepted_order_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'accepted_by_customer' THEN order_id ELSE NULL END) VIRTUAL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    submitted_at DATETIME NOT NULL,
    responded_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_quote_revision (order_id, company_id, revision),
    UNIQUE KEY uq_quote_request_identity (id, order_company_request_id, order_id, company_id),
    UNIQUE KEY uq_quote_order_company_identity (id, order_id, company_id),
    UNIQUE KEY uq_quote_one_accepted_per_order (accepted_order_id),
    KEY idx_quote_request_status_revision (order_company_request_id, status, revision),
    KEY idx_quote_created_by (created_by_user_id),
    CONSTRAINT fk_quote_request FOREIGN KEY (order_company_request_id, order_id, company_id) REFERENCES order_company_requests (id, order_id, company_id) ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_quote_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE order_company_requests
    ADD CONSTRAINT fk_request_current_quote FOREIGN KEY (current_quote_id, id, order_id, company_id) REFERENCES quotes (id, order_company_request_id, order_id, company_id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE orders
    ADD CONSTRAINT fk_order_selected_request FOREIGN KEY (id, company_id) REFERENCES order_company_requests (order_id, company_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    ADD CONSTRAINT fk_order_selected_quote FOREIGN KEY (selected_quote_id) REFERENCES quotes (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    ADD CONSTRAINT fk_order_selected_quote_match FOREIGN KEY (selected_quote_id, id, company_id) REFERENCES quotes (id, order_id, company_id) ON UPDATE RESTRICT ON DELETE RESTRICT;

CREATE TABLE order_timeline (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    event_scope VARCHAR(30) NOT NULL DEFAULT 'order',
    event_type VARCHAR(60) NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NULL,
    order_company_request_id BIGINT UNSIGNED NULL,
    company_id BIGINT UNSIGNED NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    actor_role VARCHAR(30) NULL,
    note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_timeline_order_created (order_id, created_at, id),
    KEY idx_timeline_request_created (order_company_request_id, created_at, id),
    KEY idx_timeline_company_created (company_id, created_at),
    KEY idx_timeline_actor (actor_user_id),
    CONSTRAINT fk_timeline_order FOREIGN KEY (order_id) REFERENCES orders (id) ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_timeline_request FOREIGN KEY (order_company_request_id) REFERENCES order_company_requests (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_timeline_company FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_timeline_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE repair_reports (
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

CREATE TABLE reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_review_order (order_id),
    KEY idx_review_company_created (company_id, created_at),
    KEY idx_review_customer_created (customer_id, created_at),
    CONSTRAINT fk_review_order FOREIGN KEY (order_id) REFERENCES orders (id) ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_review_customer FOREIGN KEY (customer_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_review_company FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE technician_reviews (
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

CREATE TABLE complaints (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    subject VARCHAR(180) NOT NULL,
    detail TEXT NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'open',
    admin_note TEXT NULL,
    handled_by_user_id BIGINT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_complaint_status_created (status, created_at),
    KEY idx_complaint_order_created (order_id, created_at),
    KEY idx_complaint_customer_created (customer_id, created_at),
    KEY idx_complaint_handler (handled_by_user_id),
    CONSTRAINT fk_complaint_order FOREIGN KEY (order_id) REFERENCES orders (id) ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_complaint_customer FOREIGN KEY (customer_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_complaint_handler FOREIGN KEY (handled_by_user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_user_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    notification_type VARCHAR(60) NOT NULL DEFAULT 'general',
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notification_user_read_created (target_user_id, is_read, created_at, id),
    KEY idx_notification_order (order_id),
    CONSTRAINT fk_notification_user FOREIGN KEY (target_user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT fk_notification_order FOREIGN KEY (order_id) REFERENCES orders (id) ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    actor_role VARCHAR(30) NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id BIGINT UNSIGNED NULL,
    detail TEXT NULL,
    ip_address VARCHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user_created (user_id, created_at),
    KEY idx_audit_entity_created (entity_type, entity_id, created_at),
    KEY idx_audit_action_ip_created (action, ip_address, created_at),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE RESTRICT ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version, description)
VALUES
    ('001_target_schema', 'Initial FixHome vNext marketplace schema'),
    ('002_perf_security', 'Performance and security hardening baseline'),
    ('003_phase_6d', 'Phase 6D catalog metadata and product completion'),
    ('004_phase_6d5', 'Phase 6D.5 identity, profile and workforce integrity'),
    ('005_phase_6d6a', 'Phase 6D.6A separate company and technician reputation'),
    ('006_phase_6d6b', 'Phase 6D.6B structured repair knowledge capture'),
    ('007_customer_address_book', 'Customer saved addresses and booking prefill'),
    ('008_test_account_marker', 'explicit test account marker for maintenance cleanup'),
    ('009_notification_order_link', 'structured order notification linkage for cleanup');
