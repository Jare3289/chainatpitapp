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
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_group VARCHAR(100) NOT NULL,
        job_name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY group_job (job_group, job_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed data if empty
    $count = $pdo->query("SELECT COUNT(*) FROM admin_jobs")->fetchColumn();
    if ($count == 0) {
        $initialJobs = [
            'กลุ่มบริหารกิจการนักเรียน' => [
                'งานปรับปรุงพฤติกรรมนักเรียน', 'งานป้องกันยาเสพติดและสถานศึกษาสีขาว', 'งานผู้ปกครองและเครือข่ายผู้ปกครอง',
                'งานระบบการดูแลช่วยเหลือนักเรียน', 'งานโรงเรียนคุณธรรม สพฐ.', 'งานวินัยนักเรียน',
                'งานส่งเสริมคุณธรรม จริยธรรม', 'งานส่งเสริมประชาธิปไตยและคณะกรรมการนักเรียน',
                'งานสารวัตรนักเรียน', 'งานสำนักงานกิจการนักเรียน'
            ],
            'กลุ่มบริหารงบประมาณและงานบุคคล' => [
                'งานจัดทำและเสนอของบประมาณ', 'งานธุรการและสารบรรณ', 'งานบริหารการเงิน',
                'งานบริหารบัญชีและตรวจสอบบัญชี', 'งานบริหารพัสดุและสินทรัพย์', 'งานบุคคล',
                'งานแผนงานและแผนกลยุทธ์', 'งานระดมทรัพยากรและการลงทุนเพื่อการศึกษา',
                'งานระบบควบคุมภายใน', 'งานเลขานุการคณะกรรมการสถานศึกษาขั้นพื้นฐาน',
                'งานสมาคมผู้ปกครองและครู', 'งานสมาคมศิษย์เก่า', 'งานสารสนเทศโรงเรียน'
            ],
            'กลุ่มบริหารทั่วไป' => [
                'งานคุ้มครองผู้บริโภค', 'งานซ่อมบำรุงอุปกรณ์การเกษตร', 'งานดุริยางค์ ดนตรีไทยและดนตรีสากล',
                'งานประกันอุบัติเหตุกลุ่ม', 'งานประชาสัมพันธ์', 'งานพยาบาล', 'งานพัฒนาศักยภาพผู้เรียนด้านอาชีพ',
                'งานโภชนาการ', 'งานโรงเรียนปลอดขยะ', 'งานโรงเรียนส่งเสริมสุขภาพ', 'งานโรงเรียนสีเขียว',
                'งานส่งเสริมสนับสนุนและประสานงานการศึกษา', 'งานสถานศึกษาปลอดภัย', 'งานสวัสดิการเงินยืมบุคลากร',
                'งานสหกรณ์โรงเรียน', 'งานสัมพันธ์ชุมชน', 'งานโสตทัศนศึกษา',
                'งานออกแบบตกแต่งอาคาร สถานที่ ทัศนศิลป์ และเทคโนโลยีสารสนเทศ', 'งานอาคารสถานที่และสภาพแวดล้อม'
            ],
            'กลุ่มบริหารวิชาการ' => [
                'งานกลุ่มสาระการเรียนรู้', 'งานกิจกรรมพัฒนาผู้เรียน', 'งานโครงการห้องเรียนพิเศษ',
                'งานจัดอัตรากำลังและจัดทำตารางสอน', 'งานทะเบียนนักเรียน', 'งานเทียบโอนผลการเรียน',
                'งานนิเทศการศึกษา', 'งานแนะแนว', 'งานประกันคุณภาพภายในและมาตรฐานการศึกษา',
                'งานประสานงานครูที่ปรึกษา', 'งานพัฒนากระบวนการเรียนรู้', 'งานพัฒนาสื่อนวัตกรรมและเทคโนโลยีการศึกษา',
                'งานพัฒนาหลักสูตรสถานศึกษา', 'งานรับนักเรียน', 'งานวัดผลและประเมินผล',
                'งานวิจัยเพื่อพัฒนาคุณภาพการศึกษา', 'งานสำนักงานวิชาการ', 'งานห้องสมุด'
            ]
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO admin_jobs (job_group, job_name) VALUES (?, ?)");
        foreach ($initialJobs as $group => $jobs) {
            foreach ($jobs as $job) {
                $stmt->execute([$group, $job]);
            }
        }
    }

    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM admin_jobs ORDER BY job_group, job_name");
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $mapping = [];
        foreach ($jobs as $row) {
            $mapping[$row['job_group']][] = $row['job_name'];
        }
        
        echo json_encode(['success' => true, 'mapping' => $mapping]);

    } elseif ($method === 'POST') {
        // Allow adding new jobs
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['group']) || !isset($data['name'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO admin_jobs (job_group, job_name) VALUES (?, ?)");
        $stmt->execute([$data['group'], $data['name']]);
        
        echo json_encode(['success' => true]);
    }

} catch (PDOException $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว']);
}
