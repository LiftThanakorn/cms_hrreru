<?php
session_start();
require_once '../includes/config.php';

// ตรวจสอบสิทธิ์ผู้ใช้
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "คุณไม่มีสิทธิ์เข้าถึง";
    header('Location: ../auth/login.php');
    exit();
}

// การลบไฟล์สื่อ
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $media_id = intval($_GET['delete']);
    
    try {
        // เริ่ม Transaction
        $pdo->beginTransaction();
        
        // ดึงข้อมูลไฟล์
        $stmt = $pdo->prepare("SELECT filename FROM media WHERE media_id = ?");
        $stmt->execute([$media_id]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($media) {
            // ลบความสัมพันธ์กับโพสต์
            $delete_post_media = $pdo->prepare("DELETE FROM post_media WHERE media_id = ?");
            $delete_post_media->execute([$media_id]);
            
            // ลบข้อมูลจากตาราง media
            $delete_media = $pdo->prepare("DELETE FROM media WHERE media_id = ?");
            $delete_media->execute([$media_id]);
            
            // ลบไฟล์จากระบบ
            $file_path = '../uploads/' . $media['filename'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = "ลบไฟล์สื่อสำเร็จ";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการลบไฟล์: " . $e->getMessage();
    }
    
    header('Location: media.php');
    exit();
}

// ดึงรายการไฟล์สื่อ
try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               u.username,
               (SELECT COUNT(*) FROM post_media pm WHERE pm.media_id = m.media_id) as post_count,
               (SELECT COUNT(*) FROM post_media pm WHERE pm.media_id = m.media_id AND pm.is_featured = TRUE) as featured_count
        FROM media m
        LEFT JOIN users u ON m.user_id = u.user_id
        ORDER BY m.created_at DESC
    ");
    $stmt->execute();
    $media_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <h1 class="h3 mb-2 text-gray-800">จัดการสื่อ</h1>

    <?php
    // แสดงข้อความแจ้งเตือน
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <link rel="stylesheet" href="../assets/vendor/datatables/dataTables.bootstrap4.min.css">

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <a href="upload.php" class="btn btn-primary">อัปโหลดสื่อใหม่</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="mediaTable" class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>รหัส</th>
                            <th>ชื่อไฟล์</th>
                            <th>ตัวอย่าง</th>
                            <th>ประเภท</th>
                            <th>ขนาด</th>
                            <th>ผู้อัปโหลด</th>
                            <th>โพสต์ที่ใช้</th>
                            <th>ภาพปก</th>
                            <th>วันที่อัปโหลด</th>
                            <th>การกระทำ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($media_files as $media): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($media['media_id']); ?></td>
                            <td><?php echo htmlspecialchars($media['filename']); ?></td>
                            <td>
                                <?php 
                                $file_path = '../uploads/images/' . $media['filename'];
                                $is_image = in_array(strtolower(pathinfo($file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                
                                if ($is_image): ?>
                                    <img src="<?php echo htmlspecialchars($file_path); ?>" 
                                         alt="ตัวอย่างไฟล์" 
                                         style="max-width: 100px; max-height: 100px; object-fit: cover;">
                                <?php else: ?>
                                    <span class="badge badge-secondary">
                                        <?php echo strtoupper(pathinfo($file_path, PATHINFO_EXTENSION)); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($media['file_type']); ?></td>
                            <td><?php echo number_format($media['file_size'] / 1024, 2); ?> KB</td>
                            <td><?php echo htmlspecialchars($media['username']); ?></td>
                            <td><?php echo $media['post_count']; ?></td>
                            <td><?php echo $media['featured_count']; ?></td>
                            <td><?php echo htmlspecialchars($media['created_at']); ?></td>
                            <td>
                                <a href="media-details.php?id=<?php echo $media['media_id']; ?>" 
                                   class="btn btn-sm btn-info">รายละเอียด</a>
                                <a href="media.php?delete=<?php echo $media['media_id']; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('ยืนยันการลบไฟล์สื่อนี้?')">ลบ</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- DataTables JavaScript -->
<script src="../assets/vendor/jquery/jquery.min.js"></script>
<script src="../assets/vendor/datatables/jquery.dataTables.min.js"></script>
<script src="../assets/vendor/datatables/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $('#mediaTable').DataTable({
        "language": {
            "sProcessing":   "กำลังดำเนินการ...",
            "sLengthMenu":   "แสดง _MENU_ รายการ",
            "sZeroRecords":  "ไม่พบข้อมูล",
            "sInfo":         "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
            "sInfoEmpty":    "แสดง 0 ถึง 0 จาก 0 รายการ",
            "sInfoFiltered": "(กรองข้อมูล _MAX_ ทุกรายการ)",
            "sInfoPostFix":  "",
            "sSearch":       "ค้นหา:",
            "sUrl":          "",
            "oPaginate": {
                "sFirst":    "หน้าแรก",
                "sPrevious": "ก่อนหน้า",
                "sNext":     "ถัดไป",
                "sLast":     "หน้าสุดท้าย"
            }
        },
        "pageLength": 10,
        "order": [[8, "desc"]] // เรียงลำดับตามวันที่อัปโหลดจากใหม่ไปเก่า
    });
});
</script>

<?php include 'includes/footer.php'; ?>