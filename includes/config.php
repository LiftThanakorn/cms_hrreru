<?php
// ไฟล์ config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cms_hrreru');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}

// ฟังก์ชันบันทึกกิจกรรม
/* function logActivity($userId, $action, $targetType, $targetId) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, target_type, target_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $targetType, $targetId]);
} */



?>