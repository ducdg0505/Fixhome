-- FixHome Phase 6D.5 non-destructive migration; MySQL 5.7/8.0 compatible.
-- Production execution remains a separately approved deployment step.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS user_profile_versions (
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

CREATE TABLE IF NOT EXISTS company_profile_versions (
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

CREATE TABLE IF NOT EXISTS technician_service_capabilities (
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

-- Baselines capture the current value first known when tracking begins.
-- They must not be backdated to entity creation or attributed to a guessed actor.
INSERT INTO user_profile_versions
    (user_id, version_no, name, email, phone, valid_from, valid_to, changed_by_user_id, created_at)
SELECT u.id, 1, u.name, u.email, u.phone, NOW(), NULL, NULL, NOW()
FROM users u
WHERE NOT EXISTS (
    SELECT 1 FROM user_profile_versions v WHERE v.user_id=u.id
);

INSERT INTO company_profile_versions
    (company_id, version_no, name, representative, phone, email, address, tax_code, valid_from, valid_to, changed_by_user_id, created_at)
SELECT c.id, 1, c.name, c.representative, c.phone, c.email, c.address, c.tax_code,
       NOW(), NULL, NULL, NOW()
FROM companies c
WHERE NOT EXISTS (
    SELECT 1 FROM company_profile_versions v WHERE v.company_id=c.id
);

INSERT INTO schema_migrations(version,description)
VALUES('004_phase_6d5','Phase 6D.5 identity, profile and workforce integrity')
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
