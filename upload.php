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
}
    if(empty($title)) {
        $error = 'Judul tidak boleh kosong';
    } elseif(empty($_FILES['uploaded_file']['name'])) {
        $error = 'Silakan pilih file.';
    } else {
        $file = $_FILES['uploaded_file'];
        
        if($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Error uploading file. Code: ' . $file['error'];
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $file_type = mime_content_type($file['tmp_name']);
            
            if(!in_array($file_type, $allowed_types)) {
                $error = 'Hanya file JPG, PNG, GIF, atau PDF yang diizinkan.';
            } else {
                if(strpos($file_type, 'image') === 0) {
                    list($width, $height) = getimagesize($file['tmp_name']);
                    if($width === 0 || $height === 0) {
                        $error = 'Gambar tidak valid atau rusak.';
                    } else {
                        $minRatio = 4/5; 
                        $maxRatio = 16/9;
                        $ratio = $width / $height;
                        
                        if($ratio < $minRatio || $ratio > $maxRatio) {
                            $error = "Rasio gambar harus antara 4:5 dan 16:9";
                        }
                    }
                }
                
                if(empty($error)) {
                    $max_size = 5 * 1024 * 1024; // 5MB
                    if($file['size'] > $max_size) {
                        $error = 'Ukuran file maksimal 5MB.';
                    } else {
                        $upload_dir = 'uploads/';
                        if(!is_dir($upload_dir)) {
                            if(!mkdir($upload_dir, 0755, true)) {
                                $error = 'Gagal membuat direktori upload.';
                            }
                        }
                        
                        if(empty($error)) {
                            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                            $new_filename = uniqid() . '.' . $file_ext;
                            $destination = $upload_dir . $new_filename;
                            
                            if(move_uploaded_file($file['tmp_name'], $destination)) {
                                if(strpos($file_type, 'image') === 0) {
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
                                            unlink($destination);
                                            $error = "Tipe gambar tidak valid";
                                            break;
                                    }
                                    
                                    if(empty($error)) {
                                        $ratio = min($targetWidth/$width, $targetHeight/$height);
                                        $newWidth = (int)($width * $ratio);
                                        $newHeight = (int)($height * $ratio);
                                        
                                        $newImage = imagecreatetruecolor($newWidth, $newHeight);
                                        
                                        if($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
                                            imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
                                            imagealphablending($newImage, false);
                                            imagesavealpha($newImage, true);
                                        }
                                        
                                        if(!imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
                                            $error = "Gagal melakukan resize gambar";
                                        } else {
                                            switch($type) {
                                                case IMAGETYPE_JPEG:
                                                    if(!imagejpeg($newImage, $destination, 90)) {
                                                        $error = "Gagal menyimpan gambar JPEG";
                                                    }
                                                    break;
                                                case IMAGETYPE_PNG:
                                                    if(!imagepng($newImage, $destination, 9)) {
                                                        $error = "Gagal menyimpan gambar PNG";
                                                    }
                                                    break;
                                                case IMAGETYPE_GIF:
                                                    if(!imagegif($newImage, $destination)) {
                                                        $error = "Gagal menyimpan gambar GIF";
                                                    }
                                                    break;
                                            }
                                        }
                                        
                                        imagedestroy($image);
                                        imagedestroy($newImage);
                                    }
                                }
                                
                                if(empty($error)) {
                                    $query = "INSERT INTO user_uploads (user_id, file_name, file_path, title, description) 
                                             VALUES (?, ?, ?, ?, ?)";
                                    $stmt = mysqli_prepare($con, $query);
                                    if(!$stmt) {
                                        $error = 'Gagal mempersiapkan statement: ' . mysqli_error($con);
                                        unlink($destination);
                                    } else {
                                        $original_filename = $file['name'];
                                        mysqli_stmt_bind_param($stmt, "issss", $user_id, $original_filename, $destination, $title, $description);
                                        
                                        if(!mysqli_stmt_execute($stmt)) {
                                            $error = 'Gagal menyimpan data: ' . mysqli_error($con);
                                            unlink($destination);
                                        } else {
                                            $upload_id = mysqli_insert_id($con);
                                            $success = 'File berhasil diupload!';
                                            
                                            if(isset($_POST['categories']) && is_array($_POST['categories'])) {
                                                foreach($_POST['categories'] as $category_id) {
                                                    $category_id = (int)$category_id;
                                                    if($category_id > 0) {
                                                        $insertCat = "INSERT INTO upload_categories (upload_id, category_id) VALUES (?, ?)";
                                                        $stmtCat = mysqli_prepare($con, $insertCat);
                                                        if($stmtCat) {
                                                            mysqli_stmt_bind_param($stmtCat, "ii", $upload_id, $category_id);
                                                            if(!mysqli_stmt_execute($stmtCat)) {
                                                                $error = 'Gagal menyimpan kategori: ' . mysqli_error($con);
                                                            }
                                                            mysqli_stmt_close($stmtCat);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        mysqli_stmt_close($stmt);
                                    }
                                }
                            } else {
                                $error = 'Gagal memindahkan file.';
                            }
                        }
                    }
                }
            }
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
            <a href="upload.php" class="active">Upload</a> 
            <a href="mypost.php">My post</a>
      <a href="contact_me.php" class="nav-link">Contact me</a>
       <?php if(isset($_SESSION['admin']) && $_SESSION['admin']): ?>
                <a href="admin_dashboard.php">Admin Dashboard</a>
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
        <h1>Upload Your Artworks</h1>
        
        <?php if($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" required value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>

            <div class="field input">
                <label for="categories">Categories (Hold Ctrl to select multiple)</label>
                <select name="categories[]" id="categories" multiple>
                    <?php
                    $categories = mysqli_query($con, "SELECT * FROM categories ORDER BY name");
                    if($categories) {
                        while($cat = mysqli_fetch_assoc($categories)) {
                            echo '<option value="'.$cat['id'].'"';
                            if(isset($_POST['categories']) && in_array($cat['id'], $_POST['categories'])) {
                                echo ' selected';
                            }
                            echo '>'.$cat['name'].'</option>';
                        }
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