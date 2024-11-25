<?php
session_start();
require_once '../includes/config.php';

// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Validation Functions
function validateCategory($name, $slug, $parent_id = null)
{
    $errors = [];

    // Name validation
    if (empty($name) || strlen($name) < 3) {
        $errors[] = "ชื่อหมวดหมู่ต้องมีอย่างน้อย 3 ตัวอักษร";
    }

    // Slug validation
    if (empty($slug) || strlen($slug) < 3) {
        $errors[] = "Slug ต้องมีอย่างน้อย 3 ตัวอักษร";
    }

    return $errors;
}

// Category Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $name = $_POST['name'];
                    $slug = $_POST['slug'];
                    $parent_id = $_POST['parent_id'] ?? null;

                    $errors = validateCategory($name, $slug, $parent_id);

                    if (empty($errors)) {
                        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
                        $stmt->execute([$name, $slug, $parent_id]);
                        $_SESSION['message'] = "เพิ่มหมวดหมู่สำเร็จ";
                    } else {
                        $_SESSION['errors'] = $errors;
                    }
                    break;

                case 'edit':
                    $category_id = $_POST['category_id'];
                    $name = $_POST['name'];
                    $slug = $_POST['slug'];
                    $parent_id = $_POST['parent_id'] ?? null;

                    $errors = validateCategory($name, $slug, $parent_id);

                    if (empty($errors)) {
                        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, parent_id = ? WHERE category_id = ?");
                        $stmt->execute([$name, $slug, $parent_id, $category_id]);
                        $_SESSION['message'] = "แก้ไขหมวดหมู่สำเร็จ";
                    } else {
                        $_SESSION['errors'] = $errors;
                    }
                    break;

                case 'delete':
                    $category_id = $_POST['category_id'];
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
                    $stmt->execute([$category_id]);
                    $_SESSION['message'] = "ลหมวดหมู่สำเร็จ";
                    break;
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }

    header('Location: category.php');
    exit();
}

// Fetch Categories
$stmt = $pdo->query("SELECT * FROM categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch category count
$stmt = $pdo->query("SELECT COUNT(*) FROM categories");
$category_count = $stmt->fetchColumn();

// Edit Category (for form display)
$category_data = null;
if (isset($_GET['category_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
    $stmt->execute([$_GET['category_id']]);
    $category_data = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<?php include 'includes/header.php'; ?>

<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">จัดการหมวดหมู่</h1>
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

            <!-- Category Count Report -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">รายงานจำนวนหมวดหมู่</h6>
                        </div>
                        <div class="card-body">
                            <p>จำนวนหมวดหมู่ทั้งหมด: <?php echo $category_count; ?> หมวดหมู่</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add/Edit Category Form and Category List in Two Columns -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <?php echo isset($category_data) ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่ใหม่'; ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <form action="category.php" method="POST">
                                <div class="form-row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name">ชื่อหมวดหมู่</label>
                                        <input type="text" class="form-control" id="name" name="name" placeholder="ชื่อหมวดหมู่" required value="<?php echo isset($category_data['name']) ? $category_data['name'] : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="slug">Slug</label>
                                        <input type="text" class="form-control" id="slug" name="slug" placeholder="slug" required value="<?php echo isset($category_data['slug']) ? $category_data['slug'] : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="parent_id">หมวดหมู่หลัก</label>
                                        <select class="form-control" id="parent_id" name="parent_id">
                                            <option value="">เลือกหมวดหมู่หลัก</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['category_id']; ?>" <?php echo (isset($category_data) && $category_data['parent_id'] == $category['category_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <input type="hidden" name="action" value="<?php echo isset($category_data) ? 'edit' : 'add'; ?>">
                                <input type="hidden" name="category_id" value="<?php echo isset($category_data['category_id']) ? $category_data['category_id'] : ''; ?>">
                                <button type="submit" class="btn btn-primary btn-block">บันทึก</button>
                            </form>

                            <!-- Button to switch to Add Category form -->
                            <?php if (isset($category_data)): ?>
                                <form action="category.php" method="GET" class="mt-3">
                                    <input type="hidden" name="action" value="add">
                                    <button type="submit" class="btn btn-secondary btn-block">กลับไปเพิ่มหมวดหมู่</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">รายการหมวดหมู่</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ชื่อหมวดหมู่</th>
                                        <th>แก้ไข</th>
                                        <th>ลบ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td><a href="category.php?category_id=<?php echo $category['category_id']; ?>" class="btn btn-warning btn-sm">แก้ไข</a></td>
                                        <td>
                                            <form action="category.php" method="POST" onsubmit="return confirm('ต้องการลบหมวดหมู่นี้ใช่หรือไม่?');">
                                                <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
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
