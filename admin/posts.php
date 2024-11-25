<?php
session_start();
require_once '../includes/config.php';

// ตรวจสอบสิทธิ์ผู้ใช้
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "คุณไม่มีสิทธิ์เข้าถึง";
    header('Location: ../auth/login.php');
    exit();
}

// การจัดการการลบโพสต์
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $post_id = intval($_GET['delete']);
    
    try {
        $pdo->beginTransaction();
        
        // ลบความสัมพันธ์กับแท็ก
        $tag_stmt = $pdo->prepare("DELETE FROM post_tag WHERE post_id = ?");
        $tag_stmt->execute([$post_id]);
        
        // ลบบันทึกกิจกรรม
        $log_stmt = $pdo->prepare("DELETE FROM activity_logs WHERE target_type = 'post' AND target_id = ?");
        $log_stmt->execute([$post_id]);
        
        // ลบโพสต์
        $post_stmt = $pdo->prepare("DELETE FROM posts WHERE post_id = ?");
        $post_stmt->execute([$post_id]);
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "ลบโพสต์สำเร็จ";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการลบโพสต์: " . $e->getMessage();
    }
    
    header('Location: posts.php');
    exit();
}

// การดึงโพสต์
try {
    $stmt = $pdo->prepare("
        SELECT p.post_id, p.title, p.status, p.created_at, 
               c.name AS category_name, 
               u.username AS author,
               m.filename AS featured_image
        FROM posts p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN users u ON p.user_id = u.user_id
        LEFT JOIN media m ON p.featured_image_id = m.media_id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <h1 class="h3 mb-2 text-gray-800">จัดการโพสต์</h1>

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

    <!-- เพิ่ม DataTables CSS -->
    <link rel="stylesheet" href="../assets/vendor/datatables/dataTables.bootstrap4.min.css">

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <a href="addpost.php" class="btn btn-primary">เพิ่มโพสต์ใหม่</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
            <table id="postsTable" class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>รหัส</th>
                            <th>หัวข้อ</th>
                            <th>ภาพปก</th>
                            <th>หมวดหมู่</th>
                            <th>ผู้เขียน</th>
                            <th>สถานะ</th>
                            <th>วันที่สร้าง</th>
                            <th>การกระทำ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($post['post_id']); ?></td>
                            <td><?php echo htmlspecialchars($post['title']); ?></td>
                            <td>
                                <?php if ($post['featured_image']): ?>
                                    <img src="../uploads/images/<?php echo htmlspecialchars($post['featured_image']); ?>" 
                                         alt="ภาพปก" 
                                         style="max-width: 100px; max-height: 100px; object-fit: cover;">
                                <?php else: ?>
                                    <span class="badge badge-secondary">ไม่มีภาพปก</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($post['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($post['author']); ?></td>
                            <td>
                                <?php 
                                $status_class = $post['status'] == 'published' ? 'success' : 'secondary';
                                echo '<span class="badge badge-' . $status_class . '">' . 
                                     htmlspecialchars($post['status']) . '</span>'; 
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($post['created_at']); ?></td>
                            <td>
                                <a href="editpost.php?id=<?php echo $post['post_id']; ?>" class="btn btn-sm btn-warning">แก้ไข</a>
                                <a href="posts.php?delete=<?php echo $post['post_id']; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('ยืนยันการลบโพสต์นี้?')">ลบ</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- เพิ่ม DataTables JavaScript -->
<script src="../assets/vendor/jquery/jquery.min.js"></script>
<script src="../assets/vendor/datatables/jquery.dataTables.min.js"></script>
<script src="../assets/vendor/datatables/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $('#postsTable').DataTable({
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
        "order": [[5, "desc"]] // เรียงลำดับตามวันที่สร้างจากใหม่ไปเก่า
    });
});
</script>

<?php include 'includes/footer.php'; ?>