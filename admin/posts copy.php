<?php
session_start();
require_once '../includes/config.php';

// ตรวจสอบสิทธิ์ผู้ใช้
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ฟังก์ชันตรวจสอบข้อผิดพลาด
function validatePost($title, $content)
{
    $errors = [];
    if (empty($title)) {
        $errors[] = "กรุณากรอกชื่อเรื่อง";
    }
    if (empty($content)) {
        $errors[] = "กรุณากรอกเนื้อหา";
    }
    return $errors;
}

// ฟังก์ชันเพิ่มโพสต์
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] == 'add_post') {
            $title = $_POST['title'];
            $content = $_POST['content'];
            $category_id = $_POST['category_id'];
            $status = $_POST['status'];

            // รับค่า tags ที่ถูกส่งมาเป็น string และแปลงเป็น array
            $tags = !empty($_POST['tags']) ? explode(',', $_POST['tags']) : [];

            // ตรวจสอบข้อผิดพลาด
            $errors = validatePost($title, $content);

            if (empty($errors)) {
                // เพิ่มโพสต์ใหม่ลงในตาราง posts
                $stmt = $pdo->prepare("INSERT INTO posts (title, content, user_id, category_id, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $content, $_SESSION['user_id'], $category_id, $status]);
                $post_id = $pdo->lastInsertId();

                // เพิ่มข้อมูลในตาราง pivot post_tag
                if (!empty($tags)) {
                    $stmt = $pdo->prepare("INSERT INTO post_tag (post_id, tag_id) VALUES (?, ?)");
                    foreach ($tags as $tag_id) {
                        if (is_numeric($tag_id)) { // ตรวจสอบว่าเป็นตัวเลขก่อนบันทึก
                            $stmt->execute([$post_id, $tag_id]);
                        }
                    }
                }

                $_SESSION['message'] = "โพสต์ใหม่ถูกเพิ่มสำเร็จ";
                header('Location: posts.php');
                exit();
            } else {
                $_SESSION['errors'] = $errors;
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// ดึงข้อมูลหมวดหมู่ทั้งหมด
$stmt = $pdo->query("SELECT * FROM categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลแท็กทั้งหมด
$stmt = $pdo->query("SELECT * FROM tags");
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// แปลงข้อมูล tags เป็น JSON สำหรับใช้ใน JavaScript
$tagsJson = json_encode($tags);
?>

<?php include 'includes/header.php'; ?>

<!-- Custom CSS สำหรับ Tags Input -->
<style>
    .tags-input-wrapper {
        background: #fff;
        padding: 0.375rem;
        border: 1px solid #d1d3e2;
        border-radius: 0.35rem;
        min-height: calc(1.5em + 0.75rem + 2px);
        cursor: text;
    }

    .tags-input-wrapper:focus-within {
        border-color: #bac8f3;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }

    .tags-input-wrapper .tag {
        display: inline-flex;
        align-items: center;
        background: #4e73df;
        color: white;
        padding: 0.2rem 0.6rem;
        margin: 0.2rem;
        border-radius: 0.25rem;
        font-size: 0.875rem;
    }

    .tags-input-wrapper .tag .remove-tag {
        margin-left: 0.5rem;
        cursor: pointer;
        color: #fff;
        font-size: 1rem;
        font-weight: bold;
        padding: 0 0.25rem;
    }

    .tags-input-wrapper .tag .remove-tag:hover {
        opacity: 0.7;
    }

    .tags-input {
        border: none;
        outline: none;
        background: transparent;
        padding: 0.375rem;
        min-width: 60px;
        flex-grow: 1;
    }

    .tags-dropdown {
        position: absolute;
        background: white;
        border: 1px solid #d1d3e2;
        border-radius: 0.35rem;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        width: calc(100% - 2rem);
        display: none;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }

    .tags-dropdown .dropdown-item {
        padding: 0.5rem 1rem;
        cursor: pointer;
    }

    .tags-dropdown .dropdown-item:hover {
        background: #4e73df;
        color: white;
    }

    .tags-input-wrapper .selected-tags {
        display: inline;
    }
</style>

<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">เพิ่มโพสต์ใหม่</h1>
            </div>

            <!-- แสดงข้อความแจ้งเตือน -->
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

            <!-- ฟอร์มเพิ่มโพสต์ -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">ฟอร์มเพิ่มโพสต์</h6>
                </div>
                <div class="card-body">
                    <form action="addpost.php" method="POST">
                        <input type="hidden" name="action" value="add_post">
                        <div class="form-group">
                            <label for="title">ชื่อเรื่อง</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="content">เนื้อหา</label>
                            <textarea name="content" id="editor" required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="category">หมวดหมู่</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">เลือกหมวดหมู่</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>">
                                        <?php echo $category['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group position-relative">
                            <label for="tags">แท็ก</label>
                            <div class="tags-input-wrapper">
                                <div class="selected-tags"></div>
                                <input type="text" class="tags-input" placeholder="พิมพ์เพื่อค้นหาแท็ก...">
                            </div>
                            <div class="tags-dropdown"></div>
                            <input type="hidden" name="tags" id="selected-tags-input">
                        </div>

                        <div class="form-group">
                            <label for="status">สถานะ</label>
                            <select name="status" class="form-control" required>
                                <option value="publish">เผยแพร่</option>
                                <option value="draft">ฉบับร่าง</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">เพิ่มโพสต์</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
<script>
// กำหนดตัวแปร config สำหรับการอัพโหลดรูปภาพ
const uploadConfig = {
    // เปลี่ยน URL ตามที่คุณกำหนดไว้สำหรับการอัพโหลดรูปภาพ
    uploadUrl: 'upload_image.php',
    // กำหนดชนิดไฟล์ที่อนุญาต
    allowedFileTypes: ['jpeg', 'jpg', 'png', 'gif'],
    // กำหนดขนาดไฟล์สูงสุด (2MB)
    maxFileSize: 2 * 1024 * 1024
};

// สร้าง Custom Upload Adapter
class MyUploadAdapter {
    constructor(loader) {
        this.loader = loader;
    }

    upload() {
        return this.loader.file.then(file => {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('upload', file);

                fetch(uploadConfig.uploadUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.error) {
                        reject(result.error);
                    } else {
                        resolve({
                            default: result.url
                        });
                    }
                })
                .catch(error => {
                    reject('Upload failed');
                });
            });
        });
    }

    abort() {
        // Abort upload implementation
    }
}

// กำหนด Function สำหรับสร้าง Upload Adapter
function MyCustomUploadAdapterPlugin(editor) {
    editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
        return new MyUploadAdapter(loader);
    };
}

// Configuration สำหรับ CKEditor
ClassicEditor
    .create(document.querySelector('#editor'), {
        // เพิ่ม Custom Upload Adapter
        extraPlugins: [MyCustomUploadAdapterPlugin],
        
        // กำหนด Toolbar
        toolbar: {
            items: [
                'undo', 'redo',
                '|',
                'heading',
                '|',
                'bold', 'italic', 'underline', 'strikethrough',
                '|',
                'link', 'blockQuote', 'code',
                '|',
                'bulletedList', 'numberedList',
                '|',
                'insertTable',
                '|',
                'uploadImage', 'mediaEmbed',
                '|',
                'codeBlock',
                '|',
                'alignment',
                '|',
                'fontColor', 'fontBackgroundColor'
            ],
            shouldNotGroupWhenFull: true
        },

        // กำหนดค่าสำหรับรูปภาพ
        image: {
            toolbar: [
                'imageStyle:inline',
                'imageStyle:block',
                'imageStyle:side',
                '|',
                'toggleImageCaption',
                'imageTextAlternative',
                '|',
                'resizeImage'
            ],
            upload: {
                types: uploadConfig.allowedFileTypes,
                maxFileSize: uploadConfig.maxFileSize
            },
            resizeOptions: [
                {
                    name: 'resizeImage:original',
                    label: 'Original',
                    value: null
                },
                {
                    name: 'resizeImage:50',
                    label: '50%',
                    value: '50'
                },
                {
                    name: 'resizeImage:75',
                    label: '75%',
                    value: '75'
                }
            ]
        },

        // กำหนดค่าสำหรับตาราง
        table: {
            contentToolbar: [
                'tableColumn',
                'tableRow',
                'mergeTableCells',
                'tableCellProperties',
                'tableProperties'
            ]
        },

        // กำหนดภาษา
        language: 'th',

        // กำหนดความสูงของ Editor
        height: '400px',

        // กำหนด placeholder
        placeholder: 'เริ่มเขียนเนื้อหาของคุณที่นี่...',

        // กำหนดการ Auto Save (ทุก 30 วินาที)
        autosave: {
            save(editor) {
        // สามารถเพิ่ม Logic การ Auto Save ได้ที่นี่
        return saveData(editor.getData());
            }
        },

        // เพิ่ม style สำหรับ editor container
        style: {
            'min-height': '400px',
            'max-height': '800px',
            'overflow-y': 'auto'
        }
    })
    .then(editor => {
        // เก็บ instance ไว้ใช้งาน
        window.editor = editor;
        
        // Event listener เมื่อมีการเปลี่ยนแปลงเนื้อหา
        editor.model.document.on('change:data', () => {
            // บันทึกข้อมูลอัตโนมัติ
            localStorage.setItem('editor-content', editor.getData());
        });

        // ตรวจสอบข้อมูลที่บันทึกไว้ใน localStorage
        const savedContent = localStorage.getItem('editor-content');
        if (savedContent) {
            editor.setData(savedContent);
        }
    })
    .catch(error => {
        console.error('เกิดข้อผิดพลาดในการโหลด Editor:', error);
    });

// ฟังก์ชันสำหรับ Auto Save
function saveData(data) {
    return new Promise((resolve, reject) => {
        try {
            localStorage.setItem('editor-content', data);
            resolve();
        } catch (error) {
            reject(error);
        }
    });
}

// ฟังก์ชันสำหรับ Tags
document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.querySelector('.tags-input-wrapper');
    const input = wrapper.querySelector('.tags-input');
    const dropdown = document.querySelector('.tags-dropdown');
    const selectedTagsContainer = wrapper.querySelector('.selected-tags');
    const hiddenInput = document.querySelector('#selected-tags-input');

    // สร้างตัวแปรเก็บ tags ที่เลือก
    let selectedTags = new Set();

    // ข้อมูล tags จากฐานข้อมูล
    const availableTags = <?php echo $tagsJson; ?>;

    // ฟังก์ชันแสดง tags ที่เลือก
    function renderTags() {
        selectedTagsContainer.innerHTML = '';
        selectedTags.forEach(tag => {
            const tagElement = document.createElement('span');
            tagElement.className = 'tag';
            tagElement.innerHTML = `
                ${tag.name}
                <span class="remove-tag" data-id="${tag.tag_id}">&times;</span>
            `;
            selectedTagsContainer.appendChild(tagElement);
        });

        // อัพเดท hidden input
        updateHiddenInput();
    }

    // อัพเดท hidden input สำหรับ form submission
    function updateHiddenInput() {
        hiddenInput.value = Array.from(selectedTags).map(tag => tag.tag_id).join(',');
    }

    // ฟังก์ชันแสดง dropdown
    function showDropdown(filter = '') {
        const filteredTags = availableTags.filter(tag => {
            return tag.name.toLowerCase().includes(filter.toLowerCase()) &&
                !Array.from(selectedTags).some(selectedTag => selectedTag.tag_id === tag.tag_id);
        });

        if (filteredTags.length === 0) {
            dropdown.style.display = 'none';
            return;
        }

        dropdown.innerHTML = filteredTags
            .map(tag => `<div class="dropdown-item" data-id="${tag.tag_id}">${tag.name}</div>`)
            .join('');

        dropdown.style.display = 'block';
    }

    // Event Listeners
    input.addEventListener('focus', () => showDropdown(input.value));

    input.addEventListener('input', (e) => {
        showDropdown(e.target.value);
    });

    // ปิด dropdown เมื่อคลิกนอก component
    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    // การเลือก tag จาก dropdown
    dropdown.addEventListener('click', (e) => {
        const item = e.target.closest('.dropdown-item');
        if (!item) return;

        const id = parseInt(item.dataset.id);
        const tag = availableTags.find(t => t.tag_id === id);

        if (tag) {
            selectedTags.add(tag);
            renderTags();
            input.value = '';
            dropdown.style.display = 'none';
        }
    });

    // การลบ tag
    wrapper.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-tag')) {
            const id = parseInt(e.target.dataset.id);
            selectedTags = new Set(Array.from(selectedTags).filter(tag => tag.tag_id !== id));
            renderTags();
        }
    });
});
</script>