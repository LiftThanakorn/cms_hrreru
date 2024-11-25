<?php
session_start();
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $errors = [];

    // Validate input
    if (empty($username)) $errors[] = "กรุณาป้อนชื่อผู้ใช้";
    if (empty($email)) $errors[] = "กรุณาป้อนอีเมล";
    if (empty($password)) $errors[] = "กรุณาป้อนรหัสผ่าน";
    if ($password !== $confirm_password) $errors[] = "รหัสผ่านไม่ตรงกัน";

    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->rowCount() > 0) $errors[] = "ชื่อผู้ใช้หรืออีเมลนี้มีอยู่แล้ว";

    if (empty($errors)) {
        // Hash password with Argon2
        $hashed_password = password_hash($password, PASSWORD_ARGON2ID);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashed_password, 'user']);

            $_SESSION['success_message'] = "สมัครสมาชิกสำเร็จ";
            header("Location: login.php");
            exit();
        } catch(PDOException $e) {
            $errors[] = "การสมัครสมาชิกล้มเหลว: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>สมัครสมาชิก</title>
    <link rel="stylesheet" href="../assets/vendor/fontawesome-free/css/all.min.css">
    <link href="../assets/css/sb-admin-2.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-primary">
    <div class="container">
        <div class="card o-hidden border-0 shadow-lg my-5">
            <div class="card-body p-0">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="p-5">
                            <div class="text-center">
                                <h1 class="h4 text-gray-900 mb-4">สร้างบัญชีผู้ใช้</h1>
                            </div>
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($errors as $error): ?>
                                        <p><?= $error ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <form class="user" method="post">
                                <div class="form-group">
                                    <input type="text" class="form-control form-control-user" 
                                           name="username" placeholder="ชื่อผู้ใช้">
                                </div>
                                <div class="form-group">
                                    <input type="email" class="form-control form-control-user" 
                                           name="email" placeholder="อีเมล">
                                </div>
                                <div class="form-group row">
                                    <div class="col-sm-6 mb-3 mb-sm-0">
                                        <input type="password" class="form-control form-control-user"
                                               name="password" placeholder="รหัสผ่าน">
                                    </div>
                                    <div class="col-sm-6">
                                        <input type="password" class="form-control form-control-user"
                                               name="confirm_password" placeholder="ยืนยันรหัสผ่าน">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-user btn-block">
                                    สมัครสมาชิก
                                </button>
                            </form>
                            <hr>
                            <div class="text-center">
                                <a class="small" href="login.php">มีบัญชีอยู่แล้ว? เข้าสู่ระบบ</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/vendor/jquery/jquery.min.js"></script>
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/sb-admin-2.min.js"></script>
</body>
</html>