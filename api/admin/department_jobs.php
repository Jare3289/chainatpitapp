<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS department_jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_name VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed data if empty
    $count = $pdo->query("SELECT COUNT(*) FROM department_jobs")->fetchColumn();
    if ($count == 0) {
        $initialJobs = [
            'งานกิจกรรมพัฒนาผู้เรียนและทัศนศึกษา', 'งานแข่งขันทักษะทางวิชาการและงานวันสำคัญ',
            'งานธุรการและสารบรรณกลุ่มสาระฯ', 'งานนิเทศภายใน', 'งานบุคคล', 'งานปฏิคม',
            'งานประกันคุณภาพภายใน', 'งานผู้ประสานงานระดับชั้น', 'งานแผนงาน',
            'งานพัฒนาหลักสูตร', 'งานพัสดุและสินทรัพย์', 'งานวัดผลและประเมินผล',
            'งานวารสารและประชาสัมพันธ์', 'งานวิจัย นวัตกรรม และสื่อเทคโนโลยี',
            'งานวิชาการ', 'งานส่งเสริมและพัฒนาศักยภาพผู้เรียน', 'งานสารสนเทศและฐานข้อมูล',
            'งานโสตทัศนศึกษาและอาคารสถานที่ของกลุ่มสาระฯ', 'งานห้องศูนย์การเรียนรู้',
            'งานห้องสมุดกลุ่มสาระ', 'งานบริหารกลุ่มสาระการเรียนรู้'
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO department_jobs (job_name) VALUES (?)");
        foreach ($initialJobs as $job) {
            $stmt->execute([$job]);
        }
    }

    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT job_name FROM department_jobs ORDER BY job_name");
        $jobs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'jobs' => $jobs]);

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['name'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO department_jobs (job_name) VALUES (?)");
        $stmt->execute([$data['name']]);
        echo json_encode(['success' => true]);
    }

} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
