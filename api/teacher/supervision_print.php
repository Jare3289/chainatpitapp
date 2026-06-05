<?php
/**
 * api/teacher/supervision_print.php
 * Renders a printable booklet of the completed teacher supervision process.
 */
require_once '../../config.php';
require_once '../../inc/security.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("กรุณาเข้าสู่ระบบก่อนใช้งาน");
}

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($booking_id <= 0) {
    die("ระบุรหัสรายการนิเทศไม่ถูกต้อง");
}

try {
    // 1. Fetch current user (teacher or admin)
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $curr_user = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $my_teacher = $stmt->fetch();
    $my_teacher_id = $my_teacher ? $my_teacher['id'] : 0;

    $stmt = $pdo->prepare("SELECT b.*, 
        t.prefix as t_prefix, t.first_name_th as t_first, t.last_name_th as t_last, t.photo as t_photo, t.department as t_dept, t.academic_standing as t_standing, t.position as t_pos, t.signature as t_signature,
        (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.peer_teacher_id) as peer_name,
        (SELECT signature FROM teachers WHERE id = b.peer_teacher_id) as peer_signature,
        (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.head_teacher_id) as head_name,
        (SELECT signature FROM teachers WHERE id = b.head_teacher_id) as head_signature,
        (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.academic_teacher_id) as academic_name,
        (SELECT signature FROM teachers WHERE id = b.academic_teacher_id) as academic_signature
        FROM supervision_bookings b
        JOIN teachers t ON b.teacher_id = t.id
        WHERE b.id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        die("ไม่พบข้อมูลการนิเทศรายการนี้");
    }

    // Fetch department head name
    $dept_head_name = '.......................................................';
    if (!empty($booking['t_dept'])) {
        $stmt_dept = $pdo->prepare("SELECT head_name FROM departments WHERE name_th = ?");
        $stmt_dept->execute([$booking['t_dept']]);
        $dept_row = $stmt_dept->fetch();
        if ($dept_row && !empty($dept_row['head_name'])) {
            $dept_head_name = $dept_row['head_name'];
        }
    }

    // Security check: Must be Evaluatee, one of the 3 evaluators, or admin
    $is_allowed = ($curr_user['role'] === 'admin') 
        || ((int)$booking['teacher_id'] === $my_teacher_id)
        || ((int)$booking['peer_teacher_id'] === $my_teacher_id)
        || ((int)$booking['head_teacher_id'] === $my_teacher_id)
        || ((int)$booking['academic_teacher_id'] === $my_teacher_id);

    if (!$is_allowed) {
        die("คุณไม่มีสิทธิ์ในการเข้าถึงเอกสารรายการนิเทศนี้");
    }

    // 3. Fetch all evaluations
    $stmt = $pdo->prepare("SELECT e.*, 
        t.prefix, t.first_name_th, t.last_name_th
        FROM supervision_evaluations e
        JOIN teachers t ON e.evaluator_teacher_id = t.id
        WHERE e.booking_id = ?");
    $stmt->execute([$booking_id]);
    $evals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $peer_eval = null;
    $head_eval = null;
    $academic_eval = null;

    foreach ($evals as $ev) {
        $ev['name'] = trim(($ev['prefix'] ?? '') . $ev['first_name_th'] . ' ' . $ev['last_name_th']);
        if ((int)$ev['evaluator_teacher_id'] === (int)$booking['peer_teacher_id']) {
            $peer_eval = $ev;
        } elseif ((int)$ev['evaluator_teacher_id'] === (int)$booking['head_teacher_id']) {
            $head_eval = $ev;
        } elseif ($booking['academic_teacher_id'] && (int)$ev['evaluator_teacher_id'] === (int)$booking['academic_teacher_id']) {
            $academic_eval = $ev;
        }
    }

    // Calculate document evaluation details
    $doc_items = [
        "1. จุดประสงค์การเรียนรู้ชัดเจน ครอบคลุม K P A",
        "2. การจัดกิจกรรมการเรียนรู้สอดคล้องกับจุดประสงค์",
        "3. การเลือกใช้สื่อ/นวัตกรรมการเรียนรู้เหมาะสม",
        "4. การวัดและประเมินผลเครื่องมือตรงตามเป้าหมาย",
        "5. ความสมบูรณ์และถูกต้องของเนื้อหาในแผน"
    ];

    $class_items = [
        "1. การนำเข้าสู่บทเรียนเชื่อมโยงเนื้อหาเดิมและเร้าความสนใจ",
        "2. การจัดบรรยากาศที่ส่งเสริมการมีส่วนร่วมของนักเรียน",
        "3. ครูจัดกิจกรรมการเรียนรู้ตามขั้นตอนในแผน",
        "4. ครูอธิบายเนื้อหาถูกต้อง ชัดเจน เข้าใจง่าย",
        "5. การใช้คำถามกระตุ้นความคิดและการแก้ปัญหา",
        "6. การใช้สื่อการสอน/เทคโนโลยีส่งเสริมการเรียนรู้",
        "7. ครูดูแล ควบคุมชั้นเรียน และช่วยเหลือนักเรียนทั่วถึง",
        "8. มีการวัดและประเมินผลระหว่างเรียนเป็นระยะ",
        "9. นักเรียนบรรลุวัตถุประสงค์การเรียนรู้ในคาบนั้น",
        "10. บุคลิกภาพ น้ำเสียง และการจัดสรรเวลาอย่างเหมาะสม"
    ];

    function calculate_average($scores) {
        $valid_scores = array_filter($scores, function($s) { return $s > 0; });
        if (count($valid_scores) === 0) return 0;
        return array_sum($valid_scores) / count($valid_scores);
    }

    // Thai Date Formatter Helper
    function format_th_date($d) {
        if (empty($d)) return '-';
        $parts = explode('-', $d);
        $y = (int)$parts[0] + 543;
        $m = (int)$parts[1];
        $day = (int)$parts[2];
        $months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        return "$day {$months[$m]} $y";
    }

    // Convert Arabic to Thai Numerals Helper
    function to_thai_num($num) {
        $arabic = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $thai = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
        return str_replace($arabic, $thai, (string)$num);
    }

    $teacher_name = trim(($booking['t_prefix'] ?? '') . $booking['t_first'] . ' ' . $booking['t_last']);

    // Process scores for summaries
    $all_doc_scores = [];
    $all_class_scores = [];
    
    foreach ($evals as $ev) {
        for ($i=1; $i<=5; $i++) {
            $sc = (int)($ev["doc_score_$i"] ?? 0);
            if ($sc > 0) $all_doc_scores[] = $sc;
        }
        for ($i=1; $i<=10; $i++) {
            $sc = (int)($ev["class_score_$i"] ?? 0);
            if ($sc > 0) $all_class_scores[] = $sc;
        }
    }
    
    $avg_doc = calculate_average($all_doc_scores);
    $percent_doc = ($avg_doc / 5) * 100;
    
    $avg_class = calculate_average($all_class_scores);
    $percent_class = ($avg_class / 5) * 100;

    $overall_avg = calculate_average(array_merge($all_doc_scores, $all_class_scores));
    $overall_percent = ($overall_avg / 5) * 100;

    if (!function_exists('get_quality_text_php')) {
        function get_quality_text_php($avg) {
            $avg = (float)$avg;
            if ($avg <= 0) return '-';
            if ($avg >= 4.21) return 'ยอดเยี่ยม';
            if ($avg >= 3.41) return 'ดีเลิศ';
            if ($avg >= 2.61) return 'ดี';
            if ($avg >= 1.81) return 'ปานกลาง';
            if ($avg >= 1.00) return 'กำลังพัฒนา';
            return 'ไม่ปฏิบัติ';
        }
    }

    $doc_quality = get_quality_text_php($avg_doc);
    $class_quality = get_quality_text_php($avg_class);
    
    $order_no = isset($_GET['order_no']) ? htmlspecialchars($_GET['order_no']) : '323/2568';
    
    $sign_date_thai = '...................................................';
    if (!empty($booking['post_teaching_record'])) {
        $post_record = json_decode($booking['post_teaching_record'], true);
        if (is_array($post_record) && !empty($post_record['sign_date'])) {
            $sign_date_thai = format_th_date($post_record['sign_date']);
        }
    }

} catch (Exception $e) {
    die("เกิดข้อผิดพลาดในการโหลดข้อมูล: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เอกสารการนิเทศ - <?php echo htmlspecialchars($teacher_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../public/css/fonts.css">
    <style>
        body {
            font-family: 'TH Sarabun PSK', 'TH Sarabun New', 'Sarabun', sans-serif;
            font-size: 16pt;
            background-color: #f3f4f6;
            color: #111827;
            padding: 40px 0;
            line-height: 1.25;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 25mm 20mm 20mm 25mm;
            margin: 0 auto 30px;
            background: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            border-radius: 4px;
            position: relative;
            box-sizing: border-box;
            line-height: 1.25;
        }
        .page-cover {
            padding: 20mm 20mm 18mm 20mm !important;
            display: flex !important;
            flex-direction: column !important;
        }
        .page-break {
            page-break-before: always;
        }
        .text-navy {
            color: #1e3a8a;
        }
        .cover-title {
            margin-top: 100px;
            font-weight: 800;
            font-size: 28pt;
            line-height: 1.25;
            color: #1e3a8a;
        }
        .table-eval {
            font-size: 16pt;
            line-height: 1.25;
        }
        .table-eval th {
            background-color: #f8fafc !important;
            font-weight: 700;
            text-align: center;
            font-size: 16pt;
        }
        .table-eval td {
            font-size: 16pt;
        }
        .score-col {
            width: 60px;
            text-align: center;
        }
        .sig-block {
            margin-top: 40px;
        }
        .sig-line {
            width: 200px;
            border-bottom: 1px dotted #000;
            display: inline-block;
        }
        .border-bottom-dotted {
            border-bottom: 1px dotted #000;
        }
        
        /* Floating print button */
        .print-btn-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 999;
        }
        .btn-print {
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 800;
            box-shadow: 0 10px 25px rgba(30, 58, 138, 0.4);
            font-size: 16pt;
        }

        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                color: #000 !important;
                font-family: 'TH Sarabun PSK', 'TH Sarabun New', 'Sarabun', sans-serif !important;
            }
            .page {
                width: 210mm !important;
                min-height: 297mm !important;
                padding: 25mm 20mm 20mm 25mm !important;
                margin: 0 !important;
                box-shadow: none !important;
                border: none !important;
                page-break-after: always !important;
                page-break-inside: avoid !important;
                display: block !important;
                background: white !important;
                box-sizing: border-box !important;
                border-radius: 0 !important;
            }
            .page-break {
                page-break-before: always !important;
            }
            .page-cover {
                padding: 20mm 20mm 18mm 20mm !important;
                display: flex !important;
                flex-direction: column !important;
                height: 297mm !important;
            }
            .print-btn-container {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="print-btn-container">
        <button class="btn btn-primary btn-print" onclick="window.print()"><i class="bi bi-file-earmark-pdf-fill me-2"></i> สั่งพิมพ์ / บันทึกเป็น PDF (A4)</button>
    </div>

    <!-- PAGE 1: COVER PAGE -->
    <div class="page page-cover">

        <!-- SECTION 1: Logo -->
        <div style="text-align: center; padding-bottom: 18px;">
            <img src="../../public/img/logo.png" alt="Logo" style="width: 100px; height: auto;" onerror="this.style.display='none'">
        </div>

        <!-- Navy + Gold divider top -->
        <div style="border-top: 3px solid #1e3a8a; margin-bottom: 2px;"></div>
        <div style="border-top: 1.5px solid #b8952a; margin-bottom: 28px;"></div>

        <!-- SECTION 2: Title -->
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="font-size: 30pt; font-weight: bold; color: #1e3a8a; line-height: 1.3; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif;">รายงานผลการนิเทศการจัดการเรียนรู้</div>
            <div style="font-size: 22pt; color: #334155; margin-top: 6px; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif;">ภาคเรียนที่ <?php echo htmlspecialchars($booking['semester']); ?> &nbsp; ปีการศึกษา <?php echo htmlspecialchars($booking['year']); ?></div>
        </div>

        <!-- SECTION 3: Info box — spacer grows so the box is vertically centered -->
        <div style="flex: 1; display: flex; align-items: center; justify-content: center;">
            <div style="background: #f0f4ff; border: 1.5px solid #c7d7f5; border-radius: 6px; padding: 22px 40px; min-width: 340px; max-width: 460px;">
                <table style="width: 100%; font-size: 18pt; line-height: 2.0; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; border-collapse: collapse;">
                    <tr>
                        <td style="font-weight: bold; color: #1e3a8a; white-space: nowrap; padding-right: 10px; vertical-align: top;">ชื่อ-สกุล</td>
                        <td style="color: #1e293b; vertical-align: top; padding-right: 8px;">:</td>
                        <td style="color: #1e293b; font-weight: bold; vertical-align: top;"><?php echo htmlspecialchars($teacher_name); ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #1e3a8a; white-space: nowrap; padding-right: 10px; vertical-align: top;">ตำแหน่ง</td>
                        <td style="color: #1e293b; vertical-align: top; padding-right: 8px;">:</td>
                        <td style="color: #1e293b; vertical-align: top;"><?php echo htmlspecialchars($booking['t_pos'] ?? 'ครู'); ?><?php if (!empty($booking['t_standing']) && $booking['t_standing'] !== 'ไม่มีวิทยฐานะ') echo ' ' . htmlspecialchars($booking['t_standing']); ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #1e3a8a; white-space: nowrap; padding-right: 10px; vertical-align: top;">กลุ่มสาระการเรียนรู้</td>
                        <td style="color: #1e293b; vertical-align: top; padding-right: 8px;">:</td>
                        <td style="color: #1e293b; font-weight: bold; vertical-align: top;"><?php echo htmlspecialchars($booking['t_dept'] ?? '-'); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Gold + Navy divider bottom (reversed) -->
        <div style="border-top: 1.5px solid #b8952a; margin-top: 24px; margin-bottom: 2px;"></div>
        <div style="border-top: 3px solid #1e3a8a; margin-bottom: 18px;"></div>

        <!-- SECTION 4: School footer -->
        <div style="text-align: center;">
            <div style="font-size: 20pt; font-weight: bold; color: #1e3a8a; margin-bottom: 4px; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif;">โรงเรียนชัยนาทพิทยาคม</div>
            <div style="font-size: 16pt; color: #475569; margin-bottom: 2px; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif;">สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาอุทัยธานี ชัยนาท</div>
            <div style="font-size: 14pt; color: #64748b; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif;">สำนักงานคณะกรรมการการศึกษาขั้นพื้นฐาน กระทรวงศึกษาธิการ</div>
        </div>

    </div>

    <!-- PAGE 2: MEMO -->
    <?php
    $standing = ($booking['t_standing'] && $booking['t_standing'] !== 'ไม่มีวิทยฐานะ') ? $booking['t_standing'] : '';
    ?>
    <div class="page page-break">
        <div style="position: relative; text-align: center; margin-bottom: 10px; height: 50px; line-height: 50px;">
            <img src="../../public/img/krut.png" alt="Garuda" style="position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 50px; height: auto;">
            <span style="font-size: 29pt; font-weight: bold; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; display: inline-block; vertical-align: middle;">บันทึกข้อความ</span>
        </div>
        
        <div style="font-size: 16pt; line-height: 1.15; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif;">
            <div style="margin-bottom: 1px;">
                ส่วนราชการ โรงเรียนชัยนาทพิทยาคม &nbsp;&nbsp;&nbsp; อำเภอเมืองชัยนาท &nbsp;&nbsp;&nbsp; จังหวัดชัยนาท
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 1px;">
                <div style="width: 50%;">
                    ที่
                </div>
                <div style="width: 50%;">
                    วันที่ <span style="padding-left: 5px;"><?php echo to_thai_num($sign_date_thai); ?></span>
                </div>
            </div>
            <div style="margin-bottom: 6px; padding-bottom: 2px; border-bottom: 1.5px solid #000;">
                เรื่อง <span style="padding-left: 5px;">รายงานการประเมินการจัดการเรียนรู้ ประจำภาคเรียนที่ <?php echo to_thai_num($booking['semester']); ?> ปีการศึกษา <?php echo to_thai_num($booking['year']); ?></span>
            </div>
            
            <div style="margin-top: 4px; margin-bottom: 4px;">
                เรียน ผู้อำนวยการโรงเรียนชัยนาทพิทยาคม
            </div>
            
            <div style="display: flex; margin-bottom: 4px; line-height: 1.15;">
                <span style="white-space: nowrap; margin-right: 15px;">สิ่งที่ส่งมาด้วย</span>
                <div style="flex-grow: 1;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                        <span>๑. แบบรายงานการประเมินการจัดการเรียนรู้ (แบบนิเทศ ๐๑)</span>
                        <span>จำนวน ๑ ฉบับ</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                        <span>๒. แบบประเมินแผนหน่วยการเรียนรู้และแผนการจัดการเรียนรู้(รายชั่วโมง)</span>
                        <span>จำนวน ๓ ฉบับ</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                        <span>๓. แบบนิเทศการจัดการเรียนรู้</span>
                        <span>จำนวน ๓ ฉบับ</span>
                    </div>
                </div>
            </div>
            
            <div style="text-indent: 50px; text-align: justify; margin-bottom: 4px; line-height: 1.15;">
                ตามคำสั่งโรงเรียน <?php echo to_thai_num($order_no); ?> กำหนดให้ดำเนินการนิเทศการจัดการเรียนรู้ของครูผู้สอนประจำภาคเรียนที่ <?php echo to_thai_num($booking['semester']); ?> ปีการศึกษา <?php echo to_thai_num($booking['year']); ?> นั้น
            </div>
            
            <div style="text-indent: 50px; text-align: justify; margin-bottom: 4px; line-height: 1.15;">
                ข้าพเจ้า <?php echo htmlspecialchars($teacher_name); ?> ตำแหน่ง <?php echo htmlspecialchars($booking['t_pos'] ?? 'ครู'); ?><?php echo !empty($standing) ? ' ' . htmlspecialchars($standing) : ''; ?> กลุ่มสาระการเรียนรู้ <?php echo htmlspecialchars($booking['t_dept'] ?? '-'); ?> ได้รับการนิเทศรายวิชา <?php echo to_thai_num($booking['subject_name']); ?> รหัสวิชา <?php echo to_thai_num($booking['subject_code']); ?> เมื่อวันที่ <?php echo to_thai_num(format_th_date($booking['booking_date'])); ?> โดยผู้นิเทศ ดังนี้
                <div style="padding-left: 30px; margin-top: 2px; line-height: 1.15;">
                    <div style="display: flex; margin-bottom: 2px;">
                        <span style="width: 25px;">๑.</span>
                        <span style="width: 220px; display: inline-block;"><?php echo htmlspecialchars($booking['peer_name'] ?? '.......................................................'); ?></span>
                        <span>ตำแหน่ง ครูผู้ร่วมนิเทศ</span>
                    </div>
                    <div style="display: flex; margin-bottom: 2px;">
                        <span style="width: 25px;">๒.</span>
                        <span style="width: 220px; display: inline-block;"><?php echo htmlspecialchars($booking['head_name'] ?? '.......................................................'); ?></span>
                        <span>ตำแหน่ง ผู้นิเทศ</span>
                    </div>
                    <div style="display: flex; margin-bottom: 2px;">
                        <span style="width: 25px;">๓.</span>
                        <span style="width: 220px; display: inline-block;"><?php echo htmlspecialchars($booking['academic_name'] ?? '.......................................................'); ?></span>
                        <span>ตำแหน่ง คณะกรรมการนิเทศ</span>
                    </div>
                </div>
            </div>
            
            <div style="text-indent: 50px; text-align: justify; margin-bottom: 8px; line-height: 1.15;">
                บัดนี้การดำเนินการเสร็จสิ้นแล้ว จึงขอรายงานการประเมินการจัดการเรียนรู้ ดังนี้
                <div style="padding-left: 30px; margin-top: 2px; line-height: 1.15;">
                    <div style="display: flex; margin-bottom: 2px;">
                        <span>ผลการประเมินแผนหน่วยการเรียนรู้และแผนการจัดการเรียนรู้ อยู่ในระดับ <?php echo htmlspecialchars($doc_quality); ?></span>
                    </div>
                    <div style="display: flex;">
                        <span>ผลการนิเทศการจัดการเรียนรู้ อยู่ในระดับ <?php echo htmlspecialchars($class_quality); ?></span>
                    </div>
                </div>
            </div>
            
            <div style="text-indent: 50px; margin-bottom: 8px;">
                จึงเรียนมาเพื่อโปรดพิจารณา
            </div>
            
            <div style="display: flex; flex-direction: column; align-items: flex-end; margin-right: 50px; margin-bottom: 10px;">
                <div style="text-align: left; line-height: 1.15; position: relative;">
                    <?php if (!empty($booking['t_signature'])): ?>
                        <div style="position: absolute; left: 40px; top: -45px; height: 80px; pointer-events: none;">
                            <img src="<?php echo $booking['t_signature']; ?>" style="height: 80px; width: auto; max-width: 220px; mix-blend-mode: multiply;">
                        </div>
                    <?php endif; ?>
                    ลงชื่อ....................................................... ผู้รับการประเมิน<br>
                    <div style="text-align: center; width: 230px; margin-top: 2px;">
                        ( <?php echo htmlspecialchars($teacher_name); ?> )<br>
                        ตำแหน่ง <?php echo htmlspecialchars($booking['t_pos'] ?? 'ครู'); ?><?php echo !empty($standing) ? ' ' . htmlspecialchars($standing) : ''; ?>
                    </div>
                </div>
            </div>
            
            <table style="width: 100%; border: 1.5px solid #000; border-collapse: collapse; font-size: 16pt; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; margin-top: 5px;">
                <tr>
                    <td style="width: 50%; border: 1.5px solid #000; padding: 4px 8px; vertical-align: top;">
                        ความคิดเห็นของหัวหน้ากลุ่มสาระการเรียนรู้
                        <div style="margin-top: 2px; border-bottom: 1px dotted #000; height: 16px;"></div>
                        <div style="margin-top: 2px; border-bottom: 1px dotted #000; height: 16px;"></div>
                        <div style="margin-top: 6px; text-align: center; line-height: 1.15; position: relative; display: inline-block; width: 100%;">
                            <?php if (!empty($booking['head_signature'])): ?>
                                <div style="position: absolute; left: 50%; transform: translateX(-50%); top: -40px; height: 55px; pointer-events: none;">
                                    <img src="<?php echo $booking['head_signature']; ?>" style="height: 55px; width: auto; max-width: 200px; mix-blend-mode: multiply;">
                                </div>
                            <?php endif; ?>
                            ลงชื่อ.......................................................<br>
                            ( <?php echo htmlspecialchars($dept_head_name); ?> )<br>
                            หัวหน้ากลุ่มสาระการเรียนรู้<?php echo htmlspecialchars($booking['t_dept'] ?? '……………'); ?>
                        </div>
                    </td>
                    <td style="width: 50%; border: 1.5px solid #000; padding: 4px 8px; vertical-align: top;">
                        ความคิดเห็นของรองผู้อำนวยการกลุ่มบริหารวิชาการ
                        <div style="margin-top: 2px; border-bottom: 1px dotted #000; height: 16px;"></div>
                        <div style="margin-top: 2px; border-bottom: 1px dotted #000; height: 16px;"></div>
                        <div style="margin-top: 6px; text-align: center; line-height: 1.15;">
                            ลงชื่อ.......................................................<br>
                            ( นายธีรพงศ์ เพ็งชัย )<br>
                            รองผู้อำนวยการกลุ่มบริหารวิชาการ
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="border: 1.5px solid #000; padding: 4px 8px; vertical-align: top;">
                        ความคิดเห็นของผู้อำนวยการโรงเรียน
                        <div style="margin-top: 2px; border-bottom: 1px dotted #000; height: 16px;"></div>
                        <div style="margin-top: 2px; border-bottom: 1px dotted #000; height: 16px;"></div>
                        <div style="margin-top: 6px; text-align: center; line-height: 1.15; margin-bottom: 2px;">
                            ลงชื่อ.......................................................<br>
                            ( นายชูชาติ พารีสอน )<br>
                            ผู้อำนวยการโรงเรียนชัยนาทพิทยาคม
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- PAGE 3: INFORMATION & EVALUATION SUMMARY -->
    <div class="page page-break">
        <h5 class="fw-bold text-navy border-bottom pb-2 mb-4" style="font-size: 18pt;">ส่วนที่ 1: ข้อมูลประกอบการนิเทศการสอน</h5>
        
        <table class="table table-bordered table-eval align-middle mb-5">
            <tbody>
                <tr>
                    <td class="bg-light fw-bold" style="width: 250px;">รายวิชาที่นิเทศ</td>
                    <td><?php echo htmlspecialchars($booking['subject_code']); ?> <?php echo htmlspecialchars($booking['subject_name']); ?></td>
                </tr>
                <tr>
                    <td class="bg-light fw-bold">ระดับชั้น / ห้องเรียน</td>
                    <td>มัธยมศึกษาปีที่ <?php echo htmlspecialchars($booking['classroom']); ?> (ห้องสอน: <?php echo htmlspecialchars($booking['room_number']); ?>)</td>
                </tr>
                <tr>
                    <td class="bg-light fw-bold">วันที่และเวลาคาบเรียน</td>
                    <td>วันที <?php echo format_th_date($booking['booking_date']); ?> | คาบเรียนที่ <?php echo htmlspecialchars($booking['booking_period']); ?></td>
                </tr>
                <tr>
                    <td class="bg-light fw-bold">วัตถุประสงค์การประเมิน</td>
                    <td><?php echo htmlspecialchars($booking['evaluation_purpose']); ?></td>
                </tr>
                <tr>
                    <td class="bg-light fw-bold">ครูผู้ร่วมนิเทศ (Peer)</td>
                    <td><?php echo htmlspecialchars($booking['peer_name']); ?></td>
                </tr>
                <tr>
                    <td class="bg-light fw-bold">ครูผู้นิเทศ (Head/Deputy)</td>
                    <td><?php echo htmlspecialchars($booking['head_name']); ?></td>
                </tr>
                <tr>
                    <td class="bg-light fw-bold">คณะกรรมการวิชาการ (Academic)</td>
                    <td><?php echo htmlspecialchars($booking['academic_name'] ?? '-'); ?></td>
                </tr>
            </tbody>
        </table>

        <h5 class="fw-bold text-navy border-bottom pb-2 mb-4" style="font-size: 18pt;">ส่วนที่ 2: สรุปผลคะแนนการประเมิน</h5>
        
        <?php
        // Process scores for summaries
        $all_doc_scores = [];
        $all_class_scores = [];
        
        foreach ([$peer_eval, $head_eval, $academic_eval] as $ev) {
            if ($ev) {
                for ($i=1; $i<=5; $i++) {
                    if ($ev["doc_score_$i"] > 0) $all_doc_scores[] = $ev["doc_score_$i"];
                }
                for ($i=1; $i<=10; $i++) {
                    if ($ev["class_score_$i"] > 0) $all_class_scores[] = $ev["class_score_$i"];
                }
            }
        }
        
        $avg_doc = calculate_average($all_doc_scores);
        $percent_doc = ($avg_doc / 5) * 100;
        
        $avg_class = calculate_average($all_class_scores);
        $percent_class = ($avg_class / 5) * 100;

        $overall_avg = calculate_average(array_merge($all_doc_scores, $all_class_scores));
        $overall_percent = ($overall_avg / 5) * 100;
        ?>

        <div style="margin-bottom: 6px; text-align: right; font-size: 13pt; color: #64748b; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif;">(คะแนนเต็ม 5 คะแนน)</div>
        <div class="row g-3 text-center mb-4">
            <div class="col-4">
                <div class="card border-0 rounded-3" style="background: #f1f5f9; padding: 12px 8px;">
                    <div style="font-size: 13pt; color: #64748b; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; line-height: 1.3; margin-bottom: 4px;">คะแนนเฉลี่ยตรวจแผน<br>(เอกสาร)</div>
                    <div style="font-size: 28pt; font-weight: bold; color: #1e3a8a; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; line-height: 1;"><?php echo number_format($avg_doc, 2); ?></div>
                    <div style="font-size: 13pt; color: #16a34a; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; margin-top: 3px;"><?php echo number_format($percent_doc, 1); ?>%</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card border-0 rounded-3" style="background: #f1f5f9; padding: 12px 8px;">
                    <div style="font-size: 13pt; color: #64748b; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; line-height: 1.3; margin-bottom: 4px;">คะแนนเฉลี่ยการสอน<br>(ห้องเรียน)</div>
                    <div style="font-size: 28pt; font-weight: bold; color: #1e3a8a; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; line-height: 1;"><?php echo number_format($avg_class, 2); ?></div>
                    <div style="font-size: 13pt; color: #16a34a; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; margin-top: 3px;"><?php echo number_format($percent_class, 1); ?>%</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card border-0 rounded-3" style="background: #eff6ff; padding: 12px 8px;">
                    <div style="font-size: 13pt; color: #3b82f6; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; line-height: 1.3; margin-bottom: 4px;">คะแนนรวม<br>เฉลี่ยสะสม</div>
                    <div style="font-size: 28pt; font-weight: bold; color: #2563eb; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; line-height: 1;"><?php echo number_format($overall_avg, 2); ?></div>
                    <div style="font-size: 13pt; color: #3b82f6; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; margin-top: 3px;"><?php echo number_format($overall_percent, 1); ?>%</div>
                </div>
            </div>
        </div>

        <table class="table table-bordered table-eval text-center align-middle" style="font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; font-size: 15pt;">
            <thead class="table-light">
                <tr>
                    <th style="text-align: left;">องค์ประกอบการประเมิน</th>
                    <th>คะแนนเต็ม</th>
                    <th>Peer</th>
                    <th>Head</th>
                    <th>Academic</th>
                    <th>เฉลี่ย</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-start fw-bold">1. การประเมินแผนการจัดการเรียนรู้ (ตรวจแผน)</td>
                    <td>25</td>
                    <td>
                        <?php 
                        $p_doc = $peer_eval ? ($peer_eval['doc_score_1']+$peer_eval['doc_score_2']+$peer_eval['doc_score_3']+$peer_eval['doc_score_4']+$peer_eval['doc_score_5']) : 0;
                        echo $p_doc > 0 ? $p_doc : '-';
                        ?>
                    </td>
                    <td>
                        <?php 
                        $h_doc = $head_eval ? ($head_eval['doc_score_1']+$head_eval['doc_score_2']+$head_eval['doc_score_3']+$head_eval['doc_score_4']+$head_eval['doc_score_5']) : 0;
                        echo $h_doc > 0 ? $h_doc : '-';
                        ?>
                    </td>
                    <td>
                        <?php 
                        $a_doc = $academic_eval ? ($academic_eval['doc_score_1']+$academic_eval['doc_score_2']+$academic_eval['doc_score_3']+$academic_eval['doc_score_4']+$academic_eval['doc_score_5']) : 0;
                        echo $a_doc > 0 ? $a_doc : '-';
                        ?>
                    </td>
                    <td class="fw-bold text-navy"><?php echo number_format(calculate_average(array_filter([$p_doc, $h_doc, $a_doc])), 2); ?></td>
                </tr>
                <tr>
                    <td class="text-start fw-bold">2. การประเมินจัดกิจกรรมการเรียนรู้ (ห้องเรียน)</td>
                    <td>50</td>
                    <td>
                        <?php 
                        $p_class = 0; if ($peer_eval) { for($i=1;$i<=10;$i++) $p_class += $peer_eval["class_score_$i"]; }
                        echo $p_class > 0 ? $p_class : '-';
                        ?>
                    </td>
                    <td>
                        <?php 
                        $h_class = 0; if ($head_eval) { for($i=1;$i<=10;$i++) $h_class += $head_eval["class_score_$i"]; }
                        echo $h_class > 0 ? $h_class : '-';
                        ?>
                    </td>
                    <td>
                        <?php 
                        $a_class = 0; if ($academic_eval) { for($i=1;$i<=10;$i++) $a_class += $academic_eval["class_score_$i"]; }
                        echo $a_class > 0 ? $a_class : '-';
                        ?>
                    </td>
                    <td class="fw-bold text-navy"><?php echo number_format(calculate_average(array_filter([$p_class, $h_class, $a_class])), 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- PAGE 4: DOCUMENT SCORE DETAIL -->
    <div class="page page-break">
        <h5 class="fw-bold text-navy border-bottom pb-2 mb-4" style="font-size: 18pt;">ส่วนที่ 3: คะแนนประเมินแผนการจัดการเรียนรู้ (ตรวจแผน)</h5>
        <table class="table table-bordered table-eval align-middle">
            <thead>
                <tr>
                    <th rowspan="2" class="align-middle">หัวข้อการประเมินแผนการจัดการเรียนรู้</th>
                    <th colspan="3">คะแนนประเมิน (1-5)</th>
                    <th rowspan="2" class="align-middle">เฉลี่ย</th>
                </tr>
                <tr>
                    <th style="width: 70px;">Peer</th>
                    <th style="width: 70px;">Head</th>
                    <th style="width: 70px;">Acad</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($doc_items as $index => $item): 
                    $p_sc = $peer_eval ? $peer_eval["doc_score_" . ($index + 1)] : 0;
                    $h_sc = $head_eval ? $head_eval["doc_score_" . ($index + 1)] : 0;
                    $a_sc = $academic_eval ? $academic_eval["doc_score_" . ($index + 1)] : 0;
                    $avg = calculate_average(array_filter([$p_sc, $h_sc, $a_sc]));
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item); ?></td>
                    <td class="text-center"><?php echo $p_sc > 0 ? $p_sc : '-'; ?></td>
                    <td class="text-center"><?php echo $h_sc > 0 ? $h_sc : '-'; ?></td>
                    <td class="text-center"><?php echo $a_sc > 0 ? $a_sc : '-'; ?></td>
                    <td class="text-center fw-bold text-navy"><?php echo $avg > 0 ? number_format($avg, 2) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-4 p-3 bg-light rounded-3" style="font-size: 16pt; line-height: 1.25;">
            <strong>ข้อเสนอแนะเกี่ยวกับแผนการจัดการเรียนรู้:</strong>
            <ul class="mt-2 mb-0">
                <?php if ($peer_eval && !empty($peer_eval['doc_comments'])): ?>
                    <li><strong>ครูผู้ร่วมนิเทศ:</strong> <?php echo htmlspecialchars($peer_eval['doc_comments']); ?></li>
                <?php endif; ?>
                <?php if ($head_eval && !empty($head_eval['doc_comments'])): ?>
                    <li><strong>ครูผู้นิเทศ (หัวหน้า/รอง):</strong> <?php echo htmlspecialchars($head_eval['doc_comments']); ?></li>
                <?php endif; ?>
                <?php if ($academic_eval && !empty($academic_eval['doc_comments'])): ?>
                    <li><strong>คณะกรรมการวิชาการ:</strong> <?php echo htmlspecialchars($academic_eval['doc_comments']); ?></li>
                <?php endif; ?>
                <?php if (!$peer_eval && !$head_eval && !$academic_eval): ?>
                    <li class="text-muted">ไม่มีข้อเสนอแนะ</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- PAGE 5: CLASSROOM TEACHING SCORE DETAIL -->
    <div class="page page-break">
        <h5 class="fw-bold text-navy border-bottom pb-2 mb-4" style="font-size: 18pt;">ส่วนที่ 4: คะแนนประเมินการจัดการเรียนรู้ในห้องเรียน</h5>
        <table class="table table-bordered table-eval align-middle">
            <thead>
                <tr>
                    <th rowspan="2" class="align-middle">หัวข้อการประเมินทักษะการสอนในชั้นเรียน</th>
                    <th colspan="3">คะแนนประเมิน (1-5)</th>
                    <th rowspan="2" class="align-middle">เฉลี่ย</th>
                </tr>
                <tr>
                    <th style="width: 70px;">Peer</th>
                    <th style="width: 70px;">Head</th>
                    <th style="width: 70px;">Acad</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($class_items as $index => $item): 
                    $p_sc = $peer_eval ? $peer_eval["class_score_" . ($index + 1)] : 0;
                    $h_sc = $head_eval ? $head_eval["class_score_" . ($index + 1)] : 0;
                    $a_sc = $academic_eval ? $academic_eval["class_score_" . ($index + 1)] : 0;
                    $avg = calculate_average(array_filter([$p_sc, $h_sc, $a_sc]));
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item); ?></td>
                    <td class="text-center"><?php echo $p_sc > 0 ? $p_sc : '-'; ?></td>
                    <td class="text-center"><?php echo $h_sc > 0 ? $h_sc : '-'; ?></td>
                    <td class="text-center"><?php echo $a_sc > 0 ? $a_sc : '-'; ?></td>
                    <td class="text-center fw-bold text-navy"><?php echo $avg > 0 ? number_format($avg, 2) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-4 p-3 bg-light rounded-3" style="font-size: 16pt; line-height: 1.25;">
            <strong>ข้อเสนอแนะสำหรับการจัดกิจกรรมการเรียนรู้ในชั้นเรียน:</strong>
            <ul class="mt-2 mb-0">
                <?php if ($peer_eval && !empty($peer_eval['class_comments'])): ?>
                    <li><strong>ครูผู้ร่วมนิเทศ:</strong> <?php echo htmlspecialchars($peer_eval['class_comments']); ?></li>
                <?php endif; ?>
                <?php if ($head_eval && !empty($head_eval['class_comments'])): ?>
                    <li><strong>ครูผู้นิเทศ (หัวหน้า/รอง):</strong> <?php echo htmlspecialchars($head_eval['class_comments']); ?></li>
                <?php endif; ?>
                <?php if ($academic_eval && !empty($academic_eval['class_comments'])): ?>
                    <li><strong>คณะกรรมการวิชาการ:</strong> <?php echo htmlspecialchars($academic_eval['class_comments']); ?></li>
                <?php endif; ?>
                <?php if (!$peer_eval && !$head_eval && !$academic_eval): ?>
                    <li class="text-muted">ไม่มีข้อเสนอแนะ</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- PAGE 6: POST TEACHING REFLECTIONS & SIGNATURES -->
    <div class="page page-break">
        <h5 class="fw-bold text-navy border-bottom pb-2 mb-4" style="font-size: 18pt;">ส่วนที่ 5: บันทึกผลหลังการจัดการเรียนรู้ของครูผู้รับนิเทศ</h5>
        
        <div class="p-4 border rounded-4 bg-light bg-opacity-50 mb-5" style="min-height: 250px; font-size: 16pt; line-height: 1.25;">
            <?php 
            if (!empty($booking['post_teaching_record'])):
                $post_record = json_decode($booking['post_teaching_record'], true);
                if (is_array($post_record)):
            ?>
                <div class="mb-3">
                    <strong class="text-navy">ด้านความรู้</strong>
                    <div class="ps-3 mt-1 text-secondary" style="white-space: pre-wrap;"><?php echo htmlspecialchars($post_record['knowledge'] ?? '-'); ?></div>
                </div>
                <div class="mb-3">
                    <strong class="text-navy">ด้านคุณลักษณะอันพึงประสงค์</strong>
                    <div class="ps-3 mt-1 text-secondary" style="white-space: pre-wrap;"><?php echo htmlspecialchars($post_record['characteristics'] ?? '-'); ?></div>
                </div>
                <div class="mb-3">
                    <strong class="text-navy">ด้านสมรรถนะ</strong>
                    <div class="ps-3 mt-1 text-secondary" style="white-space: pre-wrap;"><?php echo htmlspecialchars($post_record['competencies'] ?? '-'); ?></div>
                </div>
                <div class="mb-3">
                    <strong class="text-navy"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>ปัญหาที่พบ</strong>
                    <div class="ps-3 mt-1 text-secondary" style="white-space: pre-wrap;"><?php echo htmlspecialchars($post_record['problems'] ?? '-'); ?></div>
                </div>
                <div class="mb-3">
                    <strong class="text-navy"><i class="bi bi-lightbulb-fill text-success me-1"></i>แนวทางการแก้ไขปัญหา</strong>
                    <div class="ps-3 mt-1 text-secondary" style="white-space: pre-wrap;"><?php echo htmlspecialchars($post_record['solutions'] ?? '-'); ?></div>
                </div>
            <?php 
                else:
                    echo '<div style="white-space: pre-wrap;">' . htmlspecialchars($booking['post_teaching_record']) . '</div>';
                endif;
            else:
                echo '<span class="text-muted">ครูผู้รับนิเทศยังไม่ได้กรอกบันทึกหลังการสอน</span>';
            endif;
            ?>
        </div>
    </div>

    <!-- PAGE 7: PHOTOS -->
    <?php
    $photo_teaching = '';
    $caption_teaching = 'ภาพการจัดการเรียนรู้';
    $photo_supervision = '';
    $caption_supervision = 'ภาพการนิเทศการสอน';
    $standing = ($booking['t_standing'] && $booking['t_standing'] !== 'ไม่มีวิทยฐานะ') ? $booking['t_standing'] : '';

    if (!empty($booking['post_teaching_record'])) {
        $post_record = json_decode($booking['post_teaching_record'], true);
        if (is_array($post_record)) {
            $photo_teaching = $post_record['photo_teaching'] ?? '';
            $caption_teaching = !empty($post_record['caption_teaching']) ? $post_record['caption_teaching'] : 'ภาพการจัดการเรียนรู้';
            $photo_supervision = $post_record['photo_supervision'] ?? '';
            $caption_supervision = !empty($post_record['caption_supervision']) ? $post_record['caption_supervision'] : 'ภาพการนิเทศการสอน';
        }
    }
    ?>
    <div class="page page-break">
        <div style="text-align: center; margin-bottom: 15px; line-height: 1.25;">
            <span style="font-size: 20pt; font-weight: bold; display: block;">ส่วนที่ 6: ภาพประกอบการประเมินการจัดการเรียนรู้และการนิเทศการสอน</span>
            <span style="font-size: 16pt; display: block; margin-top: 3px;">
                ชื่อ-สกุลผู้รับการประเมิน <span style="font-weight: bold;"><?php echo htmlspecialchars($teacher_name); ?></span> ตำแหน่ง <?php echo htmlspecialchars($booking['t_pos'] ?? 'ครู'); ?><?php echo !empty($standing) ? ' ' . htmlspecialchars($standing) : ''; ?>
            </span>
            <span style="font-size: 16pt; display: block;">
                กลุ่มสาระการเรียนรู้ <?php echo htmlspecialchars($booking['t_dept'] ?? '-'); ?> รายวิชา <?php echo htmlspecialchars($booking['subject_name'] ?? '-'); ?> รหัสวิชา <?php echo htmlspecialchars($booking['subject_code'] ?? '-'); ?> ชั้น ม.<?php echo htmlspecialchars($booking['classroom'] ?? '-'); ?>
            </span>
            <span style="font-size: 16pt; display: block;">
                วัน/เดือน/ปีที่รับการประเมิน <?php echo format_th_date($booking['booking_date']); ?>
            </span>
            <div style="border-bottom: 1px solid #000; margin-top: 8px; width: 100%;"></div>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 20px; align-items: center; justify-content: center; min-height: 180mm;">
            <!-- PHOTO 1 -->
            <div style="width: 100%; max-width: 550px; text-align: center;">
                <div style="width: 100%; height: 230px; border: 2px dashed #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f8fafc; overflow: hidden;">
                    <?php if ($photo_teaching): ?>
                        <img src="../../<?php echo htmlspecialchars($photo_teaching); ?>" alt="ภาพการจัดการเรียนรู้" style="max-height: 100%; object-fit: contain;">
                    <?php else: ?>
                        <span class="text-muted small"><i class="bi bi-image fs-1 d-block mb-2"></i>ยังไม่มีภาพถ่ายการจัดการเรียนรู้</span>
                    <?php endif; ?>
                </div>
                <div style="margin-top: 5px; font-size: 16pt; font-weight: bold;">
                    ภาพที่ 1 <?php echo htmlspecialchars($caption_teaching); ?>
                </div>
            </div>
            
            <!-- PHOTO 2 -->
            <div style="width: 100%; max-width: 550px; text-align: center; margin-top: 5px;">
                <div style="width: 100%; height: 230px; border: 2px dashed #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f8fafc; overflow: hidden;">
                    <?php if ($photo_supervision): ?>
                        <img src="../../<?php echo htmlspecialchars($photo_supervision); ?>" alt="ภาพการนิเทศการสอน" style="max-height: 100%; object-fit: contain;">
                    <?php else: ?>
                        <span class="text-muted small"><i class="bi bi-image-fill fs-1 d-block mb-2"></i>ยังไม่มีภาพถ่ายการนิเทศการสอน</span>
                    <?php endif; ?>
                </div>
                <div style="margin-top: 5px; font-size: 16pt; font-weight: bold;">
                    ภาพที่ 2 <?php echo htmlspecialchars($caption_supervision); ?>
                </div>
            </div>
        </div>
    </div>


    <!-- PAGE 8: SIGNATURES -->
    <div class="page page-break">


        <h5 class="fw-bold text-navy border-bottom pb-2 mb-4" style="font-size: 18pt;">ส่วนที่ 7: ลงลายมือชื่อคณะกรรมการ</h5>
        
        <div class="row sig-block text-center" style="font-size: 16pt; line-height: 1.25;">
            <div class="col-6 mb-5" style="position: relative;">
                <?php if (!empty($booking['t_signature'])): ?>
                    <div style="position: absolute; left: 50%; transform: translateX(-50%); top: -40px; height: 55px; pointer-events: none;">
                        <img src="<?php echo $booking['t_signature']; ?>" style="height: 55px; width: auto; max-width: 200px; mix-blend-mode: multiply;">
                    </div>
                <?php endif; ?>
                ลงชื่อ.......................................................<br>
                ผู้สอน<br>
                ( <?php echo htmlspecialchars($teacher_name); ?> )<br>
                ตำแหน่ง <?php echo htmlspecialchars(($booking['t_pos'] ?? 'ครู') . ($booking['t_standing'] ? ' ' . $booking['t_standing'] : '')); ?><br>
                <?php 
                $sign_date_str = 'วันที่ ........ เดือน .................... พ.ศ. ............';
                if (!empty($booking['post_teaching_record'])) {
                    $post_record = json_decode($booking['post_teaching_record'], true);
                    if (is_array($post_record) && !empty($post_record['sign_date'])) {
                        $sign_date_str = format_th_date($post_record['sign_date']);
                    }
                }
                echo htmlspecialchars($sign_date_str);
                ?>
            </div>
            <div class="col-6 mb-5" style="position: relative;">
                <?php if (!empty($booking['peer_signature'])): ?>
                    <div style="position: absolute; left: 50%; transform: translateX(-50%); top: -40px; height: 55px; pointer-events: none;">
                        <img src="<?php echo $booking['peer_signature']; ?>" style="height: 55px; width: auto; max-width: 200px; mix-blend-mode: multiply;">
                    </div>
                <?php endif; ?>
                ลงชื่อ.......................................................<br>
                ( <?php echo htmlspecialchars($booking['peer_name']); ?> )<br>
                <strong>ครูผู้ร่วมนิเทศ (Peer)</strong>
            </div>
            <div class="col-6" style="position: relative;">
                <?php if (!empty($booking['head_signature'])): ?>
                    <div style="position: absolute; left: 50%; transform: translateX(-50%); top: -40px; height: 55px; pointer-events: none;">
                        <img src="<?php echo $booking['head_signature']; ?>" style="height: 55px; width: auto; max-width: 200px; mix-blend-mode: multiply;">
                    </div>
                <?php endif; ?>
                ลงชื่อ.......................................................<br>
                ( <?php echo htmlspecialchars($booking['head_name']); ?> )<br>
                <strong>ครูผู้นิเทศ (หัวหน้า/รองกลุ่มสาระฯ)</strong>
            </div>
            <div class="col-6" style="position: relative;">
                <?php if (!empty($booking['academic_signature'])): ?>
                    <div style="position: absolute; left: 50%; transform: translateX(-50%); top: -40px; height: 55px; pointer-events: none;">
                        <img src="<?php echo $booking['academic_signature']; ?>" style="height: 55px; width: auto; max-width: 200px; mix-blend-mode: multiply;">
                    </div>
                <?php endif; ?>
                ลงชื่อ.......................................................<br>
                ( <?php echo htmlspecialchars($booking['academic_name'] ?? '.......................................................'); ?> )<br>
                <strong>ผู้แทนคณะกรรมการวิชาการ</strong>
            </div>
        </div>
    </div>    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const imgs = document.getElementsByTagName("img");
        for (let i = 0; i < imgs.length; i++) {
            const img = imgs[i];
            if (img.src && img.src.startsWith("data:image/")) {
                processSignatureTransparency(img);
            }
        }

        function processSignatureTransparency(imgElement) {
            const originalSrc = imgElement.src;
            const tempImg = new Image();
            tempImg.onload = function() {
                const canvas = document.createElement("canvas");
                canvas.width = tempImg.width;
                canvas.height = tempImg.height;
                const ctx = canvas.getContext("2d");
                ctx.drawImage(tempImg, 0, 0);
                try {
                    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const data = imgData.data;
                    let modified = false;
                    for (let j = 0; j < data.length; j += 4) {
                        const r = data[j];
                        const g = data[j+1];
                        const b = data[j+2];
                        if (r > 240 && g > 240 && b > 240) {
                            data[j+3] = 0;
                            modified = true;
                        }
                    }
                    if (modified) {
                        ctx.putImageData(imgData, 0, 0);
                        imgElement.src = canvas.toDataURL("image/png");
                    }
                } catch (e) {
                    console.error("Error transparency:", e);
                }
            };
            tempImg.src = originalSrc;
        }
    });
    </script>

</body>
</html>
