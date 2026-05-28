-- Create public_relations table for PR/News posts with admin approval system
CREATE TABLE IF NOT EXISTS public_relations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(100) DEFAULT 'ทั่วไป',
    visibility VARCHAR(50) NOT NULL DEFAULT 'all',
    image_path VARCHAR(255) DEFAULT NULL,
    author_id INT NOT NULL,
    author_role VARCHAR(50) NOT NULL,
    author_name VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    approved_by INT NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert premium mock public relations posts (already approved) for gorgeous immediate display
INSERT INTO public_relations (title, content, category, author_id, author_role, author_name, status, approved_at, approved_by) VALUES
('🏆 ขอแสดงความยินดีกับนักเรียนที่ผ่านการคัดเลือก สอวน. ค่าย 1', 'โรงเรียนชัยนาทพิทยาคมขอแสดงความยินดีกับนักเรียนชั้นมัธยมศึกษาตอนปลายทุกคนที่ผ่านการคัดเลือกเข้าค่ายอบรมโอลิมปิกวิชาการ สอวน. ค่าย 1 สาขาวิชาคณิตศาสตร์ คอมพิวเตอร์ และเคมี ประจำปีการศึกษา 2569 ขอให้ทุกคนตั้งใจศึกษาและเตรียมพร้อมสำหรับค่ายอบรมถัดไป!', 'วิชาการ', 1, 'admin', 'แอดมินระบบ', 'approved', NOW(), 1),
('🌟 ขอเชิญชวนร่วมกิจกรรมพิธีไหว้ครู ประจำปีการศึกษา 2569', 'ขอเชิญตัวแทนนักเรียนทุกห้องเรียนและคณะครูอาจารย์ทุกท่าน ร่วมพิธีไหว้ครูประจำปีการศึกษา 2569 เพื่อร่วมแสดงความกตัญญูกตเวทิตาต่อครูผู้ประสิทธิ์ประสาทวิชา ณ หอประชุมใหญ่ ในวันพฤหัสบดีนี้ ตั้งแต่เวลา 08:30 น. เป็นต้นไป การแต่งกาย: ชุดนักเรียน/ชุดพิธีการถูกระเบียบ', 'กิจกรรม', 1, 'admin', 'แอดมินระบบ', 'approved', NOW(), 1),
('📢 ประกาศมาตรการความปลอดภัยและตรวจสุขภาพนักเรียนประจำภาคเรียน', 'เพื่อสุขอนามัยที่ดีของทุกคน ทางโรงเรียนร่วมกับโรงพยาบาลชัยนาทจะทำการตรวจสุขภาพประจำปีให้กับนักเรียนทุกระดับชั้นในสัปดาห์หน้า ขอให้นักเรียนเตรียมเอกสารการตรวจสุขภาพและแต่งกายด้วยชุดพละศึกษาตามวันเวลาที่กำหนด', 'ประกาศสำคัญ', 1, 'admin', 'แอดมินระบบ', 'approved', NOW(), 1);
