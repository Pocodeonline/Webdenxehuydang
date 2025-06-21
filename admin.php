<?php
require('system/dbconfig.php');

$auth = isAuthenticated();
if (!$auth['status']) {
    header('Location: login.php');
    exit();
}

$action = $_GET['action'] ?? 'post';

// Xử lý các action POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input['action'] === 'add-post') {
        $name = trim($input['name']);
        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO post (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Thêm danh mục thành công!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Lỗi khi thêm danh mục!']);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Tên danh mục không được để trống!']);
        }
        exit;
    }
    
    if ($input['action'] === 'add-category') {
        $name = trim($input['name']);
        $post = trim($input['post']);
        if (!empty($name) && !empty($post)) {
            $stmt = $conn->prepare("INSERT INTO category (name, post) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $post);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Thêm phân loại thành công!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Lỗi khi thêm phân loại!']);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Tên phân loại và danh mục không được để trống!']);
        }
        exit;
    }
    
    if ($input['action'] === 'add-subfolder') {
        $name = trim($input['name']);
        $post = trim($input['post']);
        $category = trim($input['category']);
        $youtube = trim($input['youtube'] ?? '');
        $tiktok = trim($input['tiktok'] ?? '');
        $policy = trim($input['policy'] ?? '');
        
        if (!empty($name) && !empty($post) && !empty($category)) {
            $stmt = $conn->prepare("INSERT INTO subfolder (name, post, category, youtube, tiktok, policy, content) VALUES (?, ?, ?, ?, ?, ?, '')");
            $stmt->bind_param("ssssss", $name, $post, $category, $youtube, $tiktok, $policy);
            if ($stmt->execute()) {
                $subfolder_id = $conn->insert_id;
                
                // Tạo thư mục upload
                $post_stmt = $conn->prepare("SELECT id FROM post WHERE name = ?");
                $post_stmt->bind_param("s", $post);
                $post_stmt->execute();
                $post_result = $post_stmt->get_result();
                $post_data = $post_result->fetch_assoc();
                $post_stmt->close();
                
                if ($post_data) {
                    $upload_dir = "assets/upload/{$post_data['id']}/{$subfolder_id}";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                }
                
                echo json_encode(['status' => 'success', 'message' => 'Thêm sản phẩm thành công!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Lỗi khi thêm sản phẩm!']);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Tên sản phẩm, danh mục và phân loại không được để trống!']);
        }
        exit;
    }
    
    if ($input['action'] === 'edit-subfolder') {
        $id = intval($input['id']);
        $name = trim($input['name']);
        $youtube = trim($input['youtube'] ?? '');
        $tiktok = trim($input['tiktok'] ?? '');
        $policy = trim($input['policy'] ?? '');
        
        if (!empty($name) && $id > 0) {
            $stmt = $conn->prepare("UPDATE subfolder SET name = ?, youtube = ?, tiktok = ?, policy = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $youtube, $tiktok, $policy, $id);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Cập nhật sản phẩm thành công!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Lỗi khi cập nhật sản phẩm!']);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ!']);
        }
        exit;
    }
}

// Xử lý xóa qua GET
if (isset($_GET['delete'])) {
    $type = $_GET['delete'];
    $id = intval($_GET['id'] ?? 0);
    
    if ($type === 'post' && $id > 0) {
        // Xóa tất cả subfolder liên quan
        $stmt = $conn->prepare("SELECT name FROM post WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $post_data = $result->fetch_assoc();
        $stmt->close();
        
        if ($post_data) {
            $post_name = $post_data['name'];
            
            // Xóa subfolder
            $stmt = $conn->prepare("DELETE FROM subfolder WHERE post = ?");
            $stmt->bind_param("s", $post_name);
            $stmt->execute();
            $stmt->close();
            
            // Xóa category
            $stmt = $conn->prepare("DELETE FROM category WHERE post = ?");
            $stmt->bind_param("s", $post_name);
            $stmt->execute();
            $stmt->close();
            
            // Xóa post
            $stmt = $conn->prepare("DELETE FROM post WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            
            // Xóa thư mục upload
            $upload_dir = "assets/upload/{$id}";
            if (is_dir($upload_dir)) {
                function deleteDirectory($dir) {
                    if (!is_dir($dir)) return false;
                    $files = array_diff(scandir($dir), ['.', '..']);
                    foreach ($files as $file) {
                        $path = $dir . '/' . $file;
                        is_dir($path) ? deleteDirectory($path) : unlink($path);
                    }
                    return rmdir($dir);
                }
                deleteDirectory($upload_dir);
            }
        }
        
        header('Location: admin.php?action=post&msg=deleted');
        exit;
    }
    
    if ($type === 'category' && $id > 0) {
        // Lấy thông tin category trước khi xóa
        $stmt = $conn->prepare("SELECT name, post FROM category WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $category_data = $result->fetch_assoc();
        $stmt->close();
        
        if ($category_data) {
            $category_name = $category_data['name'];
            $post_name = $category_data['post'];
            
            // Xóa tất cả subfolder thuộc category này
            $stmt = $conn->prepare("DELETE FROM subfolder WHERE category = ? AND post = ?");
            $stmt->bind_param("ss", $category_name, $post_name);
            $stmt->execute();
            $stmt->close();
            
            // Xóa category
            $stmt = $conn->prepare("DELETE FROM category WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
        
        header('Location: admin.php?action=category&msg=deleted');
        exit;
    }
    
    if ($type === 'subfolder' && $id > 0) {
        // Lấy thông tin để xóa thư mục
        $stmt = $conn->prepare("SELECT s.*, p.id as post_id FROM subfolder s JOIN post p ON p.name = s.post WHERE s.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $subfolder_data = $result->fetch_assoc();
        $stmt->close();
        
        if ($subfolder_data) {
            // Xóa subfolder
            $stmt = $conn->prepare("DELETE FROM subfolder WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            
            // Xóa thư mục upload
            $upload_dir = "assets/upload/{$subfolder_data['post_id']}/{$id}";
            if (is_dir($upload_dir)) {
                function deleteDirectory($dir) {
                    if (!is_dir($dir)) return false;
                    $files = array_diff(scandir($dir), ['.', '..']);
                    foreach ($files as $file) {
                        $path = $dir . '/' . $file;
                        is_dir($path) ? deleteDirectory($path) : unlink($path);
                    }
                    return rmdir($dir);
                }
                deleteDirectory($upload_dir);
            }
        }
        
        header('Location: admin.php?action=subfolder&msg=deleted');
        exit;
    }
}

// Xử lý upload ảnh
if (isset($_POST['upload_images'])) {
    $subfolder_id = intval($_POST['subfolder_id']);
    $post_id = intval($_POST['post_id']);
    
    if ($subfolder_id > 0 && $post_id > 0) {
        $upload_dir = "assets/upload/{$post_id}/{$subfolder_id}";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $uploaded_files = [];
        $errors = [];
        
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if (!empty($tmp_name)) {
                $file_name = $_FILES['images']['name'][$key];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $new_name = uniqid() . '.' . $file_ext;
                    $target_path = $upload_dir . '/' . $new_name;
                    
                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $uploaded_files[] = $new_name;
                    } else {
                        $errors[] = "Không thể upload file: " . $file_name;
                    }
                } else {
                    $errors[] = "File không hợp lệ: " . $file_name;
                }
            }
        }
        
        if (!empty($uploaded_files)) {
            $success_msg = "Upload thành công " . count($uploaded_files) . " ảnh!";
        }
        if (!empty($errors)) {
            $error_msg = implode(", ", $errors);
        }
    }
}

require('system/head.php');
?>

<div class="max-w-7xl mx-auto py-10 px-4">
    <h1 class="text-3xl tracking-wider text-[#27f2f2] font-bold uppercase text-center mb-10 drop-shadow-[0_0_10px_#27f2f282]" 
        data-aos="fade-up" data-aos-duration="800">
        <div class="inline-block border-b-4 border-[#27f2f2] pb-4">
            QUẢN LÝ WEBSITE
        </div>
    </h1>

    <!-- Navigation -->
    <div class="flex flex-wrap justify-center gap-4 mb-8">
        <a href="admin.php?action=post" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition <?= $action === 'post' ? 'bg-blue-800' : '' ?>">
            Danh mục
        </a>
        <a href="admin.php?action=category" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition <?= $action === 'category' ? 'bg-green-800' : '' ?>">
            Phân loại
        </a>
        <a href="admin.php?action=subfolder" class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition <?= $action === 'subfolder' ? 'bg-purple-800' : '' ?>">
            Sản phẩm
        </a>
        <a href="admin.php?action=upload" class="px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition <?= $action === 'upload' ? 'bg-orange-800' : '' ?>">
            Upload ảnh
        </a>
        <a href="logout.php" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
            Đăng xuất
        </a>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="bg-green-500 text-white p-4 rounded-lg mb-6 text-center">
            Xóa thành công!
        </div>
    <?php endif; ?>

    <?php if (isset($success_msg)): ?>
        <div class="bg-green-500 text-white p-4 rounded-lg mb-6 text-center">
            <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="bg-red-500 text-white p-4 rounded-lg mb-6 text-center">
            <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <?php if ($action === 'post'): ?>
        <!-- Quản lý Danh mục -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-[#27f2f2] mb-4">Thêm Danh mục mới</h2>
            <div id="post-message" class="mb-4"></div>
            <form id="add-post-form" class="space-y-4">
                <div>
                    <label class="block text-white mb-2">Tên danh mục:</label>
                    <input type="text" name="name" required class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                </div>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Thêm danh mục
                </button>
            </form>
        </div>

        <!-- Danh sách Danh mục -->
        <div class="bg-gray-800 rounded-lg p-6">
            <h2 class="text-2xl font-bold text-[#27f2f2] mb-4">Danh sách Danh mục</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-white">
                    <thead>
                        <tr class="border-b border-gray-600">
                            <th class="text-left py-2">ID</th>
                            <th class="text-left py-2">Tên danh mục</th>
                            <th class="text-left py-2">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT * FROM post ORDER BY id DESC");
                        while ($row = $result->fetch_assoc()):
                        ?>
                        <tr class="border-b border-gray-700">
                            <td class="py-2"><?= $row['id'] ?></td>
                            <td class="py-2"><?= htmlspecialchars($row['name']) ?></td>
                            <td class="py-2">
                                <a href="admin.php?delete=post&id=<?= $row['id'] ?>" 
                                   onclick="return confirm('Bạn có chắc muốn xóa danh mục này? Tất cả phân loại và sản phẩm liên quan sẽ bị xóa!')"
                                   class="text-red-400 hover:text-red-600">
                                    <i class="fa fa-trash"></i> Xóa
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($action === 'category'): ?>
        <!-- Quản lý Phân loại -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-[#27f2f2] mb-4">Thêm Phân loại mới</h2>
            <div id="category-message" class="mb-4"></div>
            <form id="add-category-form" class="space-y-4">
                <div>
                    <label class="block text-white mb-2">Chọn danh mục:</label>
                    <select name="post" required class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                        <option value="">-- Chọn danh mục --</option>
                        <?php
                        $posts = $conn->query("SELECT * FROM post ORDER BY name");
                        while ($post = $posts->fetch_assoc()):
                        ?>
                        <option value="<?= htmlspecialchars($post['name']) ?>"><?= htmlspecialchars($post['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-white mb-2">Tên phân loại:</label>
                    <input type="text" name="name" required class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                </div>
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    Thêm phân loại
                </button>
            </form>
        </div>

        <!-- Danh sách Phân loại -->
        <div class="bg-gray-800 rounded-lg p-6">
            <h2 class="text-2xl font-bold text-[#27f2f2] mb-4">Danh sách Phân loại</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-white">
                    <thead>
                        <tr class="border-b border-gray-600">
                            <th class="text-left py-2">ID</th>
                            <th class="text-left py-2">Danh mục</th>
                            <th class="text-left py-2">Phân loại</th>
                            <th class="text-left py-2">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT * FROM category ORDER BY id DESC");
                        while ($row = $result->fetch_assoc()):
                        ?>
                        <tr class="border-b border-gray-700">
                            <td class="py-2"><?= $row['id'] ?></td>
                            <td class="py-2"><?= htmlspecialchars($row['post']) ?></td>
                            <td class="py-2"><?= htmlspecialchars($row['name']) ?></td>
                            <td class="py-2">
                                <a href="admin.php?delete=category&id=<?= $row['id'] ?>" 
                                   onclick="return confirm('Bạn có chắc muốn xóa phân loại này? Tất cả sản phẩm liên quan sẽ bị xóa!')"
                                   class="text-red-400 hover:text-red-600">
                                    <i class="fa fa-trash"></i> Xóa
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($action === 'subfolder'): ?>
        <!-- Quản lý Sản phẩm -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-[#27f2f2] mb-4">Thêm Sản phẩm mới</h2>
            <div id="subfolder-message" class="mb-4"></div>
            <form id="add-subfolder-form" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-white mb-2">Chọn danh mục:</label>
                        <select name="post" id="post-select" required class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                            <option value="">-- Chọn danh mục --</option>
                            <?php
                            $posts = $conn->query("SELECT * FROM post ORDER BY name");
                            while ($post = $posts->fetch_assoc()):
                            ?>
                            <option value="<?= htmlspecialchars($post['name']) ?>"><?= htmlspecialchars($post['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-white mb-2">Chọn phân loại:</label>
                        <select name="category" id="category-select" required class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                            <option value="">-- Chọn phân loại --</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-white mb-2">Tên sản phẩm:</label>
                    <input type="text" name="name" required class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-white mb-2">Link YouTube (tùy chọn):</label>
                        <input type="url" name="youtube" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                    </div>
                    <div>
                        <label class="block text-white mb-2">Link TikTok (tùy chọn):</label>
                        <input type="url" name="tiktok" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                    </div>
                </div>
                <div>
                    <label class="block text-white mb-2">Chính sách bảo hành (mỗi dòng một chính sách):</label>
                    <textarea name="policy" rows="4" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400" placeholder="Bảo hành 12 tháng&#10;Đổi trả trong 30 ngày&#10;Hỗ trợ kỹ thuật miễn phí"></textarea>
                </div>
                <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    Thêm sản phẩm
                </button>
            </form>
        </div>

        <!-- Danh sách Sản phẩm -->
        <div class="bg-gray-800 rounded-lg p-6">
            <h2 class="text-2xl font-bold text-[#27f2f2] mb-4">Danh sách Sản phẩm</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-white">
                    <thead>
                        <tr class="border-b border-gray-600">
                            <th class="text-left py-2">ID</th>
                            <th class="text-left py-2">Tên sản phẩm</th>
                            <th class="text-left py-2">Danh mục</th>
                            <th class="text-left py-2">Phân loại</th>
                            <th class="text-left py-2">Lượt xem</th>
                            <th class="text-left py-2">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT * FROM subfolder ORDER BY id DESC");
                        while ($row = $result->fetch_assoc()):
                        ?>
                        <tr class="border-b border-gray-700">
                            <td class="py-2"><?= $row['id'] ?></td>
                            <td class="py-2"><?= htmlspecialchars($row['name']) ?></td>
                            <td class="py-2"><?= htmlspecialchars($row['post']) ?></td>
                            <td class="py-2"><?= htmlspecialchars($row['category']) ?></td>
                            <td class="py-2"><?= $row['view'] ?></td>
                            <td class="py-2">
                                <a href="product.php?idp=<?= $row['id'] ?>" target="_blank" class="text-blue-400 hover:text-blue-600 mr-3">
                                    <i class="fa fa-eye"></i> Xem
                                </a>
                                <a href="admin.php?action=edit-post&id=<?= $row['id'] ?>" class="text-green-400 hover:text-green-600 mr-3">
                                    <i class="fa fa-edit"></i> Sửa
                                </a>
                                <a href="admin.php?delete=subfolder&id=<?= $row['id'] ?>" 
                                   onclick="return confirm('Bạn có chắc muốn xóa sản phẩm này?')"
                                   class="text-red-400 hover:text-red-600">
                                    <i class="fa fa-trash"></i> Xóa
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($action === 'edit-post'): ?>
        <?php
        $edit_id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM subfolder WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_data = $result->fetch_assoc();
        $stmt->close();
        
        if (!$edit_data) {
            header('Location: admin.php?action=subfolder');
            exit;
        }
        ?>
        
        <!-- Sửa Sản phẩm -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-[#27f2f2] mb-4">Sửa Sản phẩm: <?= htmlspecialchars($edit_data['name']) ?></h2>
            <div id="edit-message" class="mb-4"></div>
            <form id="edit-subfolder-form" class="space-y-4">
                <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                <div>
                    <label class="block text-white mb-2">Tên sản phẩm:</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($edit_data['name']) ?>" required class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-white mb-2">Link YouTube:</label>
                        <input type="url" name="youtube" value="<?= htmlspecialchars($edit_data['youtube']) ?>" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                    </div>
                    <div>
                        <label class="block text-white mb-2">Link TikTok:</label>
                        <input type="url" name="tiktok" value="<?= htmlspecialchars($edit_data['tiktok']) ?>" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                    </div>
                </div>
                <div>
                    <label class="block text-white mb-2">Chính sách bảo hành:</label>
                    <textarea name="policy" rows="4" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400"><?= htmlspecialchars($edit_data['policy']) ?></textarea>
                </div>
                <div class="flex gap-4">
                    <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        Cập nhật
                    </button>
                    <a href="admin.php?action=subfolder" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                        Hủy
                    </a>
                </div>
            </form>
        </div>

    <?php elseif ($action === 'upload'): ?>
        <!-- Upload ảnh -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-[#27f2f2] mb-4">Upload ảnh cho sản phẩm</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-white mb-2">Chọn danh mục:</label>
                        <select name="post_select" id="upload-post-select" required class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                            <option value="">-- Chọn danh mục --</option>
                            <?php
                            $posts = $conn->query("SELECT * FROM post ORDER BY name");
                            while ($post = $posts->fetch_assoc()):
                            ?>
                            <option value="<?= $post['id'] ?>|<?= htmlspecialchars($post['name']) ?>"><?= htmlspecialchars($post['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-white mb-2">Chọn sản phẩm:</label>
                        <select name="subfolder_id" id="upload-subfolder-select" required class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                            <option value="">-- Chọn sản phẩm --</option>
                        </select>
                        <input type="hidden" name="post_id" id="upload-post-id">
                    </div>
                </div>
                <div>
                    <label class="block text-white mb-2">Chọn ảnh (có thể chọn nhiều):</label>
                    <input type="file" name="images[]" multiple accept="image/*" required class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:border-cyan-400">
                </div>
                <button type="submit" name="upload_images" class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                    Upload ảnh
                </button>
            </form>
        </div>

        <!-- Danh sách ảnh đã upload -->
        <div class="bg-gray-800 rounded-lg p-6">
            <h2 class="text-2xl font-bold text-[#27f2f2] mb-4">Danh sách ảnh đã upload</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php
                $upload_base = "assets/upload/";
                if (is_dir($upload_base)) {
                    $post_dirs = array_diff(scandir($upload_base), ['.', '..']);
                    foreach ($post_dirs as $post_dir) {
                        $post_path = $upload_base . $post_dir;
                        if (is_dir($post_path)) {
                            $subfolder_dirs = array_diff(scandir($post_path), ['.', '..']);
                            foreach ($subfolder_dirs as $subfolder_dir) {
                                $subfolder_path = $post_path . '/' . $subfolder_dir;
                                if (is_dir($subfolder_path)) {
                                    $images = array_diff(scandir($subfolder_path), ['.', '..']);
                                    foreach ($images as $image) {
                                        $image_path = $subfolder_path . '/' . $image;
                                        if (is_file($image_path)) {
                                            echo '<div class="relative group">';
                                            echo '<img src="' . $image_path . '" alt="' . $image . '" class="w-full h-32 object-cover rounded-lg">';
                                            echo '<div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center">';
                                            echo '<span class="text-white text-xs text-center p-2">' . $post_dir . '/' . $subfolder_dir . '/' . $image . '</span>';
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Form handlers
document.getElementById('add-post-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.action = 'add-post';
    
    const response = await fetch('admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    
    const result = await response.json();
    const messageDiv = document.getElementById('post-message');
    
    if (result.status === 'success') {
        messageDiv.innerHTML = '<div class="bg-green-500 text-white p-3 rounded">' + result.message + '</div>';
        this.reset();
        setTimeout(() => location.reload(), 1500);
    } else {
        messageDiv.innerHTML = '<div class="bg-red-500 text-white p-3 rounded">' + result.message + '</div>';
    }
});

document.getElementById('add-category-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.action = 'add-category';
    
    const response = await fetch('admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    
    const result = await response.json();
    const messageDiv = document.getElementById('category-message');
    
    if (result.status === 'success') {
        messageDiv.innerHTML = '<div class="bg-green-500 text-white p-3 rounded">' + result.message + '</div>';
        this.reset();
        setTimeout(() => location.reload(), 1500);
    } else {
        messageDiv.innerHTML = '<div class="bg-red-500 text-white p-3 rounded">' + result.message + '</div>';
    }
});

document.getElementById('add-subfolder-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.action = 'add-subfolder';
    
    const response = await fetch('admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    
    const result = await response.json();
    const messageDiv = document.getElementById('subfolder-message');
    
    if (result.status === 'success') {
        messageDiv.innerHTML = '<div class="bg-green-500 text-white p-3 rounded">' + result.message + '</div>';
        this.reset();
        setTimeout(() => location.reload(), 1500);
    } else {
        messageDiv.innerHTML = '<div class="bg-red-500 text-white p-3 rounded">' + result.message + '</div>';
    }
});

document.getElementById('edit-subfolder-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.action = 'edit-subfolder';
    
    const response = await fetch('admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    
    const result = await response.json();
    const messageDiv = document.getElementById('edit-message');
    
    if (result.status === 'success') {
        messageDiv.innerHTML = '<div class="bg-green-500 text-white p-3 rounded">' + result.message + '</div>';
        setTimeout(() => window.location.href = 'admin.php?action=subfolder', 1500);
    } else {
        messageDiv.innerHTML = '<div class="bg-red-500 text-white p-3 rounded">' + result.message + '</div>';
    }
});

// Category dropdown handler
document.getElementById('post-select')?.addEventListener('change', async function() {
    const postName = this.value;
    const categorySelect = document.getElementById('category-select');
    
    categorySelect.innerHTML = '<option value="">-- Chọn phân loại --</option>';
    
    if (postName) {
        try {
            const response = await fetch(`admin.php?get_categories=${encodeURIComponent(postName)}`);
            const categories = await response.json();
            
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.name;
                option.textContent = category.name;
                categorySelect.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }
});

// Upload form handlers
document.getElementById('upload-post-select')?.addEventListener('change', async function() {
    const value = this.value;
    const subfolderSelect = document.getElementById('upload-subfolder-select');
    const postIdInput = document.getElementById('upload-post-id');
    
    subfolderSelect.innerHTML = '<option value="">-- Chọn sản phẩm --</option>';
    
    if (value) {
        const [postId, postName] = value.split('|');
        postIdInput.value = postId;
        
        try {
            const response = await fetch(`admin.php?get_subfolders=${encodeURIComponent(postName)}`);
            const subfolders = await response.json();
            
            subfolders.forEach(subfolder => {
                const option = document.createElement('option');
                option.value = subfolder.id;
                option.textContent = subfolder.name + ' - ' + subfolder.category;
                subfolderSelect.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading subfolders:', error);
        }
    }
});
</script>

<?php
// API endpoints for AJAX requests
if (isset($_GET['get_categories'])) {
    $post_name = $_GET['get_categories'];
    $stmt = $conn->prepare("SELECT * FROM category WHERE post = ? ORDER BY name");
    $stmt->bind_param("s", $post_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt->close();
    header('Content-Type: application/json');
    echo json_encode($categories);
    exit;
}

if (isset($_GET['get_subfolders'])) {
    $post_name = $_GET['get_subfolders'];
    $stmt = $conn->prepare("SELECT * FROM subfolder WHERE post = ? ORDER BY name");
    $stmt->bind_param("s", $post_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $subfolders = [];
    while ($row = $result->fetch_assoc()) {
        $subfolders[] = $row;
    }
    $stmt->close();
    header('Content-Type: application/json');
    echo json_encode($subfolders);
    exit;
}

require('system/foot.php');
?>