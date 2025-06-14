<?php
session_start();
include("config.php");

if(!isset($_SESSION['valid'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if(isset($_POST['submit'])) {
    $username = mysqli_real_escape_string($con, $_POST['username']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $age = intval($_POST['age']);
    $id = $_SESSION['id'];
    
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if(!in_array($file_type, $allowed_types)) {
            $error = 'Only JPG, PNG, or GIF files are allowed.';
        } else {
            $max_size = 2 * 1024 * 1024;
            if($file['size'] > $max_size) {
                $error = 'Maximum file size is 2MB.';
            } else {
                $upload_dir = 'profile_pictures/';
                if(!is_dir($upload_dir)) {
                    if(!mkdir($upload_dir, 0755, true)) {
                        $error = 'Failed to create upload directory.';
                    }
                }
                
                $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = 'user_' . $id . '_' . uniqid() . '.' . $file_ext;
                $destination = $upload_dir . $new_filename;
                
                if(move_uploaded_file($file['tmp_name'], $destination)) {
                    $query = "SELECT profile_picture FROM users WHERE id = ?";
                    $stmt = mysqli_prepare($con, $query);
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $old_pic = mysqli_fetch_assoc($result)['profile_picture'];
                    
                    if($old_pic && file_exists($old_pic) && $old_pic != $destination) {
                        unlink($old_pic);
                    }
                    
                    $update_pic = "UPDATE users SET profile_picture = ?, Username = ?, Email = ?, Age = ? WHERE id = ?";
                    $stmt = mysqli_prepare($con, $update_pic);
                    mysqli_stmt_bind_param($stmt, "sssii", $destination, $username, $email, $age, $id);
                    
                    if(mysqli_stmt_execute($stmt)) {
                        // Update all session variables
                        $_SESSION['profile_picture'] = $destination;
                        $_SESSION['username'] = $username;
                        $_SESSION['email'] = $email;
                        $_SESSION['age'] = $age;
                        $_SESSION['profile_updated'] = time();
                        
                        // Redirect with force refresh
                        header("Location: home.php?profile_updated=".time());
                        exit();
                    } else {
                        $error = 'Failed to update profile: ' . mysqli_error($con);
                    }
                } else {
                    $error = 'Failed to upload profile picture.';
                }
            }
        }
    } else {
        $update_query = "UPDATE users SET Username = ?, Email = ?, Age = ? WHERE id = ?";
        $stmt = mysqli_prepare($con, $update_query);
        mysqli_stmt_bind_param($stmt, "ssii", $username, $email, $age, $id);
        
        if(mysqli_stmt_execute($stmt)) {
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['age'] = $age;
            $_SESSION['profile_updated'] = time();
            
            header("Location: home.php?profile_updated=".time());
            exit();
        } else {
            $error = 'Failed to update profile: ' . mysqli_error($con);
        }
    }
}

$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($con, $user_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Boaverse</title>
    <link rel="stylesheet" href="edit_pp.css">
          <link rel="Website icon" type="png" href="icon_web.png">

</head>
<body>
    <div class="profile-container">
        <header style="text-align: center; margin-bottom: 20px;">
            <h2>Edit Profile</h2>
        </header>
        
        <?php if($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form action="" method="post" enctype="multipart/form-data">
            <div style="text-align: center;">
                <img src="<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']).'?v='.time() : 'https://via.placeholder.com/150'; ?>" 
                     class="profile-picture" 
                     id="profile-preview">
                
                <div class="file-input-wrapper">
                    <label class="file-input-label">
                        Choose Profile Picture
                        <input type="file" name="profile_picture" id="profile_picture" accept="image/*" style="display: none;">
                    </label>
                    <p style="font-size: 12px; color: #666;">Formats: JPG, PNG, GIF (Max 2MB)</p>
                </div>
            </div>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" 
                       value="<?php echo htmlspecialchars($user['Username']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" 
                       value="<?php echo htmlspecialchars($user['Email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="age">Age</label>
                <input type="number" name="age" id="age" 
                       value="<?php echo htmlspecialchars($user['Age']); ?>" required min="13" max="120">
            </div>
            
            <button type="submit" name="submit" class="btn-submit">Save Changes</button>
        </form>
    </div>

    <script>
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.type.match('image.*')) {
                const reader = new FileReader();
                
                reader.onload = function(event) {
                    document.getElementById('profile-preview').src = event.target.result;
                };
                
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>