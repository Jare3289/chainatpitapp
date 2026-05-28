<?php
header('Content-Type: application/json');
require_once '../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// Determine author name for creating posts
function getAuthorName($pdo, $uid, $uRole) {
    if ($uRole === 'admin') return 'ผู้ดูแลระบบ';
    
    if ($uRole === 'teacher') {
        $stmt = $pdo->prepare("SELECT prefix, first_name_th, last_name_th FROM teachers WHERE user_id = ?");
        $stmt->execute([$uid]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($t) return trim(($t['prefix'] ?? '') . $t['first_name_th'] . ' ' . $t['last_name_th']);
    } elseif ($uRole === 'student') {
        $stmt = $pdo->prepare("SELECT prefix, first_name_th, last_name_th FROM students WHERE user_id = ?");
        $stmt->execute([$uid]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($s) return trim(($s['prefix'] ?? '') . $s['first_name_th'] . ' ' . $s['last_name_th']);
    }
    
    // Fallback: get name from users table if available
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    return $u['username'] ?? 'ผู้ใช้ทั่วไป';
}

$action = $_GET['action'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($action === 'list') {
            if ($role === 'admin') {
                // Admins see all posts sorted by status (pending first) then date
                $stmt = $pdo->query("SELECT * FROM public_relations ORDER BY FIELD(status, 'pending', 'approved', 'rejected') ASC, created_at DESC");
            } else {
                // Students and teachers see approved posts matching their role visibility, OR their own posts (even if pending/rejected)
                $stmt = $pdo->prepare("
                    SELECT * FROM public_relations 
                    WHERE (status = 'approved' AND (visibility = 'all' OR visibility = ?))
                       OR (author_id = ? AND author_role = ?)
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$role, $user_id, $role]);
            }
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'posts' => $posts]);
            exit;
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if ($action === 'create') {
            $title = trim($data['title'] ?? '');
            $content = trim($data['content'] ?? '');
            $category = trim($data['category'] ?? 'ทั่วไป');
            $visibility = trim($data['visibility'] ?? 'all');
            
            if (!in_array($visibility, ['all', 'teacher', 'student'])) {
                $visibility = 'all';
            }
            
            if (empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'error' => 'กรุณากรอกหัวข้อและเนื้อหาให้ครบถ้วน']);
                exit;
            }
            
            $authorName = getAuthorName($pdo, $user_id, $role);
            // Admins posts are automatically approved, others start as pending
            $status = ($role === 'admin') ? 'approved' : 'pending';
            $approved_at = ($role === 'admin') ? date('Y-m-d H:i:s') : null;
            $approved_by = ($role === 'admin') ? $user_id : null;
            
            $stmt = $pdo->prepare("
                INSERT INTO public_relations (title, content, category, visibility, author_id, author_role, author_name, status, approved_at, approved_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $success = $stmt->execute([
                $title, $content, $category, $visibility, $user_id, $role, $authorName, $status, $approved_at, $approved_by
            ]);
            
            if ($success) {
                $msg = ($role === 'admin') ? 'บันทึกและเผยแพร่ข่าวประชาสัมพันธ์เรียบร้อยแล้ว' : 'ส่งคำขอสร้างข่าวประชาสัมพันธ์แล้ว รอผู้ดูแลระบบอนุมัติ';
                echo json_encode(['success' => true, 'message' => $msg]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ไม่สามารถบันทึกข้อมูลได้']);
            }
            exit;
        }
        
        // Admin-only actions
        if ($role !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์ดำเนินการ']);
            exit;
        }
        
        if ($action === 'approve') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID ไม่ถูกต้อง']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE public_relations SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?");
            $success = $stmt->execute([$user_id, $id]);
            
            if ($success) {
                // Generate a notification to the author if applicable
                $chk = $pdo->prepare("SELECT author_id, author_role, title FROM public_relations WHERE id = ?");
                $chk->execute([$id]);
                $post = $chk->fetch(PDO::FETCH_ASSOC);
                if ($post && $post['author_role'] !== 'admin') {
                    // Try to insert notification if helper function is available or table exists
                    try {
                        $msg = "ข่าวประชาสัมพันธ์ของคุณในหัวข้อ \"" . mb_strimwidth($post['title'], 0, 30, "...") . "\" ได้รับการอนุมัติแล้ว";
                        $not = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
                        $not->execute([$post['author_id'], $msg]);
                    } catch (Exception $ex) { /* Ignore notification failure */ }
                }
                echo json_encode(['success' => true, 'message' => 'อนุมัติข่าวประชาสัมพันธ์เรียบร้อยแล้ว']);
            } else {
                echo json_encode(['success' => false, 'error' => 'ไม่สามารถอนุมัติได้']);
            }
            exit;
        }
        
        if ($action === 'reject') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID ไม่ถูกต้อง']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE public_relations SET status = 'rejected' WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'ปฏิเสธคำขอเรียบร้อยแล้ว']);
            } else {
                echo json_encode(['success' => false, 'error' => 'ไม่สามารถดำเนินการได้']);
            }
            exit;
        }
        
        if ($action === 'delete') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID ไม่ถูกต้อง']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM public_relations WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'ลบข่าวประชาสัมพันธ์เรียบร้อยแล้ว']);
            } else {
                echo json_encode(['success' => false, 'error' => 'ไม่สามารถลบข้อมูลได้']);
            }
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'error' => 'Action ไม่ถูกต้อง']);
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'ระบบขัดข้องชั่วคราว: ' . $e->getMessage()]);
}
