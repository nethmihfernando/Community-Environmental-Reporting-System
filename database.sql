-- ============================================================
--  Community Environmental Reporting System — Database Schema
-- ============================================================

-- 1. Create and select the database
CREATE DATABASE IF NOT EXISTS env_reporting
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE env_reporting;

-- ============================================================
-- 2. CATEGORIES table
--    Stores the types of environmental issues that can be reported.
-- ============================================================
CREATE TABLE categories (
    category_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    description   TEXT,
    icon          VARCHAR(50)   DEFAULT 'fa-exclamation-triangle',
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categories (name, description, icon) VALUES
  ('Illegal Dumping',  'Unauthorised disposal of waste in public areas',            'fa-trash'),
  ('Water Pollution',  'Contamination of rivers, lakes, or coastal waters',         'fa-tint'),
  ('Air Pollution',    'Smoke, fumes, or other airborne pollutants',                'fa-wind'),
  ('Noise Pollution',  'Excessive or disruptive noise affecting the community',     'fa-volume-up'),
  ('Chemical Hazard',  'Spills or exposure to dangerous chemicals',                 'fa-biohazard'),
  ('Deforestation',    'Unauthorised tree-cutting or land clearing',                'fa-tree'),
  ('Wildlife Threat',  'Threats to native animals or their habitats',              'fa-paw'),
  ('Other',            'Any other environmental concern not listed above',          'fa-exclamation-circle');


-- ============================================================
-- 3. USERS table
-- ============================================================
CREATE TABLE users (
    user_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(150)  NOT NULL,
    email         VARCHAR(200)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    phone         VARCHAR(20),
    role          ENUM('citizen','admin') DEFAULT 'citizen',
    is_active     TINYINT(1)    DEFAULT 1,
    avatar        VARCHAR(255),
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default admin: email=admin@gmail.com  password=admin123
INSERT INTO users (full_name, email, password_hash, role) VALUES
  ('System Admin', 'admin@gmail.com',
   '$2y$10$H4Fg/6Rlgz0oU1cYIEmp6uqlVnpqG28ucT2abo4g3VUOxq08H95TS',
   'admin');


-- ============================================================
-- 4. REPORTS table
-- ============================================================
CREATE TABLE reports (
    report_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    category_id   INT UNSIGNED NOT NULL,
    title         VARCHAR(255) NOT NULL,
    description   TEXT         NOT NULL,
    image_path    VARCHAR(500),
    latitude      DECIMAL(10, 8),
    longitude     DECIMAL(11, 8),
    address       VARCHAR(500),
    status        ENUM('pending','in_progress','resolved','rejected') DEFAULT 'pending',
    priority      ENUM('low','medium','high')        DEFAULT 'medium',
    admin_notes   TEXT,
    views         INT UNSIGNED  DEFAULT 0,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)     REFERENCES users(user_id)          ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT,

    INDEX idx_status   (status),
    INDEX idx_user     (user_id),
    INDEX idx_category (category_id),
    INDEX idx_created  (created_at)
);


-- ============================================================
-- 5. STATUS_LOGS table — audit trail of every status change
-- ============================================================
CREATE TABLE status_logs (
    log_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id     INT UNSIGNED NOT NULL,
    changed_by    INT UNSIGNED NOT NULL,
    old_status    ENUM('pending','in_progress','resolved','rejected'),
    new_status    ENUM('pending','in_progress','resolved','rejected') NOT NULL,
    note          TEXT,
    changed_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (report_id)  REFERENCES reports(report_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(user_id)     ON DELETE CASCADE
);
