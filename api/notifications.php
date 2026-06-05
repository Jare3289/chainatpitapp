<?php
// api/notifications.php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../inc/security.php';
require_once '../inc/classroom_codes.php';
session_start();
cnp_verify_origin();
cnp_csrf_verify();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($pdo, $user_id);
        break;
    case 'POST':
        handlePost($pdo, $user_id);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
}

function handleGet($pdo, $user_id) {
    $unreadOnly = ($_GET['unread'] ?? '') === '1';
    $role = $_SESSION['role'];
    
    try {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        if ($unreadOnly) $sql .= " AND is_read = 0";
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Dynamic System Alerts (Things that need doing) ---
        $actions = [];

        // ── Teacher Alerts ──
        if ($role === 'teacher') {
            // Fetch advisory room once — reused for both PS count and homeroom check
            $stmtRoom = $pdo->prepare("SELECT COALESCE(r.classroom_code, t.classroom) AS classroom FROM teachers t LEFT JOIN rooms r ON r.id = t.advisory_room_id WHERE t.user_id = ? LIMIT 1");
            $stmtRoom->execute([$user_id]);
            $room  = $stmtRoom->fetchColumn();
            $varsR = $room ? cnp_classroom_code_variants((string) $room) : [];

            // Pending PS approvals: assigned to this teacher OR unassigned records from their advisory room
            if ($varsR) {
                $phR     = implode(',', array_fill(0, count($varsR), '?'));
                $stmtPs  = $pdo->prepare("SELECT COUNT(*) FROM public_service_records r JOIN students s ON r.student_id = s.id WHERE r.status = 'pending' AND (r.approver_id = ? OR (r.approver_id IS NULL AND s.class_name IN ($phR)))");
                $stmtPs->execute(array_merge([$user_id], $varsR));
            } else {
                $stmtPs = $pdo->prepare("SELECT COUNT(*) FROM public_service_records WHERE status = 'pending' AND approver_id = ?");
                $stmtPs->execute([$user_id]);
            }
            $pendingCount = (int)$stmtPs->fetchColumn();
            if ($pendingCount > 0) {
                $actions[] = [
                    'id' => 'alert_ps_pending',
                    'type' => 'action',
                    'title' => 'คำขอสาธารณประโยชน์',
                    'message' => "มีนักเรียนรอให้คุณอนุมัติกิจกรรม $pendingCount รายการ",
                    'link' => 'teacher_public_service.html',
                    'icon' => 'bi bi-heart-pulse-fill',
                    'color' => '#e11d48',
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }

            // ยังไม่เช็คชื่อโฮมรูมวันนี้
            if ($room && $varsR) {
                $today  = date('Y-m-d');
                
                // ตรวจสอบวันหยุดเสาร์-อาทิตย์ และวันหยุดพิเศษ
                $dayOfWeek = (int)date('N');
                $isWeekend = ($dayOfWeek === 6 || $dayOfWeek === 7);
                $stmtHoliday = $pdo->prepare("SELECT COUNT(*) FROM academic_days WHERE date_val = ? AND day_type = 'วันหยุด'");
                $stmtHoliday->execute([$today]);
                $isHoliday = ((int)$stmtHoliday->fetchColumn() > 0);

                // ข้ามการแจ้งเตือนถ้าเป็นวันหยุด
                if (!$isWeekend && !$isHoliday) {
                    $phR2   = implode(',', array_fill(0, count($varsR), '?'));
                    $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND class_name IN ($phR2) AND type = 'daily'");
                    $stmtChk->execute(array_merge([$today], $varsR));
                    $todayChecked = (int) $stmtChk->fetchColumn();

                    $hour = (int) date('G');
                    if ($todayChecked === 0 && $hour >= 8 && $hour < 18) {
                        $actions[] = [
                            'id' => 'alert_not_checked',
                            'type' => 'action',
                            'title' => 'ยังไม่ได้เช็คชื่อวันนี้',
                            'message' => "ห้อง {$room} ยังไม่มีการเช็คชื่อวันนี้ — กดเพื่อเช็คชื่อ",
                            'link' => 'attendance_daily.html?class_name=' . urlencode($room),
                            'icon' => 'bi-clipboard-x-fill',
                            'color' => '#ef4444',
                            'is_read' => 0,
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                    }
                }
            }
        }

        // ── Admin Alerts ──
        if ($role === 'admin') {
            try {
                $stmtPr = $pdo->prepare("SELECT COUNT(*) FROM public_relations WHERE status = 'pending'");
                $stmtPr->execute([]);
                $pendingPrCount = (int)$stmtPr->fetchColumn();
                if ($pendingPrCount > 0) {
                    $actions[] = [
                        'id' => 'alert_pr_pending',
                        'type' => 'action',
                        'title' => 'รออนุมัติประชาสัมพันธ์',
                        'message' => "มีบทความรอการอนุมัติ $pendingPrCount รายการ",
                        'link' => 'public_relations.html',
                        'icon' => 'bi-megaphone-fill',
                        'color' => '#f59e0b',
                        'is_read' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
            } catch (Throwable $e) { /* table อาจยังไม่มี */ }
        }

        // Student: dynamic alerts (today's absence summary, PS status)
        if ($role === 'student') {
            $stmtSid = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            $stmtSid->execute([$user_id]);
            $sid = (int) ($stmtSid->fetchColumn() ?: 0);
            if ($sid > 0) {
                // วันนี้ถูกบันทึก ขาด/สาย?
                $today = date('Y-m-d');
                $stmtToday = $pdo->prepare("SELECT status FROM attendance WHERE student_id = ? AND date = ? AND type = 'daily' LIMIT 1");
                $stmtToday->execute([$sid, $today]);
                $todayStatus = $stmtToday->fetchColumn();
                if ($todayStatus === 'ขาด' || $todayStatus === 'สาย') {
                    $actions[] = [
                        'id' => 'alert_today_attendance',
                        'type' => 'action',
                        'title' => $todayStatus === 'ขาด' ? '⚠️ วันนี้ถูกบันทึก: ขาด' : '⏰ วันนี้ถูกบันทึก: สาย',
                        'message' => 'กดเพื่อดูประวัติการเช็คชื่อ',
                        'link' => 'student_attendance_history.html',
                        'icon' => 'bi-clipboard2-x-fill',
                        'color' => $todayStatus === 'ขาด' ? '#ef4444' : '#f59e0b',
                        'is_read' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }

                // คำขอ PS ที่ยังรอ
                $stmtPS = $pdo->prepare("SELECT COUNT(*) FROM public_service_records WHERE student_id = ? AND status = 'pending'");
                $stmtPS->execute([$sid]);
                $pendingMine = (int) $stmtPS->fetchColumn();
                if ($pendingMine > 0) {
                    $actions[] = [
                        'id' => 'alert_my_ps_pending',
                        'type' => 'action',
                        'title' => 'คำขอสาธารณประโยชน์รอตรวจ',
                        'message' => "มีคำขอที่รอครูรับรอง {$pendingMine} รายการ",
                        'link' => 'student_public_service.html',
                        'icon' => 'bi-hourglass-split',
                        'color' => '#a16207',
                        'is_read' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }

        // ── System Update Alert (แสดงทุก role, หมดอายุ 14 วันหลัง release) ────
        $releaseTs  = strtotime('2026-05-27');
        $expireTs   = $releaseTs + 14 * 86400;
        if (time() <= $expireTs) {
            array_unshift($actions, [
                'id'         => 'alert_system_update_20260527',
                'type'       => 'system_update',
                'title'      => '🆕 อัปเดตระบบ 27-28 พ.ค. 2569',
                'message'    => "• โปรไฟล์นักเรียน: ความสัมพันธ์แบบ rating scale, label BMI, ปุ่มบันทึกล่าง\n• สถิติรายสัปดาห์ใน Monthly Stats และ Attendance Report\n• Export PDF ทุกรายงาน\n• ลบนักเรียน: ลบข้อมูลที่เกี่ยวข้องทั้งหมดออกโดยอัตโนมัติ\n• สาธารณประโยชน์: แก้ไข/ลบรายการได้, Batch delete สำหรับครู\n• รายงานครูที่เซ็นอนุมัติสาธา\n• ประวัติคะแนน: ออกแบบใหม่ กรองได้",
                'link'       => '',
                'icon'       => 'bi bi-stars',
                'color'      => '#7c3aed',
                'is_read'    => 0,
                'created_at' => date('Y-m-d 08:00:00', $releaseTs),
            ]);
        }

        $releaseTs20260605  = strtotime('2026-06-05');
        $expireTs20260605   = $releaseTs20260605 + 14 * 86400;
        if (time() <= $expireTs20260605) {
            array_unshift($actions, [
                'id'         => 'alert_system_update_20260605',
                'type'       => 'system_update',
                'title'      => 'อัปเดตระบบ CNP APP — 5 มิถุนายน 2569',
                'message'    => "ผู้ดูแลระบบได้ทำการปรับปรุงและพัฒนาระบบ CNP APP ในช่วงวันที่ 5 มิถุนายน 2569 มีรายละเอียดดังนี้\n\n📍 การจัดเส้นทางและเยี่ยมบ้านนักเรียน\n• การคำนวณเส้นทางเยี่ยมบ้านโดยใช้ระยะทางจริงบนถนนจริง (Nearest Neighbor Algorithm)\n• การเลือกจุดเริ่มต้นเดินทางได้อย่างยืดหยุ่น (โรงเรียน / พิกัด GPS / ระบุเองบนแผนที่)\n• ระบบเช็คอินติดตามผลการเยี่ยมบ้านนักเรียน ( visited check-in ) และเปลี่ยนสีหมุดเป็นสีเขียว\n• สามารถกดเบอร์โทรศัพท์เพื่อโทรออกได้โดยตรง (Click-to-Call Link) จากป๊อปอัปและเมนูจัดเส้นทาง\n• เพิ่มแถบควบคุม Layer บนแผนที่เพื่อสลับดูมุมมองปกติ หรือภาพถ่ายดาวเทียม (Satellite View) ได้ตามต้องการ\n\n🔔 ระบบแจ้งเตือนและการนิเทศการสอน\n• อัปเดตตรรกะระบบแจ้งเตือนนิเทศการสอนให้สมบูรณ์ในทุกขั้นตอน (จอง / อนุมัติ / ส่งแผน / ประเมิน / สะท้อนคิด / ยกเลิก)\n• เพิ่มรายการอัปเดตระบบประจำวันทั่วไป (System Update alert) เมื่ออ่านแล้วสามารถปิดได้ทันที",
                'link'       => '',
                'icon'       => 'bi bi-stars',
                'color'      => '#7c3aed',
                'is_read'    => 0,
                'created_at' => date('Y-m-d 08:00:00', $releaseTs20260605),
            ]);
        }

        // Count total unread from DB
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $countStmt->execute([$user_id]);
        $unreadCount = $countStmt->fetchColumn();

        // Format time ago and combine
        $all = array_merge($actions, $notifications);
        foreach ($all as &$n) {
            $n['time_ago'] = formatTimeAgo($n['created_at']);
        }

        echo json_encode([
            'success' => true,
            'data' => $all,
            'unread_count' => (int)$unreadCount + count($actions)
        ]);
    } catch (PDOException $e) {
        error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
    }
}

function handlePost($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'mark_read') {
        $id = $data['id'] ?? null;
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
                $stmt->execute([$user_id]);
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['error' => 'ระบบขัดข้องชั่วคราว']);
        }
    }
}

function formatTimeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return 'เมื่อครู่นี้';
    if ($diff < 3600) return floor($diff / 60) . ' นาทีที่แล้ว';
    if ($diff < 86400) return floor($diff / 3600) . ' ชั่วโมงที่แล้ว';
    if ($diff < 604800) return floor($diff / 86400) . ' วันที่แล้ว';
    return date('j M y', $time);
}
?>
