<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../inc/security.php';

session_start();
cnp_verify_origin();
cnp_csrf_verify();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $sql = "SELECT r.*, t.prefix, t.first_name_th, t.last_name_th, t.photo 
                    FROM rooms r 
                    LEFT JOIN teachers t ON r.teacher_id = t.id 
                    ORDER BY r.classroom_code ASC";
            $stmt = $pdo->query($sql);
            $rooms = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $rooms]);
        } catch (PDOException $e) {
            error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'message' => 'ระบบขัดข้องชั่วคราว']);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
            exit;
        }

        $id = $data['id'] ?? null;

        try {
            if ($id) {
                // Update
                $sql = "UPDATE rooms SET
                        classroom_code = ?, class_level = ?, classroom_no = ?, 
                        location_code = ?, building = ?, floor = ?,
                        room_no = ?, grade_level = ?, house = ?, program = ?,
                        teacher_id = ?
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $data['classroom_code'] ?? '',
                    $data['class_level'] ?? '',
                    $data['classroom_no'] ?? '',
                    $data['location_code'] ?? '',
                    $data['building'] ?? '',
                    $data['floor'] ?? '',
                    $data['room_no'] ?? '',
                    $data['grade_level'] ?? '',
                    $data['house'] ?? '',
                    $data['program'] ?? '',
                    $data['teacher_id'] ?: null,
                    $id
                ]);
                echo json_encode(['success' => true, 'message' => 'แก้ไขข้อมูลสำเร็จ']);
            } else {
                // UPSERT
                $roomCode = $data['classroom_code'] ?? '';

                $check = $pdo->prepare("SELECT id FROM rooms WHERE classroom_code = ?");
                $check->execute([$roomCode]);
                $existing = $check->fetch();

                if ($existing) {
                    $sql = "UPDATE rooms SET
                            class_level = ?, classroom_no = ?,
                            location_code = ?, building = ?, floor = ?, room_no = ?,
                            grade_level = ?, house = ?, program = ?, teacher_id = ?
                            WHERE classroom_code = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $data['class_level'] ?? '',
                        $data['classroom_no'] ?? '',
                        $data['location_code'] ?? '',
                        $data['building'] ?? '',
                        $data['floor'] ?? '',
                        $data['room_no'] ?? '',
                        $data['grade_level'] ?? '',
                        $data['house'] ?? '',
                        $data['program'] ?? '',
                        $data['teacher_id'] ?: null,
                        $roomCode
                    ]);
                    echo json_encode(['success' => true, 'message' => "อัปเดตห้อง $roomCode เรียบร้อย"]);
                } else {
                    $sql = "INSERT INTO rooms (classroom_code, class_level, classroom_no, location_code, building, floor, room_no, grade_level, house, program, teacher_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $roomCode,
                        $data['class_level'] ?? '',
                        $data['classroom_no'] ?? '',
                        $data['location_code'] ?? '',
                        $data['building'] ?? '',
                        $data['floor'] ?? '',
                        $data['room_no'] ?? '',
                        $data['grade_level'] ?? '',
                        $data['house'] ?? '',
                        $data['program'] ?? '',
                        $data['teacher_id'] ?: null
                    ]);
                    echo json_encode(['success' => true, 'message' => "เพิ่มห้อง $roomCode เรียบร้อย"]);
                }
            }
        } catch (PDOException $e) {
            error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'message' => 'ระบบขัดข้องชั่วคราว']);
        }
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบ ID']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'ลบข้อมูลสำเร็จ']);
        } catch (PDOException $e) {
            error_log('[' . basename(__FILE__) . '] ' . $e->getMessage()); echo json_encode(['success' => false, 'message' => 'ระบบขัดข้องชั่วคราว']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
?>
