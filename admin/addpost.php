<?php
session_start();
require_once '../includes/config.php';

// ตรวจสอบสิทธิ์ผู้ใช้
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "คุณไม่มีสิทธิ์เข้าถึง";
    header('Location: ../auth/login.php');
    exit();
}

// ฟังก์ชันตรวจสอบและอัปโหลดรูปภาพ
function uploadImage($file, $pdo, $user_id)
{
    if (empty($file['name'])) return null;

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    // ตรวจสอบขนาดและประเภทไฟล์
    if ($file['size'] > $maxSize || !in_array(mime_content_type($file['tmp_name']), $allowedTypes)) {
        return null;
    }

    $uploadDir = '../uploads/images/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = uniqid('img_') . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $uploadPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO media (filename, file_type, file_size, user_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $filename,
                mime_content_type($uploadPath),
                filesize($uploadPath),
                $user_id
            ]);
            return $pdo->lastInsertId();
        } catch (PDOException $e) {
            return null;
        }
    }
    return null;
}

try {
    // ดึงหมวดหมู่และแท็ก
    $categories = $pdo->query("SELECT category_id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $tags = $pdo->query("SELECT tag_id, name FROM tags ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // ประมวลผลฟอร์ม
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $category_id = intval($_POST['category_id']);
        $status = in_array($_POST['status'], ['draft', 'published']) ? $_POST['status'] : 'draft';
        $user_id = $_SESSION['user_id'];
        $tags_selected = isset($_POST['tags']) ? array_map('intval', $_POST['tags']) : [];

        // ตรวจสอบข้อมูล
        $errors = [];
        if (empty($title)) $errors[] = "กรุณากรอกชื่อโพสต์";
        if (empty($content)) $errors[] = "กรุณากรอกเนื้อหาโพสต์";
        if (empty($category_id)) $errors[] = "กรุณาเลือกหมวดหมู่";

        // อัปโหลดภาพปก
        $featured_image_id = uploadImage($_FILES['featured_image'], $pdo, $user_id);

        // บันทึกข้อมูล
        if (empty($errors)) {
            $pdo->beginTransaction();

            try {
                // เพิ่มโพสต์
                $stmt = $pdo->prepare("
                    INSERT INTO posts 
                    (title, content, user_id, category_id, status, featured_image_id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $title,
                    $content,
                    $user_id,
                    $category_id,
                    $status,
                    $featured_image_id
                ]);
                $post_id = $pdo->lastInsertId();

                // เพิ่มแท็ก
                if (!empty($tags_selected)) {
                    $tag_stmt = $pdo->prepare("INSERT INTO post_tag (post_id, tag_id) VALUES (?, ?)");
                    foreach ($tags_selected as $tag_id) {
                        $tag_stmt->execute([$post_id, $tag_id]);
                    }
                }

                // บันทึกประวัติ
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, target_type, target_id, created_at) 
                    VALUES (?, 'create', 'post', ?, NOW())
                ");
                $log_stmt->execute([$user_id, $post_id]);

                $pdo->commit();
                $_SESSION['success_message'] = "เพิ่มโพสต์สำเร็จ";
                header("Location: addposts.php");
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    }
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<?php include 'includes/header.php'; ?>

<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">เพิ่มโพสต์ใหม่</h1>
            </div>

            <?php
            if (!empty($errors)) {
                echo '<div class="alert alert-danger">';
                foreach ($errors as $error) {
                    echo '<p>' . htmlspecialchars($error) . '</p>';
                }
                echo '</div>';
            }
            ?>

            <form action="" method="POST" enctype="multipart/form-data">

                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="form-group">
                            <label>หัวข้อโพสต์</label>
                            <input type="text" name="title" class="form-control" value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label>เนื้อหาโพสต์</label>
                            <textarea name="content" class="form-control" rows="10" required><?php echo isset($content) ? htmlspecialchars($content) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>หมวดหมู่</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">เลือกหมวดหมู่</option>
                                <?php
                                foreach ($categories as $category) {
                                    echo "<option value='" . htmlspecialchars($category['category_id']) . "'>" .
                                        htmlspecialchars($category['name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                        <label>แท็ก</label>
                        <select name="tags[]" class="form-control select2-tags" multiple="multiple">
                            <?php
                            foreach ($tags as $tag) {
                                echo "<option value='" . htmlspecialchars($tag['tag_id']) . "'>" .
                                    htmlspecialchars($tag['name']) . "</option>";
                            }
                            ?>
                        </select>
                        </div>

                        <div class="form-group">
                            <label>สถานะ</label>
                            <select name="status" class="form-control">
                                <option value="draft">ฉบับร่าง</option>
                                <option value="published">เผยแพร่</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>ภาพปก</label>
                            <input type="file" name="featured_image" class="form-control" accept="image/*">
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">บันทึกโพสต์</button>
                            <a href="posts.php" class="btn btn-secondary">ยกเลิก</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>



<?php include 'includes/footer.php'; ?>
<script>
    $(document).ready(function() {

        // การตั้งค่า Select2 สำหรับแท็ก
        $('.select2-tags').select2({
            placeholder: 'เลือกแท็ก',
            allowClear: true,
            tags: true, // อนุญาตให้เพิ่มแท็กใหม่
            tokenSeparators: [',', ' ']
        });
    });
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>