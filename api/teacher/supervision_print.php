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

    // 2. Fetch booking details
    $stmt = $pdo->prepare("SELECT b.*, 
        t.prefix as t_prefix, t.first_name_th as t_first, t.last_name_th as t_last, t.photo as t_photo, t.department as t_dept, t.academic_standing as t_standing, t.position as t_pos,
        (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.peer_teacher_id) as peer_name,
        (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.head_teacher_id) as head_name,
        (SELECT CONCAT(prefix, first_name_th, ' ', last_name_th) FROM teachers WHERE id = b.academic_teacher_id) as academic_name
        FROM supervision_bookings b
        JOIN teachers t ON b.teacher_id = t.id
        WHERE b.id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        die("ไม่พบข้อมูลการนิเทศรายการนี้");
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

    $teacher_name = trim(($booking['t_prefix'] ?? '') . $booking['t_first'] . ' ' . $booking['t_last']);

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
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f3f4f6;
            color: #111827;
            padding: 40px 0;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm 15mm;
            margin: 0 auto 30px;
            background: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            border-radius: 4px;
            position: relative;
            box-sizing: border-box;
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
            font-size: 2.2rem;
            line-height: 1.4;
            color: #1e3a8a;
        }
        .table-eval {
            font-size: 0.85rem;
        }
        .table-eval th {
            background-color: #f8fafc !important;
            font-weight: 700;
            text-align: center;
        }
        .score-col {
            width: 60px;
            text-align: center;
        }
        .sig-block {
            margin-top: 50px;
        }
        .sig-line {
            width: 200px;
            border-bottom: 1px dotted #000;
            display: inline-block;
        }
        
        /* Floating print button */
        .print-btn-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 999;
        }
        .btn-print {
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 800;
            box-shadow: 0 10px 25px rgba(30, 58, 138, 0.4);
            font-size: 1.1rem;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                color: #000;
            }
            .page {
                width: 210mm;
                min-height: 297mm;
                padding: 15mm;
                margin: 0;
                box-shadow: none;
                border: none;
            }
            .print-btn-container {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="print-btn-container">
        <button class="btn btn-primary btn-print" onclick="window.print()"><i class="bi bi-printer-fill me-2"></i> สั่งพิมพ์เอกสาร (Print)</button>
    </div>

    <!-- PAGE 1: COVER PAGE -->
    <div class="page text-center">
        <div class="d-flex justify-content-center mb-4" style="margin-top: 50px;">
            <img src="../../public/img/logo.png" alt="Logo" width="120" onerror="this.style.display='none'">
        </div>
        <h4 class="fw-bold text-navy mt-4">รายงานผลการนิเทศการจัดการเรียนรู้</h4>
        <h5 class="text-muted">งานนิเทศการศึกษา กลุ่มบริหารงานวิชาการ</h5>
        <h5 class="text-muted">โรงเรียนชัยนาทพิทยาคม</h5>
        
        <div class="cover-title">
            เล่มรายงานการนิเทศการสอน<br>
            ภาคเรียนที่ 1 ปีการศึกษา 2569
        </div>
        
        <div style="margin-top: 150px; font-size: 1.25rem; line-height: 2;">
            <strong>ผู้รับการนิเทศ:</strong> <?php echo htmlspecialchars($teacher_name); ?><br>
            <strong>ตำแหน่ง:</strong> <?php echo htmlspecialchars($booking['t_pos'] ?? 'ครู'); ?><br>
            <strong>วิทยฐานะ:</strong> <?php echo htmlspecialchars($booking['t_standing'] ?? 'ไม่มีวิทยฐานะ'); ?><br>
            <strong>กลุ่มสาระการเรียนรู้:</strong> <?php echo htmlspecialchars($booking['t_dept'] ?? '-'); ?><br>
        </div>
        
        <div style="margin-top: 150px; font-size: 0.95rem; color: #6b7280;">
            กลุ่มสาระการเรียนรู้<?php echo htmlspecialchars($booking['t_dept'] ?? '-'); ?><br>
            โรงเรียนชัยนาทพิทยาคม สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาอุทัยธานี ชัยนาท
        </div>
    </div>

    <!-- PAGE 2: INFORMATION & EVALUATION SUMMARY -->
    <div class="page page-break">
        <h5 class="fw-bold text-navy border-bottom pb-2 mb-4">ส่วนที่ 1: ข้อมูลประกอบการนิเทศการสอน</h5>
        
        <table class="table table-bordered align-middle mb-5">
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

        <h5 class="fw-bold text-navy border-bottom pb-2 mb-4">ส่วนที่ 2: สรุปผลคะแนนการประเมิน</h5>
        
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

        <div class="row g-4 text-center mt-2 mb-5">
            <div class="col-4">
                <div class="card p-3 border-0 bg-light rounded-3">
                    <div class="text-muted small fw-bold">คะแนนเฉลี่ยตรวจแผน (เอกสาร)</div>
                    <h3 class="fw-bold text-navy mt-2"><?php echo number_format($avg_doc, 2); ?> / 5</h3>
                    <div class="text-success small fw-bold"><?php echo number_format($percent_doc, 1); ?>%</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card p-3 border-0 bg-light rounded-3">
                    <div class="text-muted small fw-bold">คะแนนเฉลี่ยการสอน (ห้องเรียน)</div>
                    <h3 class="fw-bold text-navy mt-2"><?php echo number_format($avg_class, 2); ?> / 5</h3>
                    <div class="text-success small fw-bold"><?php echo number_format($percent_class, 1); ?>%</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card p-3 border-0 bg-primary bg-opacity-10 rounded-3">
                    <div class="text-primary small fw-bold">คะแนนรวมเฉลี่ยสะสม</div>
                    <h3 class="fw-bold text-primary mt-2"><?php echo number_format($overall_avg, 2); ?> / 5</h3>
                    <div class="text-primary small fw-bold"><?php echo number_format($overall_percent, 1); ?>%</div>
                </div>
            </div>
        </div>

        <table class="table table-bordered text-center align-middle">
            <thead class="table-light">
                <tr>
                    <th>องค์ประกอบการประเมิน</th>
                    <th>คะแนนเต็ม</th>
                    <th>ครูผู้ร่วมนิเทศ (Peer)</th>
                    <th>ครูผู้นิเทศ (Head)</th>
                    <th>กรรมการวิชาการ (Academic)</th>
                    <th>คะแนนเฉลี่ย</th>
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

    <!-- PAGE 3: DOCUMENT SCORE DETAIL -->
    <div class="page page-break">
        <h5 class="fw-bold text-navy border-bottom pb-2 mb-4">ส่วนที่ 3: คะแนนประเมินแผนการจัดการเรียนรู้ (ตรวจแผน)</h5>
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

        <div class="mt-4 p-3 bg-light rounded-3" style="font-size: 0.85rem;">
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

    <!-- PAGE 4: CLASSROOM TEACHING SCORE DETAIL -->
    <div class="page page-break">
        <h5 class="fw-bold text-navy border-bottom pb-2 mb-4">ส่วนที่ 4: คะแนนประเมินการจัดการเรียนรู้ในห้องเรียน</h5>
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

        <div class="mt-4 p-3 bg-light rounded-3" style="font-size: 0.85rem;">
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

    <!-- PAGE 5: POST TEACHING REFLECTIONS & SIGNATURES -->
    <div class="page page-break">
        <h5 class="fw-bold text-navy border-bottom pb-2 mb-4">ส่วนที่ 5: บันทึกผลหลังการจัดการเรียนรู้ของครูผู้รับนิเทศ</h5>
        
        <div class="p-4 border rounded-4 bg-light bg-opacity-50 mb-5" style="min-height: 250px; white-space: pre-wrap; font-size: 0.95rem; line-height: 1.8;">
            <?php echo !empty($booking['post_teaching_record']) ? htmlspecialchars($booking['post_teaching_record']) : 'ครูผู้รับนิเทศยังไม่ได้กรอกบันทึกหลังการสอน'; ?>
        </div>

        <h5 class="fw-bold text-navy border-bottom pb-2 mb-4">ส่วนที่ 6: ลงลายมือชื่อคณะกรรมการ</h5>
        
        <div class="row sig-block text-center" style="font-size: 0.9rem; line-height: 2;">
            <div class="col-6 mb-5">
                ลงชื่อ.......................................................<br>
                ( <?php echo htmlspecialchars($teacher_name); ?> )<br>
                <strong>ครูผู้รับนิเทศ</strong>
            </div>
            <div class="col-6 mb-5">
                ลงชื่อ.......................................................<br>
                ( <?php echo htmlspecialchars($booking['peer_name']); ?> )<br>
                <strong>ครูผู้ร่วมนิเทศ (Peer)</strong>
            </div>
            <div class="col-6">
                ลงชื่อ.......................................................<br>
                ( <?php echo htmlspecialchars($booking['head_name']); ?> )<br>
                <strong>ครูผู้นิเทศ (หัวหน้า/รองกลุ่มสาระฯ)</strong>
            </div>
            <div class="col-6">
                ลงชื่อ.......................................................<br>
                ( <?php echo htmlspecialchars($booking['academic_name'] ?? '.......................................................'); ?> )<br>
                <strong>ผู้แทนคณะกรรมการวิชาการ</strong>
            </div>
        </div>
    </div>

</body>
</html>
