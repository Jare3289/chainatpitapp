    <?php
    /**
     * api/teacher/get-timetable-by-day.php
     * Fetches the logged-in teacher's timetable for a given day of the week.
     */
    header('Content-Type: application/json');
    require_once '../../config.php';
    require_once '../../inc/security.php';
    session_start();

    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $day_of_week = isset($_GET['day']) ? (int)$_GET['day'] : 0;
    $booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

    if ($day_of_week < 1 || $day_of_week > 7) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid day of week']);
        exit;
    }

    try {
        if ($_SESSION['role'] === 'admin' && $booking_id > 0) {
            $stmt = $pdo->prepare("SELECT teacher_id FROM supervision_bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $teacher_internal_id = $stmt->fetchColumn();
            if (!$teacher_internal_id) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Booking not found']);
                exit;
            }
        } else {
            // Get logged-in teacher's internal id
            $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $teacher_internal_id = $stmt->fetchColumn();

            if (!$teacher_internal_id) {
                $stmt = $pdo->prepare("SELECT id FROM teachers WHERE teacher_id = ? OR email = ?");
                $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
                $teacher_internal_id = $stmt->fetchColumn();
            }

            if (!$teacher_internal_id) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Teacher profile not found']);
                exit;
            }
        }

        // Fetch timetable for this teacher and day (latest academic year)
        $stmt_tt = $pdo->prepare("
            SELECT
                t.id,
                t.period,
                COALESCE(NULLIF(t.subject_code, ''), MAX(sc.subject_code), MAX(sn.subject_code), t.subject_name) AS subject_code,
                COALESCE(MAX(sc.subject_name), MAX(sn.subject_name), t.subject_name)                  AS subject_name,
                t.class_name,
                t.room_location
            FROM timetable t
            LEFT JOIN subjects sc ON sc.subject_code = t.subject_name
            LEFT JOIN subjects sn ON sn.subject_name  = t.subject_name AND sc.id IS NULL
            WHERE t.teacher_id = ?
              AND t.day_of_week = ?
              AND (t.academic_year IS NULL OR t.academic_year = (
                      SELECT MAX(t2.academic_year) FROM timetable t2 WHERE t2.teacher_id = t.teacher_id
                  ))
              AND (t.semester IS NULL OR t.semester = 0 OR t.semester = (
                      SELECT COALESCE(MAX(CAST(s.setting_value AS UNSIGNED)), 1)
                      FROM system_settings s WHERE s.setting_key = 'current_semester'
                  ))
            GROUP BY t.id, t.period, t.class_name, t.room_location
            ORDER BY t.period ASC
        ");
        $stmt_tt->execute([$teacher_internal_id, $day_of_week]);
        $schedule = $stmt_tt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'day_of_week' => $day_of_week,
            'schedule' => $schedule
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
