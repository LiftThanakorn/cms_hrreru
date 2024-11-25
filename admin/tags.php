<?php
session_start();
require_once '../includes/config.php';

// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Validation Functions
function validateTag($name, $slug)
{
    $errors = [];

    // Name validation
    if (empty($name) || strlen($name) < 3) {
        $errors[] = "ชื่อแท็กต้องมีอย่างน้อย 3 ตัวอักษร";
    }

    // Slug validation
    if (empty($slug) || strlen($slug) < 3) {
        $errors[] = "Slug ต้องมีอย่างน้อย 3 ตัวอักษร";
    }

    return $errors;
}

// Tag Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $name = $_POST['name'];
                    $slug = $_POST['slug'];

                    $errors = validateTag($name, $slug);

                    if (empty($errors)) {
                        $stmt = $pdo->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
                        $stmt->execute([$name, $slug]);
                        $_SESSION['message'] = "เพิ่มแท็กสำเร็จ";
                    } else {
                        $_SESSION['errors'] = $errors;
                    }
                    break;

                case 'edit':
                    $tag_id = $_POST['tag_id'];
                    $name = $_POST['name'];
                    $slug = $_POST['slug'];

                    $errors = validateTag($name, $slug);

                    if (empty($errors)) {
                        $stmt = $pdo->prepare("UPDATE tags SET name = ?, slug = ? WHERE tag_id = ?");
                        $stmt->execute([$name, $slug, $tag_id]);
                        $_SESSION['message'] = "แก้ไขแท็กสำเร็จ";
                    } else {
                        $_SESSION['errors'] = $errors;
                    }
                    break;

                case 'delete':
                    $tag_id = $_POST['tag_id'];
                    $stmt = $pdo->prepare("DELETE FROM tags WHERE tag_id = ?");
                    $stmt->execute([$tag_id]);
                    $_SESSION['message'] = "ลบแท็กสำเร็จ";
                    break;
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }

    header('Location: tags.php');
    exit();
}

// Fetch Tags
$stmt = $pdo->query("SELECT * FROM tags");
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch tag count
$stmt = $pdo->query("SELECT COUNT(*) FROM tags");
$tag_count = $stmt->fetchColumn();

// Edit Tag (for form display)
$tag_data = null;
if (isset($_GET['tag_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM tags WHERE tag_id = ?");
    $stmt->execute([$_GET['tag_id']]);
    $tag_data = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<?php include 'includes/header.php'; ?>

<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">จัดการแท็ก</h1>
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

            <!-- Tag Count Report -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">รายงานจำนวนแท็ก</h6>
                        </div>
                        <div class="card-body">
                            <p>จำนวนแท็กทั้งหมด: <?php echo $tag_count; ?> แท็ก</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add/Edit Tag Form and Tag List in Two Columns -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <?php echo isset($tag_data) ? 'แก้ไขแท็ก' : 'เพิ่มแท็กใหม่'; ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <form action="tags.php" method="POST">
                                <div class="form-row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name">ชื่อแท็ก</label>
                                        <input type="text" class="form-control" id="name" name="name" placeholder="ชื่อแท็ก" required value="<?php echo isset($tag_data['name']) ? $tag_data['name'] : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="slug">Slug</label>
                                        <input type="text" class="form-control" id="slug" name="slug" placeholder="slug" required value="<?php echo isset($tag_data['slug']) ? $tag_data['slug'] : ''; ?>">
                                    </div>
                                </div>
                                <input type="hidden" name="action" value="<?php echo isset($tag_data) ? 'edit' : 'add'; ?>">
                                <input type="hidden" name="tag_id" value="<?php echo isset($tag_data['tag_id']) ? $tag_data['tag_id'] : ''; ?>">
                                <button type="submit" class="btn btn-primary btn-block">บันทึก</button>
                            </form>

                            <!-- Button to switch to Add Tag form -->
                            <?php if (isset($tag_data)): ?>
                                <form action="tags.php" method="GET" class="mt-3">
                                    <input type="hidden" name="action" value="add">
                                    <button type="submit" class="btn btn-secondary btn-block">กลับไปเพิ่มแท็ก</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">รายการแท็ก</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ชื่อแท็ก</th>
                                        <th>แก้ไข</th>
                                        <th>ลบ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tags as $tag): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tag['name']); ?></td>
                                        <td><a href="tags.php?tag_id=<?php echo $tag['tag_id']; ?>" class="btn btn-warning btn-sm">แก้ไข</a></td>
                                        <td>
                                            <form action="tags.php" method="POST" onsubmit="return confirm('ต้องการลบแท็กนี้ใช่หรือไม่?');">
                                                <input type="hidden" name="tag_id" value="<?php echo $tag['tag_id']; ?>">
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
