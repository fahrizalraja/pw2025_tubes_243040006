<?php
session_start();
include("config.php");


if($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Invalid CSRF token");
}

if(!isset($_SESSION['valid']) || !$_SESSION['admin']) {
    header("Location: index.php");
    exit();
}

// Cek apakah user adalah admin
if(!isset($_SESSION['valid']) || $_SESSION['username'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Fungsi hapus foto
if(isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Ambil info file sebelum dihapus
    $query = "SELECT file_path FROM user_uploads WHERE id = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $file = mysqli_fetch_assoc($result);
    
    if($file) {
        // Hapus file dari server
        if(file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        
        // Hapus dari database
        $delete = mysqli_prepare($con, "DELETE FROM user_uploads WHERE id = ?");
        mysqli_stmt_bind_param($delete, "i", $id);
        mysqli_stmt_execute($delete);
        
        $_SESSION['message'] = "Foto berhasil dihapus";
        header("Location: admin_dashboard.php");
        exit();
    }
}

// Fungsi edit foto
if(isset($_POST['edit'])) {
    $id = intval($_POST['id']);
    $title = mysqli_real_escape_string($con, $_POST['title']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    
    $update = mysqli_prepare($con, "UPDATE user_uploads SET title = ?, description = ? WHERE id = ?");
    mysqli_stmt_bind_param($update, "ssi", $title, $description, $id);
    mysqli_stmt_execute($update);
    
    $_SESSION['message'] = "Perubahan berhasil disimpan";
    header("Location: admin_dashboard.php");
    exit();
}

// Ambil semua data upload
$uploads = mysqli_query($con, "SELECT u.*, us.username 
                              FROM user_uploads u
                              JOIN users us ON u.user_id = us.Id
                              ORDER BY u.upload_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="stylesheet" href="admin_db.css">
    <title>Admin Dashboard</title>
  
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
        <div class="header">
            <h1>Admin Dashboard</h1>

        </div>

        <?php if(isset($_SESSION['message'])): ?>
            <div class="message success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>

        <div class="gallery">
            <?php while($upload = mysqli_fetch_assoc($uploads)): ?>
                <div class="card">
                    <div class="card-img" style="background-image: url('<?php echo $upload['file_path']; ?>');"></div>
                    <div class="card-body">
                        <h3 class="card-title"><?php echo htmlspecialchars($upload['title']); ?></h3>
                        <p class="card-text"><?php echo htmlspecialchars($upload['description']); ?></p>
                        <p class="card-meta">
                            Uploaded by <?php echo htmlspecialchars($upload['username']); ?> on <?php echo $upload['upload_date']; ?>
                        </p>
                        <div class="actions">
                            <button onclick="openEditModal(<?php echo $upload['id']; ?>, '<?php echo addslashes($upload['title']); ?>', '<?php echo addslashes($upload['description']); ?>')" 
                                    class="btn btn-primary">
                                Edit
                            </button>
                            <a href="admin_dashboard.php?delete=<?php echo $upload['id']; ?>" 
                               class="btn btn-danger" 
                               onclick="return confirm('Yakin ingin menghapus foto ini?')">
                                Delete
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Edit Foto</h2>
            <form method="POST" action="admin_dashboard.php">
                <input type="hidden" name="id" id="editId">
                <div style="margin-bottom: 15px;">
                    <label for="title">Judul</label>
                    <input type="text" name="title" id="editTitle" style="width: 100%; padding: 8px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label for="description">Deskripsi</label>
                    <textarea name="description" id="editDescription" style="width: 100%; padding: 8px; height: 100px;"></textarea>
                </div>
                <button type="submit" name="edit" class="btn btn-primary">Simpan</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-danger">Batal</button>
            </form>
        </div>
    </div>

    <script>
        // Fungsi modal
        function openEditModal(id, title, description) {
            document.getElementById('editId').value = id;
            document.getElementById('editTitle').value = title;
            document.getElementById('editDescription').value = description;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Tutup modal saat klik di luar
        window.onclick = function(event) {
            if (event.target == document.getElementById('editModal')) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>