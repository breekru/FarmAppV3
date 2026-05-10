-- FarmApp Database Schema
-- Run in order: users → login_attempts → animals
-- Charset: utf8mb4 (full Unicode + emoji support)

-- -----------------------------------------------
-- Users
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    reset_token   VARCHAR(255) DEFAULT NULL,
    reset_expires DATETIME     DEFAULT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------
-- Login attempt tracking (rate limiting / lockout)
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(100) DEFAULT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    attempt_time DATETIME     NOT NULL,
    INDEX idx_ip       (ip_address, attempt_time),
    INDEX idx_username (username, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------
-- Animals (owned by a user)
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS animals (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT            NOT NULL,
    type       VARCHAR(100)   NOT NULL,
    name       VARCHAR(100)   NOT NULL,
    dob        DATE           DEFAULT NULL,
    weight     DECIMAL(8, 2)  DEFAULT NULL,
    notes      TEXT           DEFAULT NULL,
    created_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
