<?php
session_start();
require_once '../includes/config.php';

// ตรวจสอบสิทธิ์ผู้ใช้
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "คุณไม่มีสิทธิ์เข้าถึง";
    header('Location: ../auth/login.php');
    exit();
}

// ตรวจสอบรหัสโพสต์
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ไม่พบรหัสโพสต์";
    header('Location: posts.php');
    exit();
}

$post_id = intval($_GET['id']);

// ดึงข้อมูลโพสต์ปัจจุบัน
try {
    $stmt = $pdo->prepare("SELECT p.*, GROUP_CONCAT(DISTINCT t.name) as tags, m.filename as featured_image_filename 
                            FROM posts p 
                            LEFT JOIN post_tag pt ON p.post_id = pt.post_id 
                            LEFT JOIN tags t ON pt.tag_id = t.tag_id 
                            LEFT JOIN media m ON p.featured_image_id = m.media_id 
                            WHERE p.post_id = ? 
                            GROUP BY p.post_id");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        $_SESSION['error_message'] = "ไม่พบโพสต์";
        header('Location: posts.php');
        exit();
    }
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}

// กึงแท็กทั้งหมดจากฐานข้อมูล
$tags_stmt = $pdo->query("SELECT tag_id, name FROM tags ORDER BY name");
$tags = $tags_stmt->fetchAll(PDO::FETCH_ASSOC);

// กระบวนการบันทึกการแก้ไข
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_current_image'])) {
        // ลบภาพปกปัจจุบัน
        $delete_stmt = $pdo->prepare("UPDATE posts SET featured_image_id = NULL WHERE post_id = ?");
        $delete_stmt->execute([$post_id]);
        $_SESSION['success_message'] = "ลบภาพปกปัจจุบันสำเร็จ";
        header("Location: editpost.php?id=" . $post_id);
        exit();
    }

    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $category_id = intval($_POST['category_id']);
    $status = $_POST['status'];
    $tags_selected = isset($_POST['tags']) ? explode(',', $_POST['tags']) : []; // แยกแท็กที่กรอกเป็นอาเรย์
    $featured_image_id = $post['featured_image_id'];

    // Validate inputs
    $errors = [];
    if (empty($title)) $errors[] = "กรุณากรอกชื่อโพสต์";
    if (empty($content)) $errors[] = "กรุณากรอกเนื้อหาโพสต์";
    if (empty($category_id)) $errors[] = "กรุณาเลือกหมวดหมู่";

    // Handle image upload
    if (isset($_FILES['new_featured_image']) && $_FILES['new_featured_image']['error'] == 0) {
        $new_image_id = uploadImage($_FILES['new_featured_image'], $pdo, $_SESSION['user_id']);
        if ($new_image_id !== null) {
            $featured_image_id = $new_image_id;
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดภาพ";
        }
    }

    if (empty($errors)) {
        // Begin transaction
        $pdo->beginTransaction();

        try {
            // Update post
            $update_stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, category_id = ?, status = ?, featured_image_id = ?, updated_at = NOW() WHERE post_id = ?");
            $update_stmt->execute([$title, $content, $category_id, $status, $featured_image_id, $post_id]);

            // Update tags
            $delete_tags_stmt = $pdo->prepare("DELETE FROM post_tag WHERE post_id = ?");
            $delete_tags_stmt->execute([$post_id]);

            if (!empty($tags_selected)) {
                $tag_stmt = $pdo->prepare("INSERT INTO post_tag (post_id, tag_id) VALUES (?, ?)");
                foreach ($tags_selected as $tag_name) {
                    // ตรวจสอบว่ามีแท็กนี้อยู่ในฐานข้อมูลหรือไม่
                    $tag_check_stmt = $pdo->prepare("SELECT tag_id FROM tags WHERE name = ?");
                    $tag_check_stmt->execute([$tag_name]);
                    $tag = $tag_check_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($tag) {
                        $tag_stmt->execute([$post_id, $tag['tag_id']]);
                    } else {
                        // ถ้าไม่มีแท็กนี้ให้เพิ่มลงในฐานข้อมูล
                        $insert_tag_stmt = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
                        $insert_tag_stmt->execute([$tag_name]);
                        $new_tag_id = $pdo->lastInsertId();
                        $tag_stmt->execute([$post_id, $new_tag_id]);
                    }
                }
            }

            // Commit transaction
            $pdo->commit();
            $_SESSION['success_message'] = "แก้ไขโพสต์สำเร็จ";
            header('Location: posts.php');
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "เกิดข้อผิดพลาดในการบันทึกโพสต์: " . $e->getMessage();
        }
    }
}

// ฟังก์ชันสำหรับอัปโหลดภาพ
function uploadImage($file, $pdo, $user_id)
{
    if (empty($file['name'])) {
        return null;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if ($file['size'] > $maxSize) {
        return null;
    }

    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        return null;
    }

    $uploadDir = '../uploads/images/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid('img_') . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $uploadPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO media (filename, file_type, file_size, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$filename, $fileType, filesize($uploadPath), $user_id]);
            return $pdo->lastInsertId();
        } catch (PDOException $e) {
            return null;
        }
    }

    return null;
}

include 'includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<div class="container-fluid">
    <h1 class="h3 mb-2 text-gray-800">แก้ไขโพสต์</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php
            echo htmlspecialchars($_SESSION['success_message']);
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <form method="post" action="" enctype="multipart/form-data">
        <div class="row">
            <div class="col-lg-9">
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="form-group">
                            <label>หัวข้อ</label>
                            <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>เนื้อหา</label>
                            <textarea class="form-control" name="content" rows="10" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>แท็ก (คั่นด้วยเครื่องหมายจุลภาค)</label>
                            <select name="tags[]" class="form-control select2-tags" multiple="multiple">
                                <?php
                                $selected_tags = isset($post['tags']) ? explode(',', $post['tags']) : [];
                                foreach ($tags as $tag): ?>
                                    <option value="<?php echo htmlspecialchars($tag['tag_id']); ?>" <?php echo in_array($tag['name'], $selected_tags) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tag['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="card shadow mb-4">
                    <div class="card-header">การตั้งค่า</div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>หมวดหมู่</label>
                            <select name="category_id" class="form-control" required>
                                <?php
                                $categories_stmt = $pdo->query("SELECT category_id, name FROM categories");
                                $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" <?php echo $category['category_id'] == $post['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>สถานะ</label>
                            <select name="status" class="form-control" required>
                                <option value="published" <?php echo $post['status'] == 'published' ? 'selected' : ''; ?>>เผยแพร่</option>
                                <option value="draft" <?php echo $post['status'] == 'draft' ? 'selected' : ''; ?>>บันทึกเป็นร่าง</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>ภาพปก</label>
                            <div class="form-check">
                                <label>ภาพปกปัจจุบัน</label>
                                <?php if (!empty($post['featured_image_filename'])): ?>
                                    <img id="currentImage" src="../uploads/images/<?php echo htmlspecialchars($post['featured_image_filename']); ?>" class="img-fluid" alt="Current Featured Image" style="max-width: 100%; height: auto;">
                                    <form method="post" action="" style="display:inline;">
                                        <button type="submit" name="delete_current_image" class="btn btn-danger btn-sm mt-2">ลบภาพปกปัจจุบัน</button>
                                    </form>
                                <?php else: ?>
                                    <p>ไม่มีภาพปกปัจจุบัน</p>
                                <?php endif; ?>
                            </div>

                            <div class="form-check">
                                <label>เลือกภาพปากที่อัปโหลด</label>
                                <select name="featured_image" class="form-control" onchange="showSelectedImage(this)">
                                    <option value="">เลือกภาพปกจากภาพที่อัปโหลด</option>
                                    <?php
                                    $media_stmt = $pdo->prepare("SELECT media_id, filename FROM media");
                                    $media_stmt->execute();
                                    $media_files = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($media_files as $file): ?>
                                        <option value="<?php echo htmlspecialchars($file['filename']); ?>" <?php echo $file['media_id'] == $post['featured_image_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($file['filename']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <img id="selectedImage" src="" class="img-fluid mt-2" style="display:none; max-width: 150px; height: auto;" alt="Selected Image">
                            </div>

                            <div class="form-check">
                                <label>หรืออัปโหลดภาพใหม่</label>
                                <input type="file" class="form-control" name="new_featured_image" accept="image/*" onchange="previewNewImage(this)">
                            </div>

                            <img id="newImagePreview" src="" class="img-fluid mt-2" style="display:none; max-width: 150px; height: auto;" alt="New Image Preview">
                        </div>

                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function showSelectedImage(select) {
    const selectedImage = document.getElementById('selectedImage');
    const filename = select.value;

    if (filename) {
        // สร้าง URL สำหรับแสดงภาพที่เลือก
        selectedImage.src = '../uploads/images/' + filename; // ใช้ filename ที่ถูกต้อง
        selectedImage.style.display = 'block';
    } else {
        selectedImage.style.display = 'none';
    }
}

function previewNewImage(input) {
    const newImagePreview = document.getElementById('newImagePreview');
    const file = input.files[0];

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            newImagePreview.src = e.target.result;
            newImagePreview.style.display = 'block';
        }
        reader.readAsDataURL(file);
    } else {
        newImagePreview.style.display = 'none';
    }
}

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

<?php include 'includes/footer.php'; ?>

