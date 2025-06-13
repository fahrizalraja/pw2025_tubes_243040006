<?php
session_start();

if(!isset($_SESSION['valid'])) {
    header("Location: index.php");
    exit();
}

include("config.php");

$error = '';
$success = '';

if(isset($_POST['submit'])) {
    $title = mysqli_real_escape_string($con, $_POST['title']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    $user_id = $_SESSION['id'];
    
    // Validasi file
    if(isset($_FILES['uploaded_file'])) {
        $file = $_FILES['uploaded_file'];
        
        // Cek error
        if($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Error uploading file.';
        } else {
            // Validasi tipe file
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            if(!in_array($file['type'], $allowed_types)) {
                $error = 'Hanya file JPG, PNG, GIF, atau PDF yang diizinkan.';
            } else {
                // Validasi rasio gambar hanya untuk file gambar
                if(strpos($file['type'], 'image') === 0) {
                    $minRatio = 4/5; // Minimum ratio (contoh: 4:5)
                    $maxRatio = 16/9; // Maximum ratio (contoh: 16:9)
                    
                    list($width, $height) = getimagesize($file['tmp_name']);
                    $ratio = $width / $height;
                    
                    if($ratio < $minRatio || $ratio > $maxRatio) {
                        $error = "Image ratio must be between 4:5 and 16:9";
                    }
                }
                
                if(empty($error)) {
                    // Validasi ukuran file (max 5MB)
                    $max_size = 5 * 1024 * 1024; // 5MB
                    if($file['size'] > $max_size) {
                        $error = 'Ukuran file maksimal 5MB.';
                    } else {
                        // Buat direktori upload jika belum ada
                        $upload_dir = 'uploads/';
                        if(!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Generate nama file unik
                        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $new_filename = uniqid() . '.' . $file_ext;
                        $destination = $upload_dir . $new_filename;
                        
                        // Pindahkan file ke folder upload
                        if(move_uploaded_file($file['tmp_name'], $destination)) {
                            // Resize gambar hanya untuk file gambar
                            if(strpos($file['type'], 'image') === 0) {
                                $targetWidth = 800; // Lebar maksimum
                                $targetHeight = 600; // Tinggi maksimum
                                
                                list($width, $height, $type) = getimagesize($destination);
                                
                                switch($type) {
                                    case IMAGETYPE_JPEG:
                                        $image = imagecreatefromjpeg($destination);
                                        break;
                                    case IMAGETYPE_PNG:
                                        $image = imagecreatefrompng($destination);
                                        break;
                                    case IMAGETYPE_GIF:
                                        $image = imagecreatefromgif($destination);
                                        break;
                                    default:
                                        // Hapus file jika bukan gambar yang valid
                                        unlink($destination);
                                        die("Invalid image type");
                                }
                                
                                // Hitung rasio baru
                                $ratio = min($targetWidth/$width, $targetHeight/$height);
                                $newWidth = (int)($width * $ratio);
                                $newHeight = (int)($height * $ratio);
                                
                                // Buat canvas baru
                                $newImage = imagecreatetruecolor($newWidth, $newHeight);
                                
                                // Preserve transparency untuk PNG/GIF
                                if($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
                                    imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
                                    imagealphablending($newImage, false);
                                    imagesavealpha($newImage, true);
                                }
                                
                                // Resize gambar
                                imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                                
                                // Simpan gambar yang sudah diresize
                                switch($type) {
                                    case IMAGETYPE_JPEG:
                                        imagejpeg($newImage, $destination, 90); // 90% quality
                                        break;
                                    case IMAGETYPE_PNG:
                                        imagepng($newImage, $destination, 9); // 9 = maximum compression
                                        break;
                                    case IMAGETYPE_GIF:
                                        imagegif($newImage, $destination);
                                        break;
                                }
                                
                                // Bersihkan memory
                                imagedestroy($image);
                                imagedestroy($newImage);
                            }
                            
                            // Simpan ke database
                            $query = "INSERT INTO user_uploads (user_id, file_name, file_path, title, description) 
                                     VALUES (?, ?, ?, ?, ?)";
                            $stmt = mysqli_prepare($con, $query);
                            mysqli_stmt_bind_param($stmt, "issss", $user_id, $file['name'], $destination, $title, $description);
                            
                            if(mysqli_stmt_execute($stmt)) {
                                $upload_id = mysqli_insert_id($con);
                                $success = 'File berhasil diupload!';
                                
                                // Simpan kategori setelah upload berhasil
                                if(isset($_POST['categories'])) {
                                    foreach($_POST['categories'] as $category_id) {
                                        $insertCat = "INSERT INTO upload_categories (upload_id, category_id) VALUES (?, ?)";
                                        $stmtCat = mysqli_prepare($con, $insertCat);
                                        mysqli_stmt_bind_param($stmtCat, "ii", $upload_id, $category_id);
                                        mysqli_stmt_execute($stmtCat);
                                        mysqli_stmt_close($stmtCat);
                                    }
                                }
                            } else {
                                $error = 'Gagal menyimpan data: ' . mysqli_error($con);
                                // Hapus file yang sudah diupload jika gagal menyimpan ke database
                                unlink($destination);
                            }
                            
                            mysqli_stmt_close($stmt);
                        } else {
                            $error = 'Gagal memindahkan file.';
                        }
                    }
                }
            }
        }
    } else {
        $error = 'Silakan pilih file.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File</title>
    <link rel="stylesheet" href="upload.css">
          <link rel="Website icon" type="png" href="icon_web.png">

</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo">Boaverse</div>
        
        <div class="nav-links">
            <a href="home.php">Home</a>
            <a href="upload.php">Upload</a> 
            <a href="#">Popular art</a>
            <a href="#">About</a>
            <?php if(isset($_SESSION['admin']) && $_SESSION['admin']): ?>
                <a href="admin_dashboard.php" class="active">Admin Dashboard</a>
            <?php endif; ?>
        </div>
        
        <div class="user-section">
            <div class="user-avatar">
                <?php
                $cacheBuster = isset($_SESSION['profile_updated']) ? '?force='.$_SESSION['profile_updated'] : '?force='.time();
                
                if(isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) {
                    echo '<img src="'.htmlspecialchars($_SESSION['profile_picture']).$cacheBuster.'" 
                         alt="Profile" 
                         class="profile-avatar"
                         id="navbar-profile-pic">';
                } else {
                    echo '<div class="avatar-initial">'.substr($_SESSION['username'], 0, 1).'</div>';
                }
                ?>
            </div>
            <a href="edit_profile.php" class="edit-btn">Edit profile</a>
            <form action="logout.php" method="post">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </nav>
    <!-- End Navbar -->
    <div class="container">
        <h1>Upload Karya Anda</h1>
        
        <?php if($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Judul Karya</label>
                <input type="text" name="title" id="title" required>
            </div>
            
            <div class="form-group">
                <label for="description">Deskripsi</label>
                <textarea name="description" id="description"></textarea>
            </div>

            <div class="field input">
                <label for="categories">Categories (Hold Ctrl to select multiple)</label>
                <select name="categories[]" id="categories" multiple>
                    <?php
                    $categories = mysqli_query($con, "SELECT * FROM categories ORDER BY name");
                    while($cat = mysqli_fetch_assoc($categories)) {
                        echo '<option value="'.$cat['id'].'">'.$cat['name'].'</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="uploaded_file">Pilih File (JPG, PNG, GIF, PDF - max 5MB)</label>
                <input type="file" name="uploaded_file" id="uploaded_file" required>
            </div>
            
            <div class="form-group">
                <input type="submit" class="btn" name="submit" value="Upload">
            </div>
        </form>
    </div>
</body>
</html>