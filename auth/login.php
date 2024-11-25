<?php
session_start();
require_once '../includes/config.php';

// ตรวจสอบว่า login อยู่แล้วหรือไม่
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: ../admin/index.php");
    } else {
        header("Location: ../user/dashboard.php");
    }
    exit();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username)) $errors[] = "กรุณาป้อนชื่อผู้ใช้";
    if (empty($password)) $errors[] = "กรุณาป้อนรหัสผ่าน";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] == 'admin') {
                    header("Location: ../admin/index.php");
                } else {
                    header("Location: ../user/dashboard.php");
                }
                exit();
            } else {
                $errors[] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            }
        } catch (PDOException $e) {
            $errors[] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ | CMS ของคุณ</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome-free/css/all.min.css">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <link href="../assets/css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #2c67e7 0%, #2c67e7 100%);
        }

        .login-container {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-radius: 15px;
            overflow: hidden;
        }

        .login-form {
            padding: 2rem;
        }

        .btn-login {
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 17px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body>
    <div class="container login-container">
        <div class="col-xl-10 col-lg-12 col-md-9">
            <div class="card login-card border-0 shadow-lg">
                <div class="row no-gutters">
                    <div class="col-lg-6 d-none d-lg-block"
                        style="background: url('../assets/img/login.png') no-repeat center center; background-size: cover;">
                    </div>

                    <div class="col-lg-6">
                        <div class="login-form">
                            <div class="text-center mb-4">
                                <h1 class="h3 text-gray-900 mb-4">
                                    <i class="fas fa-user-lock mr-2"></i>เข้าสู่ระบบ
                                </h1>
                            </div>

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php foreach ($errors as $error): ?>
                                        <p class="mb-0"><i class="fas fa-exclamation-triangle mr-2"></i><?= $error ?></p>
                                    <?php endforeach; ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                            <form class="user" method="post">
                                <div class="form-group">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-user"></i></span>
                                        </div>
                                        <input type="text" class="form-control form-control-user border-left-0"
                                            name="username" placeholder="ชื่อผู้ใช้" required
                                            pattern="^[a-zA-Z0-9_]+$" title="ชื่อผู้ใช้ต้องเป็นอักษร, ตัวเลข หรือขีดล่าง">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-lock"></i></span>
                                        </div>
                                        <input type="password" class="form-control form-control-user border-left-0"
                                            name="password" placeholder="รหัสผ่าน" required
                                            minlength="6" maxlength="20"
                                            pattern="^[a-zA-Z0-9@#$%^&+=]+$" title="รหัสผ่านต้องประกอบด้วยอักษร, ตัวเลข หรืออักขระพิเศษ">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-user btn-block btn-login">
                                    เข้าสู่ระบบ
                                </button>
                            </form>

                            <hr>
                            <div class="text-center">
                                <a class="small" href="register.php">
                                    <i class="fas fa-user-plus mr-2"></i>สร้างบัญชีใหม่!
                                </a>
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