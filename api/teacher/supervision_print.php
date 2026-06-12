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
        "1.1 จัดทำแผนการจัดการเรียนรู้เสร็จล่วงหน้าและถูกต้องสมบูรณ์ (ดูจากคะแนนภาพรวมของการประเมินแผนการจัดการเรียนรู้รายชั่วโมง)",
        "1.2 ห้องเรียนสะอาด เรียบร้อย เหมาะสำหรับการจัดการเรียนรู้",
        "1.3 เช็คชื่อนักเรียน ซักถามเหตุผลของนักเรียนที่เข้าห้องเรียนสายหรือติดตามนักเรียนที่ขาดเรียน",
        "1.4 เตรียมวัสดุ-อุปกรณ์ สื่อ กิจกรรมตามแผนฯ ก่อนเข้าสอน",
        "1.5 ใช้วิธีการนำเข้าสู่บทเรียนที่น่าสนใจเหมาะสมกับเวลาและเนื้อหา ตามแผนการจัดการเรียนรู้ โดยมีการทบทวนความรู้ ทักษะ หรือประสบการณ์เดิม เช่น การใช้คำถาม แบบฝึก กิจกรรม หรือ เกมสั้น ๆ ฯลฯ",
        "1.6 แจ้งหรือบอกผลการเรียนรู้/จุดประสงค์การเรียนรู้และแนวทางการเรียน ที่นักเรียนมีส่วนร่วมในการกำหนด",
        "2.1 เนื้อหา/มโนทัศน์ที่สอนหรือจัดให้ผู้เรียนเรียนรู้ หรือฝึกฝนมีความถูกต้อง และสอดคล้องกับผลการเรียนรู้/จุดประสงค์การเรียนรู้ตรงตามหลักสูตร",
        "2.2 จัดกิจกรรมการเรียนรู้สอดคล้องตาม ตัวชี้วัด/ผลการเรียนรู้และจุดประสงค์การเรียนรู้ ของหลักสูตรรายวิชา",
        "2.3 ครูดำเนินการสอนตามขั้นตอนที่กำหนดไว้ในแผนการจัดการเรียนรู้",
        "2.4 ใช้วิธีการ/เทคนิคการสอนเหมาะสมกับเนื้อหา มีความท้าทายและมีระดับความยากง่ายเหมาะสมกับวัยและพัฒนาการของนักเรียน",
        "2.5 กิจกรรมการเรียนรู้หรือตัวอย่างที่ใช้ประกอบเรื่องที่สอน มีความหลากหลาย เหมาะกับเนื้อหา เชื่อมโยง สอดคล้องและสัมพันธ์กับชีวิตจริงของนักเรียนหรือบริบทชุมชน",
        "2.6 จัดกิจกรรมการเรียนรู้เพื่อให้นักเรียนมีผลงานหรือผลการปฏิบัติสะท้อนถึงความสามารถในการเรียนรู้ (Cognitive Abilities) เน้นให้นักเรียนได้เรียนรู้โดยผ่านกระบวนการคิด ได้แก่ ความยืดหยุ่นในการคิดหรือการคิดเชื่อมโยงสิ่งต่าง ๆ ความคิดสร้างสรรค์ หรือการคิดเชิงนวัตกรรม กระบวนการสืบเสาะหาความหมายหรือกระบวนการตัดสินใจ กระบวนการคิดเชิงเหตุผลหรือการให้เหตุผลเชิงตรรกะ กระบวนการคิดเชิงระบบ โดยให้นักเรียนลงมือปฏิบัติกิจกรรมที่ต้องใช้ความรู้หรือบูรณาการทักษะหลากหลายต่าง ๆ ลงสู่การปฏิบัติกิจกรรมการเรียนรู้ตามจุดประสงค์การเรียนรู้และผู้เรียนได้ประยุกต์ใช้ทักษะที่ได้รับการพัฒนาในสถานการณ์หรือแก้ปัญหาใหม่ๆ",
        "2.7 ตรวจสอบความเข้าใจของนักเรียนระหว่างการสอน ด้วยการสังเกตหรือค้นหาข้อผิดพลาดในการปฏิบัติหรือมโนทัศน์ที่คลาดเคลื่อนของนักเรียนในระหว่างการเรียนรู้โดยการใช้คำถาม ฯลฯ",
        "2.8 มีการประเมินผลระหว่างการเรียนรู้โดยใช้วิธีการที่เหมาะสม เช่น การใช้คำถามแบบทดสอบ การปฏิบัติ ฯลฯ",
        "2.9 มีการนำผลการสังเกต หรือผลการค้นหา หรือผลการประเมินระหว่างเรียนรู้สะท้อนกลับให้ผู้เรียน",
        "2.10 จัดกิจกรรมการเรียนรู้ เพื่อให้ผู้เรียนมีผลงานหรือผลการปฏิบัติสะท้อนการได้รับการพัฒนาทักษะพื้นฐาน (Basic Skills) ผู้เรียนได้รับการฝึกทักษะการแสดงออกและทักษะการสื่อสาร โดยการนำเสนอผลงาน ด้วยการพูด การเขียน หรือการแสดงออกในรูปแบบอื่น ๆ อย่างเป็นระบบ น่าสนใจ มีความถูกต้อง ความคล่องแคล่ว หรือความชำนาญ",
        "2.11 ผู้เรียนได้รับการฝึกทักษะให้สรุปองค์ความรู้หรือประสบการณ์ใหม่ด้วยตนเอง เช่น แผนที่ความคิด ตารางวิเคราะห์ การทดลองปฏิบัติ การนำเสนอ ฯลฯ",
        "2.12 ใช้สื่อการสอนที่หลากหลายได้อย่างเหมาะสมและคุ้มค่า ช่วยกระตุ้นให้นักเรียน คิดวิเคราะห์ เปรียบเทียบจากสื่อการเรียนหรือตัวอย่าง และช่วยในการเรียนรู้ของนักเรียนให้บรรลุวัตถุประสงค์ของบทเรียน",
        "2.13 มอบหมายงานหรือแบบฝึกหัดเป็นไปตามเป้าหมายหรือวัตถุประสงค์การเรียนรู้ที่กำหนดไว้ในแผนการจัดการเรียนรู้ เหมาะสมกับวัย พัฒนาการ บริบทของนักเรียนและชั้นเรียน คุ้มค่าและเป็นประโยชน์ต่อการเรียนรู้ของนักเรียนเป็นรายบุคคล หรือรายกลุ่ม",
        "3.1 จัดกิจกรรมการเรียนรู้ เพื่อให้ผลงานหรือผลการปฏิบัติสะท้อนทักษะชีวิตและทักษะในการทำงาน (Cross-functional Skills) ตามที่กำหนดไว้",
        "3.2 ให้ความสนใจนักเรียนอย่างทั่วถึง โดยมีการเข้าถึงผู้เรียนที่ยังไม่พร้อมที่จะเรียนรู้ใหม่ และช่วยเหลือเด็กนักเรียนที่เรียนช้า หรือส่งเสริมเด็กนักเรียนที่เรียนเก่ง",
        "3.3 อบรมบ่มนิสัยให้ผู้เรียนมีคุณธรรมจริยธรรม ค่านิยมที่ดีงาม คุณลักษณะอันพึงประสงค์ สมรรถนะที่สำคัญ และทักษะในศตวรรษที่ 21 ตามที่กำหนดไว้ในแผนการจัดการเรียนรู้ อย่างครบถ้วน",
        "3.4 มีการบริหารจัดการชั้นเรียน โดยการเสริมแรงแก่นักเรียนอย่างเหมาะสม โดยเน้นการมีปฏิสัมพันธ์เชิงบวก เพื่อกระตุ้นให้นักเรียนเกิดแรงจูงใจในการเรียนรู้",
        "4.1 สรุปบทเรียนได้กระชับ ชัดเจน ช่วยให้นักเรียนเกิดแนวคิดตรงจุดประสงค์การเรียนรู้",
        "4.2 สั่งงานหรือให้นักเรียนเตรียมตัวล่วงหน้าสำหรับการเรียนครั้งต่อไป เพื่อให้นักเรียนได้รับการกระตุ้นให้ศึกษา ค้นคว้า ฝึกฝนเพิ่มเติมภายหลังจบบทเรียน",
        "5.1 ใช้วิธีการวัดผลการเรียนรู้ที่หลากหลาย เน้นสภาพจริงและตรงตามจุดประสงค์ โดยให้นักเรียนได้ประเมินตนเองหรือเพื่อนประเมิน",
        "5.2 ประสิทธิภาพของการใช้เวลาในการสอน/สอนเต็มเวลา ใช้เวลาเหมาะสมกับเนื้อหา",
        "6.1 การควบคุมอารมณ์ขณะสอนและพฤติกรรมการแสดงออกเป็นแบบอย่างที่ดี",
        "6.2 การใช้ภาษา น้ำเสียงเหมาะสมและชัดเจนเป็นแบบอย่างที่ดี",
        "6.3 การยอมรับความคิดเห็นของนักเรียนและให้เกียรตินักเรียน",
        "6.4 การแต่งกายสุภาพเรียบร้อยเป็นแบบอย่างที่ดี"
    ];

    function calculate_average($scores) {
        $valid_scores = array_filter($scores, function($s) { return $s > -1 && $s !== null && $s !== ''; });
        if (count($valid_scores) === 0) return 0;
        return array_sum($valid_scores) / count($valid_scores);
    }

    function parse_evaluator_role($comments) {
        $res = [
            'comments' => $comments,
            'title' => ''
        ];
        if (strpos($comments, '[ผู้ประเมิน: ') === 0) {
            $close_bracket = strpos($comments, ']');
            if ($close_bracket !== false && $close_bracket > 12) {
                $json_str = substr($comments, 12, $close_bracket - 12);
                $info = json_decode($json_str, true);
                if (is_array($info)) {
                    $res['comments'] = trim(substr($comments, $close_bracket + 1));
                    if ($info['type'] === 'peer') {
                        $title = '';
                        if (!empty($info['position']) && $info['position'] !== '-') $title .= 'ตำแหน่ง ' . $info['position'];
                        if (!empty($info['standing']) && $info['standing'] !== '-') {
                            $title .= ($title ? ' ' : '') . 'วิทยฐานะ ' . $info['standing'];
                        }
                        $res['title'] = $title;
                    } elseif ($info['type'] === 'head') {
                        $res['title'] = 'ตำแหน่ง ' . ($info['sub_role'] ?? 'หัวหน้ากลุ่มสาระการเรียนรู้');
                    } elseif ($info['type'] === 'academic') {
                        $sub = $info['sub_role'] ?? '';
                        if ($sub === 'อื่น ๆ' && !empty($info['custom_role'])) {
                            $res['title'] = 'ตำแหน่ง ' . $info['custom_role'];
                        } else {
                            $res['title'] = 'ตำแหน่ง ' . $sub;
                        }
                    }
                }
            }
        }
        return $res;
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
            $sc = $ev["doc_score_$i"];
            if ($sc !== null && $sc !== '') {
                $all_doc_scores[] = (int)$sc;
            }
        }
        for ($i=1; $i<=31; $i++) {
            $sc = $ev["class_score_$i"];
            if ($sc !== null && $sc !== '') {
                $all_class_scores[] = (int)$sc;
            }
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
        .table-eval, .table-eval th, .table-eval td {
            border-color: #94a3b8 !important;
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
        <div style="position: relative; text-align: center; margin-bottom: 20px; height: 60px; line-height: 60px;">
            <img src="../../public/img/krut.png" alt="Garuda" style="position: absolute; left: 0; top: 0; width: 60px; height: auto;">
            <span style="font-size: 29pt; font-weight: bold; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; display: inline-block; vertical-align: middle;">บันทึกข้อความ</span>
        </div>
        
        <div style="font-size: 16pt; line-height: 1.6; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif;">
            <div style="margin-bottom: 4px;">
                <strong>ส่วนราชการ</strong> โรงเรียนชัยนาทพิทยาคม &nbsp;&nbsp;&nbsp; อำเภอเมืองชัยนาท &nbsp;&nbsp;&nbsp; จังหวัดชัยนาท
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <div style="width: 50%;">
                    <strong>ที่</strong> <span style="padding-left: 5px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                </div>
                <div style="width: 50%;">
                    <strong>วันที่</strong> <span style="padding-left: 5px;"><?php echo htmlspecialchars($sign_date_thai); ?></span>
                </div>
            </div>
            <div style="margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1.5px solid #000;">
                <strong>เรื่อง</strong> <span style="padding-left: 5px;">รายงานการประเมินการจัดการเรียนรู้ ประจำภาคเรียนที่ <?php echo htmlspecialchars($booking['semester']); ?> ปีการศึกษา <?php echo htmlspecialchars($booking['year']); ?></span>
            </div>
            
            <div style="margin-top: 10px; margin-bottom: 10px;">
                <strong>เรียน</strong> ผู้อำนวยการโรงเรียนชัยนาทพิทยาคม
            </div>
            
            <div style="margin-bottom: 10px; padding-left: 30px;">
                <strong>สิ่งที่ส่งมาด้วย</strong>
                <div style="padding-left: 30px; line-height: 1.4;">
                    1. แบบรายงานการประเมินการจัดการเรียนรู้ (แบบนิเทศ 01) <span style="float: right;">จำนวน 1 ฉบับ</span><br>
                    2. แบบประเมินแผนหน่วยการเรียนรู้และแผนการจัดการเรียนรู้ (รายชั่วโมง) <span style="float: right;">จำนวน 3 ฉบับ</span><br>
                    3. แบบนิเทศการจัดการเรียนรู้ <span style="float: right;">จำนวน 3 ฉบับ</span><br>
                    4. ภาพถ่ายประกอบการนิเทศ
                </div>
            </div>
            
            <div style="text-indent: 70px; text-align: justify; margin-bottom: 10px; line-height: 1.35;">
                ตามคำสั่งโรงเรียน <?php echo htmlspecialchars($order_no); ?> กำหนดให้ดำเนินการนิเทศการจัดการเรียนรู้ของครูผู้สอนประจำภาคเรียนที่ <?php echo htmlspecialchars($booking['semester']); ?> ปีการศึกษา <?php echo htmlspecialchars($booking['year']); ?> นั้น
            </div>
            
            <div style="text-indent: 70px; text-align: justify; margin-bottom: 10px; line-height: 1.35;">
                ข้าพเจ้า <span style="font-weight: bold;"><?php echo htmlspecialchars($teacher_name); ?></span> ตำแหน่ง <?php echo htmlspecialchars($booking['t_pos'] ?? 'ครู'); ?><?php echo !empty($standing) ? ' ' . htmlspecialchars($standing) : ''; ?> กลุ่มสาระการเรียนรู้ <?php echo htmlspecialchars($booking['t_dept'] ?? '-'); ?> ได้รับการนิเทศรายวิชา <span style="font-weight: bold;"><?php echo htmlspecialchars($booking['subject_name']); ?></span> รหัสวิชา <?php echo htmlspecialchars($booking['subject_code']); ?> เมื่อวันที่ <?php echo format_th_date($booking['booking_date']); ?> โดยผู้นิเทศ ดังนี้
                <div style="padding-left: 40px; margin-top: 3px; line-height: 1.35;">
                    1. <?php echo htmlspecialchars($booking['peer_name'] ?? '.......................................................'); ?> ตำแหน่ง ครูผู้ร่วมนิเทศ<br>
                    2. <?php echo htmlspecialchars($booking['head_name'] ?? '.......................................................'); ?> ตำแหน่ง ผู้ร่วมนิเทศในตำแหน่งหัวหน้าหรือรอง<br>
                    3. <?php echo htmlspecialchars($booking['academic_name'] ?? '.......................................................'); ?> ตำแหน่ง ผู้ร่วมนิเทศในคณะกรรมการกลุ่มบริหารวิชาการ
                </div>
            </div>
            
            <div style="text-indent: 70px; text-align: justify; margin-bottom: 15px; line-height: 1.35;">
                บัดนี้การดำเนินการเสร็จสิ้นแล้ว จึงขอรายงานการประเมินการจัดการเรียนรู้ ดังนี้
                <div style="padding-left: 40px; margin-top: 3px; line-height: 1.35;">
                    • ผลการประเมินแผนหน่วยการเรียนรู้และแผนการจัดการเรียนรู้ อยู่ในระดับ <span style="font-weight: bold;"><?php echo htmlspecialchars($doc_quality); ?></span><br>
                    • ผลการนิเทศการจัดการเรียนรู้ อยู่ในระดับ <span style="font-weight: bold;"><?php echo htmlspecialchars($class_quality); ?></span>
                </div>
            </div>
            
            <div style="text-indent: 70px; margin-bottom: 25px;">
                จึงเรียนมาเพื่อโปรดพิจารณา
            </div>
            
            <div style="display: flex; flex-direction: column; align-items: flex-end; margin-right: 50px; margin-bottom: 25px;">
                <div style="text-align: left; line-height: 1.6;">
                    ลงชื่อ....................................................... ผู้รับการประเมิน<br>
                    <div style="text-align: center; width: 230px; margin-top: 5px;">
                        ( <?php echo htmlspecialchars($teacher_name); ?> )<br>
                        ตำแหน่ง <?php echo htmlspecialchars($booking['t_pos'] ?? 'ครู'); ?><?php echo !empty($standing) ? ' ' . htmlspecialchars($standing) : ''; ?>
                    </div>
                </div>
            </div>
            
            <table style="width: 100%; border: 1.5px solid #000; border-collapse: collapse; font-size: 16pt; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif; margin-top: 15px;">
                <tr>
                    <td style="width: 50%; border: 1.5px solid #000; padding: 10px; vertical-align: top; height: 130px;">
                        <strong>ความคิดเห็นของหัวหน้ากลุ่มสาระการเรียนรู้</strong>
                        <div style="margin-top: 8px; border-bottom: 1px dotted #000; height: 20px;"></div>
                        <div style="margin-top: 8px; border-bottom: 1px dotted #000; height: 20px;"></div>
                        <div style="margin-top: 15px; text-align: center; line-height: 1.3;">
                            ลงชื่อ.......................................................<br>
                            ( <?php echo htmlspecialchars($dept_head_name); ?> )<br>
                            หัวหน้ากลุ่มสาระการเรียนรู้<?php echo htmlspecialchars($booking['t_dept'] ?? '……………'); ?>
                        </div>
                    </td>
                    <td style="width: 50%; border: 1.5px solid #000; padding: 10px; vertical-align: top; height: 130px;">
                        <strong>ความคิดเห็นของรองผู้อำนวยการกลุ่มบริหารวิชาการ</strong>
                        <div style="margin-top: 8px; border-bottom: 1px dotted #000; height: 20px;"></div>
                        <div style="margin-top: 8px; border-bottom: 1px dotted #000; height: 20px;"></div>
                        <div style="margin-top: 15px; text-align: center; line-height: 1.3;">
                            ลงชื่อ.......................................................<br>
                            ( นายธีรพงศ์ เพ็งชัย )<br>
                            รองผู้อำนวยการกลุ่มบริหารวิชาการ
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="border: 1.5px solid #000; padding: 10px; vertical-align: top;">
                        <strong>ความคิดเห็นของผู้อำนวยการโรงเรียน</strong>
                        <div style="margin-top: 8px; border-bottom: 1px dotted #000; height: 20px;"></div>
                        <div style="margin-top: 8px; border-bottom: 1px dotted #000; height: 20px;"></div>
                        <div style="margin-top: 15px; text-align: center; line-height: 1.3; margin-bottom: 5px;">
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
                    <td>ชั้น ม.<?php echo htmlspecialchars($booking['classroom']); ?> (ห้องสอน: <?php echo htmlspecialchars($booking['room_number']); ?>)</td>
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
                    <td class="bg-light fw-bold">ผู้นิเทศ (Head/Deputy)</td>
                    <td><?php echo htmlspecialchars($booking['head_name']); ?></td>
                </tr>
                <tr>
                    <td class="bg-light fw-bold">คณะกรรมการวิชาการ</td>
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
                    $sc = $ev["doc_score_$i"];
                    if ($sc !== null && $sc !== '') {
                        $all_doc_scores[] = (int)$sc;
                    }
                }
                for ($i=1; $i<=31; $i++) {
                    $sc = $ev["class_score_$i"];
                    if ($sc !== null && $sc !== '') {
                        $all_class_scores[] = (int)$sc;
                    }
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

        <?php
        $p_doc_avg = 0; $h_doc_avg = 0; $a_doc_avg = 0;
        $p_class_avg = 0; $h_class_avg = 0; $a_class_avg = 0;

        if ($peer_eval) {
            $p_doc_scores = [];
            for($i=1;$i<=5;$i++) { 
                $sc = $peer_eval["doc_score_$i"];
                if($sc !== null && $sc !== '') $p_doc_scores[] = (int)$sc; 
            }
            $p_doc_avg = count($p_doc_scores) > 0 ? array_sum($p_doc_scores) / count($p_doc_scores) : 0;
            
            $p_class_scores = [];
            for($i=1;$i<=31;$i++) { 
                $sc = $peer_eval["class_score_$i"];
                if($sc !== null && $sc !== '') $p_class_scores[] = (int)$sc; 
            }
            $p_class_avg = count($p_class_scores) > 0 ? array_sum($p_class_scores) / count($p_class_scores) : 0;
        }
        if ($head_eval) {
            $h_doc_scores = [];
            for($i=1;$i<=5;$i++) { 
                $sc = $head_eval["doc_score_$i"];
                if($sc !== null && $sc !== '') $h_doc_scores[] = (int)$sc; 
            }
            $h_doc_avg = count($h_doc_scores) > 0 ? array_sum($h_doc_scores) / count($h_doc_scores) : 0;
            
            $h_class_scores = [];
            for($i=1;$i<=31;$i++) { 
                $sc = $head_eval["class_score_$i"];
                if($sc !== null && $sc !== '') $h_class_scores[] = (int)$sc; 
            }
            $h_class_avg = count($h_class_scores) > 0 ? array_sum($h_class_scores) / count($h_class_scores) : 0;
        }
        if ($academic_eval) {
            $a_doc_scores = [];
            for($i=1;$i<=5;$i++) { 
                $sc = $academic_eval["doc_score_$i"];
                if($sc !== null && $sc !== '') $a_doc_scores[] = (int)$sc; 
            }
            $a_doc_avg = count($a_doc_scores) > 0 ? array_sum($a_doc_scores) / count($a_doc_scores) : 0;
            
            $a_class_scores = [];
            for($i=1;$i<=31;$i++) { 
                $sc = $academic_eval["class_score_$i"];
                if($sc !== null && $sc !== '') $a_class_scores[] = (int)$sc; 
            }
            $a_class_avg = count($a_class_scores) > 0 ? array_sum($a_class_scores) / count($a_class_scores) : 0;
        }
        ?>

        <div style="border: 1px solid #94a3b8; border-radius: 8px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; border-style: hidden; text-align: center; vertical-align: middle; font-family: 'TH Sarabun PSK', 'TH Sarabun New', sans-serif;">
                <tbody>
                    <tr>
                        <td rowspan="3" style="border: 1px solid #94a3b8; width: 35%; vertical-align: middle; background-color: #f8f9fa;">
                            <div style="font-size: 16pt; font-weight: bold;">ผู้รับการประเมิน</div>
                        </td>
                        <td colspan="4" style="border: 1px solid #94a3b8; background-color: #f8f9fa; font-size: 16pt; padding: 4px;">รวมผลการประเมิน</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="border: 1px solid #94a3b8; background-color: #f8f9fa; font-size: 16pt; padding: 4px;">แผนหน่วยการเรียนรู้และ<br>แผนการจัดการเรียนรู้</td>
                        <td colspan="2" style="border: 1px solid #94a3b8; background-color: #f8f9fa; font-size: 16pt; padding: 4px;">ผลการนิเทศการจัดการเรียนรู้</td>
                    </tr>
                <tr style="font-size: 16pt;">
                    <td style="border: 1px solid #94a3b8; padding: 4px; width: 16.25%;">ค่าเฉลี่ย</td>
                    <td style="border: 1px solid #94a3b8; padding: 4px; width: 16.25%;">ระดับคุณภาพ</td>
                    <td style="border: 1px solid #94a3b8; padding: 4px; width: 16.25%;">ค่าเฉลี่ย</td>
                    <td style="border: 1px solid #94a3b8; padding: 4px; width: 16.25%;">ระดับคุณภาพ</td>
                </tr>
                <tr style="font-size: 16pt;">
                    <td style="border: 1px solid #94a3b8; padding: 8px 12px; text-align: left;">ครูผู้ร่วมนิเทศ</td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $p_doc_avg > 0 ? number_format($p_doc_avg, 3) : '-'; ?></td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $p_doc_avg > 0 ? get_quality_text_php($p_doc_avg) : '-'; ?></td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $p_class_avg > 0 ? number_format($p_class_avg, 3) : '-'; ?></td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $p_class_avg > 0 ? get_quality_text_php($p_class_avg) : '-'; ?></td>
                </tr>
                <tr style="font-size: 16pt;">
                    <td style="border: 1px solid #94a3b8; padding: 8px 12px; text-align: left;">ผู้นิเทศ</td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $h_doc_avg > 0 ? number_format($h_doc_avg, 3) : '-'; ?></td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $h_doc_avg > 0 ? get_quality_text_php($h_doc_avg) : '-'; ?></td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $h_class_avg > 0 ? number_format($h_class_avg, 3) : '-'; ?></td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $h_class_avg > 0 ? get_quality_text_php($h_class_avg) : '-'; ?></td>
                </tr>
                <tr style="font-size: 16pt;">
                    <td style="border: 1px solid #94a3b8; padding: 8px 12px; text-align: left;">คณะกรรมการนิเทศ</td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $a_doc_avg > 0 ? number_format($a_doc_avg, 3) : '-'; ?></td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $a_doc_avg > 0 ? get_quality_text_php($a_doc_avg) : '-'; ?></td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $a_class_avg > 0 ? number_format($a_class_avg, 3) : '-'; ?></td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $a_class_avg > 0 ? get_quality_text_php($a_class_avg) : '-'; ?></td>
                </tr>
                <tr style="font-size: 16pt;">
                    <td style="border: 1px solid #94a3b8; padding: 8px 15px; text-align: right;">รวมผลและระดับคุณภาพ</td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $avg_doc > 0 ? number_format($avg_doc, 3) : '-'; ?></td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $avg_doc > 0 ? get_quality_text_php($avg_doc) : '-'; ?></td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $avg_class > 0 ? number_format($avg_class, 3) : '-'; ?></td>
                    <td style="border: 1px solid #94a3b8; color: #2563eb;"><?php echo $avg_class > 0 ? get_quality_text_php($avg_class) : '-'; ?></td>
                </tr>
            </tbody>
        </table>
        </div>
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
                    $p_sc = ($peer_eval && $peer_eval["doc_score_" . ($index + 1)] !== null) ? (int)$peer_eval["doc_score_" . ($index + 1)] : null;
                    $h_sc = ($head_eval && $head_eval["doc_score_" . ($index + 1)] !== null) ? (int)$head_eval["doc_score_" . ($index + 1)] : null;
                    $a_sc = ($academic_eval && $academic_eval["doc_score_" . ($index + 1)] !== null) ? (int)$academic_eval["doc_score_" . ($index + 1)] : null;
                    
                    $valid_sc = array_filter([$p_sc, $h_sc, $a_sc], function($v) { return $v !== null && $v !== ''; });
                    $avg = count($valid_sc) > 0 ? array_sum($valid_sc) / count($valid_sc) : 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item); ?></td>
                    <td class="text-center"><?php echo $p_sc !== null ? $p_sc : '-'; ?></td>
                    <td class="text-center"><?php echo $h_sc !== null ? $h_sc : '-'; ?></td>
                    <td class="text-center"><?php echo $a_sc !== null ? $a_sc : '-'; ?></td>
                    <td class="text-center fw-bold text-navy"><?php echo count($valid_sc) > 0 ? number_format($avg, 2) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-4 p-3 bg-light rounded-3" style="font-size: 16pt; line-height: 1.25;">
            <strong>ข้อเสนอแนะเกี่ยวกับแผนการจัดการเรียนรู้:</strong>
            <ul class="mt-2 mb-0">
                <?php if ($peer_eval && !empty($peer_eval['doc_comments'])): 
                    $parsed = parse_evaluator_role($peer_eval['doc_comments']);
                ?>
                    <li><strong>ครูผู้ร่วมนิเทศ:</strong> <?php echo htmlspecialchars($parsed['comments']); ?></li>
                <?php endif; ?>
                <?php if ($head_eval && !empty($head_eval['doc_comments'])): 
                    $parsed = parse_evaluator_role($head_eval['doc_comments']);
                ?>
                    <li><strong>ผู้นิเทศ (หัวหน้า/รอง):</strong> <?php echo htmlspecialchars($parsed['comments']); ?></li>
                <?php endif; ?>
                <?php if ($academic_eval && !empty($academic_eval['doc_comments'])): 
                    $parsed = parse_evaluator_role($academic_eval['doc_comments']);
                ?>
                    <li><strong>คณะกรรมการวิชาการ:</strong> <?php echo htmlspecialchars($parsed['comments']); ?></li>
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
                    $p_sc = ($peer_eval && $peer_eval["class_score_" . ($index + 1)] !== null) ? (int)$peer_eval["class_score_" . ($index + 1)] : null;
                    $h_sc = ($head_eval && $head_eval["class_score_" . ($index + 1)] !== null) ? (int)$head_eval["class_score_" . ($index + 1)] : null;
                    $a_sc = ($academic_eval && $academic_eval["class_score_" . ($index + 1)] !== null) ? (int)$academic_eval["class_score_" . ($index + 1)] : null;
                    
                    $valid_sc = array_filter([$p_sc, $h_sc, $a_sc], function($v) { return $v !== null && $v !== ''; });
                    $avg = count($valid_sc) > 0 ? array_sum($valid_sc) / count($valid_sc) : 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item); ?></td>
                    <td class="text-center"><?php echo $p_sc !== null ? $p_sc : '-'; ?></td>
                    <td class="text-center"><?php echo $h_sc !== null ? $h_sc : '-'; ?></td>
                    <td class="text-center"><?php echo $a_sc !== null ? $a_sc : '-'; ?></td>
                    <td class="text-center fw-bold text-navy"><?php echo count($valid_sc) > 0 ? number_format($avg, 2) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-4 p-3 bg-light rounded-3" style="font-size: 16pt; line-height: 1.25;">
            <strong>ข้อเสนอแนะสำหรับการจัดกิจกรรมการเรียนรู้ในชั้นเรียน:</strong>
            <ul class="mt-2 mb-0">
                <?php if ($peer_eval && !empty($peer_eval['class_comments'])): 
                    $parsed = parse_evaluator_role($peer_eval['class_comments']);
                    if (!empty($parsed['comments'])):
                ?>
                    <li><strong>ครูผู้ร่วมนิเทศ:</strong> <?php echo htmlspecialchars($parsed['comments']); ?></li>
                <?php endif; endif; ?>
                <?php if ($head_eval && !empty($head_eval['class_comments'])): 
                    $parsed = parse_evaluator_role($head_eval['class_comments']);
                    if (!empty($parsed['comments'])):
                ?>
                    <li><strong>ผู้นิเทศ (หัวหน้า/รอง):</strong> <?php echo htmlspecialchars($parsed['comments']); ?></li>
                <?php endif; endif; ?>
                <?php if ($academic_eval && !empty($academic_eval['class_comments'])): 
                    $parsed = parse_evaluator_role($academic_eval['class_comments']);
                    if (!empty($parsed['comments'])):
                ?>
                    <li><strong>คณะกรรมการวิชาการ:</strong> <?php echo htmlspecialchars($parsed['comments']); ?></li>
                <?php endif; endif; ?>
                <?php 
                $has_any_comment = false;
                if ($peer_eval) { $parsed = parse_evaluator_role($peer_eval['class_comments']); if (!empty($parsed['comments'])) $has_any_comment = true; }
                if ($head_eval) { $parsed = parse_evaluator_role($head_eval['class_comments']); if (!empty($parsed['comments'])) $has_any_comment = true; }
                if ($academic_eval) { $parsed = parse_evaluator_role($academic_eval['class_comments']); if (!empty($parsed['comments'])) $has_any_comment = true; }
                if (!$has_any_comment):
                ?>
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
                <?php 
                $peer_title = 'ครูผู้ร่วมนิเทศ (Peer)';
                if ($peer_eval) {
                    $parsed = parse_evaluator_role($peer_eval['class_comments']);
                    if (empty($parsed['title'])) {
                        $parsed = parse_evaluator_role($peer_eval['doc_comments']);
                    }
                    if (!empty($parsed['title'])) {
                        $peer_title = $parsed['title'];
                    }
                }
                ?>
                <strong><?php echo htmlspecialchars($peer_title); ?></strong>
            </div>
            <div class="col-6" style="position: relative;">
                <?php if (!empty($booking['head_signature'])): ?>
                    <div style="position: absolute; left: 50%; transform: translateX(-50%); top: -40px; height: 55px; pointer-events: none;">
                        <img src="<?php echo $booking['head_signature']; ?>" style="height: 55px; width: auto; max-width: 200px; mix-blend-mode: multiply;">
                    </div>
                <?php endif; ?>
                ลงชื่อ.......................................................<br>
                ( <?php echo htmlspecialchars($booking['head_name']); ?> )<br>
                <?php 
                $head_title = 'ผู้นิเทศ (หัวหน้า/รองกลุ่มสาระฯ)';
                if ($head_eval) {
                    $parsed = parse_evaluator_role($head_eval['class_comments']);
                    if (empty($parsed['title'])) {
                        $parsed = parse_evaluator_role($head_eval['doc_comments']);
                    }
                    if (!empty($parsed['title'])) {
                        $head_title = $parsed['title'];
                    }
                }
                ?>
                <strong><?php echo htmlspecialchars($head_title); ?></strong>
            </div>
            <div class="col-6" style="position: relative;">
                <?php if (!empty($booking['academic_signature'])): ?>
                    <div style="position: absolute; left: 50%; transform: translateX(-50%); top: -40px; height: 55px; pointer-events: none;">
                        <img src="<?php echo $booking['academic_signature']; ?>" style="height: 55px; width: auto; max-width: 200px; mix-blend-mode: multiply;">
                    </div>
                <?php endif; ?>
                ลงชื่อ.......................................................<br>
                ( <?php echo htmlspecialchars($booking['academic_name'] ?? '.......................................................'); ?> )<br>
                <?php 
                $acad_title = 'คณะกรรมการวิชาการ';
                if ($academic_eval) {
                    $parsed = parse_evaluator_role($academic_eval['class_comments']);
                    if (empty($parsed['title'])) {
                        $parsed = parse_evaluator_role($academic_eval['doc_comments']);
                    }
                    if (!empty($parsed['title'])) {
                        $acad_title = $parsed['title'];
                    }
                }
                ?>
                <strong><?php echo htmlspecialchars($acad_title); ?></strong>
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
