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

// One-time migration: add gallery_images column if missing
try {
    $pdo->exec("ALTER TABLE public_relations ADD COLUMN IF NOT EXISTS gallery_images TEXT NULL DEFAULT NULL");
} catch (PDOException $e) { /* already exists or unsupported — ignore */ }

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

// Handle gallery uploads — returns array of relative web paths
function handleGalleryUploads(): array {
    $paths    = [];
    $maxSize  = 5 * 1024 * 1024;
    $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $extMap   = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $uploadDir = __DIR__ . '/../public/uploads/pr_images/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    foreach ($_FILES as $key => $f) {
        if (!preg_match('/^gallery_\d+$/', $key)) continue;
        if ($f['error'] !== UPLOAD_ERR_OK)          continue;
        if ($f['size'] > $maxSize)                   continue;
        $mime = $finfo->file($f['tmp_name']);
        if (!in_array($mime, $allowed, true))         continue;
        $ext      = $extMap[$mime] ?? 'jpg';
        $filename = uniqid('prg_', true) . '.' . $ext;
        if (move_uploaded_file($f['tmp_name'], $uploadDir . $filename)) {
            $paths[] = 'public/uploads/pr_images/' . $filename;
        }
    }
    return $paths;
}

// Handle image upload — returns relative web path or null
function handleImageUpload(): ?string {
    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file    = $_FILES['image'];
    $maxSize = 5 * 1024 * 1024; // 5 MB

    if ($file['size'] > $maxSize) {
        throw new Exception('ไฟล์รูปภาพต้องมีขนาดไม่เกิน 5 MB');
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowedMimes, true)) {
        throw new Exception('รองรับเฉพาะไฟล์ภาพ JPG, PNG, GIF หรือ WEBP เท่านั้น');
    }

    $ext      = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        default      => 'jpg',
    };

    $uploadDir = __DIR__ . '/../public/uploads/pr_images/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid('pr_', true) . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception('ไม่สามารถบันทึกไฟล์ภาพได้');
    }

    return 'public/uploads/pr_images/' . $filename;
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
        // Check if request is multipart (file upload) or JSON
        $isMultipart = isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'multipart/form-data');

        if ($isMultipart) {
            $data = $_POST;
        } else {
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        }
        
        if ($action === 'create') {
            $title      = trim($data['title']    ?? '');
            $content    = trim($data['content']  ?? '');
            $category   = trim($data['category'] ?? 'ทั่วไป');
            $visibility = trim($data['visibility'] ?? 'all');
            
            if (!in_array($visibility, ['all', 'teacher', 'student'])) {
                $visibility = 'all';
            }
            
            if (empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'error' => 'กรุณากรอกหัวข้อและเนื้อหาให้ครบถ้วน']);
                exit;
            }

            // Handle optional image upload
            $imagePath   = null;
            $galleryJson = null;
            if ($isMultipart) {
                $imagePath    = handleImageUpload();
                $galleryPaths = handleGalleryUploads();
                if (!empty($galleryPaths)) {
                    $galleryJson = json_encode($galleryPaths);
                }
            }

            $authorName = getAuthorName($pdo, $user_id, $role);
            $status      = ($role === 'admin') ? 'approved' : 'pending';
            $approved_at = ($role === 'admin') ? date('Y-m-d H:i:s') : null;
            $approved_by = ($role === 'admin') ? $user_id : null;

            $stmt = $pdo->prepare("
                INSERT INTO public_relations (title, content, category, visibility, image_path, gallery_images, author_id, author_role, author_name, status, approved_at, approved_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $success = $stmt->execute([
                $title, $content, $category, $visibility, $imagePath, $galleryJson,
                $user_id, $role, $authorName, $status, $approved_at, $approved_by
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
                        require_once '../inc/notifications.php';
                        $msg = "ข่าวประชาสัมพันธ์ของคุณในหัวข้อ \"" . mb_strimwidth($post['title'], 0, 30, "...") . "\" ได้รับการอนุมัติแล้ว";
                        cnp_notify($pdo, (int)$post['author_id'], 'ข่าวประชาสัมพันธ์ได้รับการอนุมัติ 📢', $msg, 'public_relations.html', 'bi-megaphone-fill', '#10b981', 'public_relations');
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

            // Delete associated image file if exists
            $imgStmt = $pdo->prepare("SELECT image_path FROM public_relations WHERE id = ?");
            $imgStmt->execute([$id]);
            $imgRow = $imgStmt->fetch(PDO::FETCH_ASSOC);
            if ($imgRow && !empty($imgRow['image_path'])) {
                $filePath = __DIR__ . '/../' . $imgRow['image_path'];
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
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
