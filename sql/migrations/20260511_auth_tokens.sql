-- ============================================================
-- Migration 20260511_auth_tokens
-- Long-lived authentication tokens — สำหรับ remember-me ที่อยู่ได้นานกว่า
-- cookie/PHPSESSID (เช่น iOS PWA, Safari ITP)
--
-- - token        : sha256 hash ของ token (32 chars hex stored)
-- - selector    : public lookup key (16 chars)
-- - user_id     : เจ้าของ
-- - expires_at  : วันหมดอายุ (default 90 วัน)
-- - user_agent  : เก็บไว้ตรวจ
-- - last_used_at: track activity
-- ============================================================

CREATE TABLE IF NOT EXISTS auth_tokens (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    selector       VARCHAR(32)  NOT NULL,
    token_hash     VARCHAR(128) NOT NULL,
    user_id        INT          NOT NULL,
    expires_at     DATETIME     NOT NULL,
    user_agent     VARCHAR(255) NULL,
    last_used_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_selector (selector),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),

    CONSTRAINT fk_auth_token_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
