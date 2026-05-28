<?php
// config_deploy.php - ตั้งค่าฐานข้อมูลสำหรับ Server จริง (InfinityFree)
// กรุณาแทนที่ค่าเหล่านี้ด้วยข้อมูลจาก InfinityFree Hosting Account ของคุณ

$host = 'sqlXXX.infinityfree.com'; // ดูที่ MySQL Hostname ใน InfinityFree
$db   = 'if0_XXXXXX_cnpapp';       // ดูที่ MySQL Database Name (ที่สร้างใน Control Panel)
$user = 'if0_XXXXXX';              // ดูที่ MySQL Username
$pass = 'your_ftp_password';       // รหัสผ่านเดียวกับที่เข้า FTP/Control Panel
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Connection Failed: " . $e->getMessage());
}
?>
