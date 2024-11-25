<?php
session_start();
require_once '../includes/config.php';


// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Validation Functions
function validateUser($username, $email, $role, $password = null)
{
    $errors = [];

    // Username validation
    if (empty($username) || strlen($username) < 3) {
        $errors[] = "ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร";
    }

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "รูปแบบอีเมลไม่ถูกต้อง";
    }

    // Role validation
    if (!in_array($role, ['admin', 'user'])) {
        $errors[] = "สิทธิ์ไม่ถูกต้อง";
    }

    // Password validation (only for new users)
    if ($password !== null && strlen($password) < 6) {
        $errors[] = "รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร";
    }

    return $errors;
}

// User Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $username = $_POST['username'];
                    $email = $_POST['email'];
                    $password = $_POST['password'];
                    $role = $_POST['role'];

                    $errors = validateUser($username, $email, $role, $password);

                    // Check for existing user
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username, $email]);
                    if ($stmt->fetchColumn() > 0) {
                        $errors[] = "ชื่อผู้ใช้หรืออีเมลนี้มีอยู่แล้ว";
                    }

                    if (empty($errors)) {
                        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);
                        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$username, $email, $hashedPassword, $role]);
                        $_SESSION['message'] = "เพิ่มผู้ใช้สำเร็จ";
                    } else {
                        $_SESSION['errors'] = $errors;
                    }
                    break;

                case 'edit':
                    $user_id = $_POST['user_id'];
                    $username = $_POST['username'];
                    $email = $_POST['email'];
                    $role = $_POST['role'];

                    $errors = validateUser($username, $email, $role);

                    if (empty($errors)) {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE user_id = ?");
                        $stmt->execute([$username, $email, $role, $user_id]);
                        $_SESSION['message'] = "แก้ไขผู้ใช้สำเร็จ";
                    } else {
                        $_SESSION['errors'] = $errors;
                    }
                    break;

                case 'delete':
                    $user_id = $_POST['user_id'];
                    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $_SESSION['message'] = "ลบผู้ใช้สำเร็จ";
                    break;
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }

    header('Location: users.php');
    exit();
}

// Fetch Users
$stmt = $pdo->query("SELECT * FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user count
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$user_count = $stmt->fetchColumn();

// Edit User (for form display)
$user_data = null;
if (isset($_GET['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_GET['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<?php include 'includes/header.php'; ?>

<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">จัดการผู้ใช้</h1>
            </div>

            <!-- Error/Success Messages -->
            <?php if (isset($_SESSION['errors'])): ?>
                <div class="alert alert-danger">
                    <?php
                    foreach ($_SESSION['errors'] as $error) {
                        echo $error . "<br>";
                    }
                    unset($_SESSION['errors']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <?php
                    echo $_SESSION['message'];
                    unset($_SESSION['message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- User Count Report -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">รายงานจำนวนผู้ใช้</h6>
                        </div>
                        <div class="card-body">
                            <p>จำนวนผู้ใช้ทั้งหมด: <?php echo $user_count; ?> คน</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add/Edit User Form and User List in Two Columns -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <?php echo isset($user_data) ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้ใหม่'; ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <form action="users.php" method="POST">
                                <div class="form-row">
                                    <div class="col-md-6 mb-3">
                                        <label for="username">ชื่อผู้ใช้</label>
                                        <input type="text" class="form-control" id="username" name="username" placeholder="ชื่อผู้ใช้" required value="<?php echo isset($user_data['username']) ? $user_data['username'] : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email">อีเมล</label>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="อีเมล" required value="<?php echo isset($user_data['email']) ? $user_data['email'] : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="password">รหัสผ่าน</label>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="รหัสผ่าน" <?php echo isset($user_data) ? '' : 'required'; ?>>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="role">สิทธิ์</label>
                                        <select class="form-control" id="role" name="role" required>
                                            <option value="admin" <?php echo (isset($user_data) && $user_data['role'] == 'admin') ? 'selected' : ''; ?>>แอดมิน</option>
                                            <option value="user" <?php echo (isset($user_data) && $user_data['role'] == 'user') ? 'selected' : ''; ?>>ผู้ใช้</option>
                                        </select>
                                    </div>
                                </div>
                                <input type="hidden" name="action" value="<?php echo isset($user_data) ? 'edit' : 'add'; ?>">
                                <input type="hidden" name="user_id" value="<?php echo isset($user_data['user_id']) ? $user_data['user_id'] : ''; ?>">
                                <button type="submit" class="btn btn-primary btn-block">บันทึก</button>
                            </form>

                            <!-- Button to switch to Add User form -->
                            <?php if (isset($user_data)): ?>
                                <form action="users.php" method="GET" class="mt-3">
                                    <input type="hidden" name="action" value="add">
                                    <button type="submit" class="btn btn-secondary btn-block">กลับไปเพิ่มผู้ใช้</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">รายการผู้ใช้</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ชื่อผู้ใช้</th>
                                        <th>อีเมล</th>
                                        <th>สิทธิ์</th>
                                        <th>การกระทำ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                                            <td>
                                                <a href="users.php?user_id=<?php echo $user['user_id']; ?>" class="btn btn-warning btn-sm">แก้ไข</a>
                                                <form action="users.php" method="POST" class="d-inline" onsubmit="return confirm('คุณต้องการลบผู้ใช้นี้หรือไม่?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-danger btn-sm">ลบ</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>