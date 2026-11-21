-- Gesond Rewards database schema (MySQL / MariaDB, InnoDB, utf8mb4)
-- Points are never stored as a balance: the balance is always the sum of points_ledger.
-- Ledger rows are never edited or deleted; corrections are new reversal rows.

CREATE TABLE IF NOT EXISTS members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_no VARCHAR(12) NULL UNIQUE,
  first_name VARCHAR(60) NOT NULL,
  last_name VARCHAR(60) NOT NULL,
  phone VARCHAR(15) NOT NULL UNIQUE,
  birth_date DATE NOT NULL,
  email VARCHAR(120) NULL,
  pin_hash VARCHAR(255) NOT NULL,
  must_change_pin TINYINT(1) NOT NULL DEFAULT 0,
  marketing_optin TINYINT(1) NOT NULL DEFAULT 0,
  popia_consent_at DATETIME NOT NULL,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(40) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vouchers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code CHAR(8) NOT NULL UNIQUE,
  member_id INT UNSIGNED NOT NULL,
  points INT NOT NULL,
  value_cents INT NOT NULL,
  status ENUM('active','used','cancelled') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  used_by_staff INT UNSIGNED NULL,
  used_reference VARCHAR(40) NULL,
  CONSTRAINT fk_v_member FOREIGN KEY (member_id) REFERENCES members(id),
  CONSTRAINT fk_v_staff FOREIGN KEY (used_by_staff) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS points_ledger (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  type ENUM('earn','redeem','reversal') NOT NULL,
  points INT NOT NULL,
  spend_cents INT NULL,
  reference VARCHAR(40) NULL,
  note VARCHAR(255) NULL,
  voucher_id INT UNSIGNED NULL,
  reverses_id INT UNSIGNED NULL UNIQUE,
  staff_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_type_reference (type, reference),
  KEY ix_member (member_id),
  CONSTRAINT fk_l_member FOREIGN KEY (member_id) REFERENCES members(id),
  CONSTRAINT fk_l_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers(id),
  CONSTRAINT fk_l_reverses FOREIGN KEY (reverses_id) REFERENCES points_ledger(id),
  CONSTRAINT fk_l_staff FOREIGN KEY (staff_id) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  key_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_key_time (key_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('member','staff','system') NOT NULL,
  actor_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  member_id INT UNSIGNED NULL,
  details TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  KEY ix_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  body TEXT NOT NULL,
  link_url VARCHAR(255) NULL,
  publish_at DATETIME NOT NULL,
  status ENUM('scheduled','cancelled') NOT NULL DEFAULT 'scheduled',
  to_facebook TINYINT(1) NOT NULL DEFAULT 0,
  fb_status ENUM('none','pending','sending','posted','failed') NOT NULL DEFAULT 'none',
  fb_post_id VARCHAR(80) NULL,
  fb_error VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY ix_publish (status, publish_at),
  CONSTRAINT fk_p_staff FOREIGN KEY (created_by) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref VARCHAR(12) NULL UNIQUE,
  kind ENUM('question','booking','complaint','idea') NOT NULL,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(15) NULL,
  email VARCHAR(120) NULL,
  message TEXT NOT NULL,
  member_id INT UNSIGNED NULL,
  status ENUM('new','in_progress','closed') NOT NULL DEFAULT 'new',
  staff_note TEXT NULL,
  handled_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  KEY ix_status (status, created_at),
  CONSTRAINT fk_e_member FOREIGN KEY (member_id) REFERENCES members(id),
  CONSTRAINT fk_e_staff FOREIGN KEY (handled_by) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(40) PRIMARY KEY,
  v TEXT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
