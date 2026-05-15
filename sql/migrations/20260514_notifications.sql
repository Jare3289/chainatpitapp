-- ============================================================
-- ระบบ Notification — ตารางเก็บการแจ้งเตือนของผู้ใช้
--
-- รัน 1 ครั้ง บน production phpMyAdmin → Import
-- ปลอดภัย: CREATE TABLE IF NOT EXISTS — รันซ้ำได้
-- ============================================================

CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL COMMENT 'FK → users.id',
    type        VARCHAR(50)  DEFAULT 'info' COMMENT 'attendance|public_service|points|system|reminder',
    title       VARCHAR(255) NOT NULL,
    message     TEXT,
    link        VARCHAR(255) DEFAULT NULL COMMENT 'URL ที่กดแล้วพาไป',
    icon        VARCHAR(50)  DEFAULT 'bi-bell' COMMENT 'Bootstrap Icons class',
    color       VARCHAR(20)  DEFAULT '#3b82f6' COMMENT 'Hex color (e.g. #ef4444)',
    is_read     TINYINT(1)   DEFAULT 0,
    dedup_key   VARCHAR(100) DEFAULT NULL COMMENT 'กันส่งซ้ำ — ภายในวันเดียวกัน',
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_created (created_at),
    UNIQUE KEY uq_dedup (user_id, dedup_key, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
