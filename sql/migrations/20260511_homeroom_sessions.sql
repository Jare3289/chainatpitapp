-- ============================================================
-- Migration 20260511_homeroom_sessions
-- บันทึกกิจกรรมโฮมรูม (สั้น/ยาว) ต่อห้องต่อวัน
-- - session_type: 'short' (มีกิจกรรม) / 'long' (พิมพ์อธิบาย)
-- - activity   : ชื่อกิจกรรม (autocomplete จาก distinct ค่าเก่า)
-- - notes      : รายละเอียดเพิ่มเติม (สำหรับ long)
-- ============================================================

CREATE TABLE IF NOT EXISTS homeroom_sessions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    date            DATE        NOT NULL,
    room_id         INT         NOT NULL,
    session_type    ENUM('short','long') NOT NULL DEFAULT 'short',
    activity        VARCHAR(255) NULL,
    notes           TEXT         NULL,
    recorded_by     INT          NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_homeroom_date_room (date, room_id),
    INDEX idx_homeroom_date (date),
    INDEX idx_homeroom_room (room_id),

    CONSTRAINT fk_homeroom_room
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_homeroom_user
        FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
