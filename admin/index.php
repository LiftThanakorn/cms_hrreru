<?php
session_start();
require_once '../includes/config.php';

// ตรวจสอบสิทธิ์ผู้ใช้
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// ดึงสถิติ
try {
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $postCount = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    $categoryCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $mediaCount = $pdo->query("SELECT COUNT(*) FROM media")->fetchColumn();

    // ดึงกิจกรรมล่าสุด
    $recentLogs = $pdo->query("SELECT al.*, u.username 
        FROM activity_logs al 
        JOIN users u ON al.user_id = u.user_id 
        ORDER BY al.created_at DESC 
        LIMIT 10")->fetchAll();
} catch(PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">แดชบอร์ดผู้ดูแลระบบ</h1>
            </div>

            <div class="row">
                <!-- Users Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        ผู้ใช้ทั้งหมด</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $userCount; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Posts Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        โพสต์ทั้งหมด</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $postCount; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categories Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        หมวดหมู่ทั้งหมด</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $categoryCount; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-tags fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Media Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        ไฟล์สื่อทั้งหมด</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $mediaCount; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-upload fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Section -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">กิจกรรมล่าสุด</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ผู้ใช้</th>
                                    <th>การกระทำ</th>
                                    <th>เป้าหมาย</th>
                                    <th>เวลา</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recentLogs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['username']); ?></td>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><?php echo htmlspecialchars($log['target_type'] . ' ' . $log['target_id']); ?></td>
                                    <td><?php echo $log['created_at']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</div>