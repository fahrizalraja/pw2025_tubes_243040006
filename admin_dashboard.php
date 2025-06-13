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

// Pagination settings
$per_page = 6; // Number of items per page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

// Get total number of uploads
$count_query = "SELECT COUNT(*) as total FROM user_uploads";
$count_result = mysqli_query($con, $count_query);
$total_items = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_items / $per_page);

// Calculate offset
$offset = ($page - 1) * $per_page;

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
        header("Location: admin_dashboard.php?page=".$page);
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
    header("Location: admin_dashboard.php?page=".$page);
    exit();
}

// Ambil data upload dengan pagination
$uploads = mysqli_query($con, "SELECT u.*, us.username 
                              FROM user_uploads u
                              JOIN users us ON u.user_id = us.Id
                              ORDER BY u.upload_date DESC
                              LIMIT $per_page OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="stylesheet" href="admin_db.css">
         <link rel="Website icon" type="png" href="icon_web.png">
    <title>Admin Dashboard</title>
</head>
<body>
     <!-- Navbar -->
    <nav class="navbar" role="navigation" aria-label="Primary Navigation">
        <div class="logo" tabindex="0">Boaverse</div>
        <div class="nav-links">
            <a href="home.php" class="nav-link">Home</a>
            <a href="upload.php" class="nav-link">Upload</a>
            <a href="mypost.php" class="nav-link">My post</a>
      <a href="contact_me.php" class="nav-link">Contact me</a>
<?php if (isset($_SESSION['admin']) && $_SESSION['admin']): ?>
                <a href="admin_dashboard.php" class="nav-link">Admin Dashboard</a>
            <?php endif; ?>
        </div>
        <div class="user-section">
            <div class="user-avatar" tabindex="0" aria-label="User profile picture">
                <?php
                $cacheBuster = isset($_SESSION['profile_updated']) ? '?force=' . $_SESSION['profile_updated'] : '?force=' . time();

                if (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) {
                    echo '<img src="' . htmlspecialchars($_SESSION['profile_picture']) . $cacheBuster . '" alt="Profile picture" class="profile-avatar" id="navbar-profile-pic" src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/c88657c3-dd7b-4d5a-92dd-559893106352.png">';
                } else {
                    echo '<div class="avatar-initial" aria-label="User initial">' . strtoupper(substr($_SESSION['username'], 0, 1)) . '</div>';
                }
                ?>
            </div>
            <a href="edit_profile.php" class="edit-btn">Edit profile</a>
            <form action="logout.php" method="post" style="margin: 0;">
                <button type="submit" class="logout-btn" aria-label="Logout">Logout</button>
            </form>
        </div>
    </nav>
    <!-- navbar end -->

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
                            <a href="admin_dashboard.php?delete=<?php echo $upload['id']; ?>&page=<?php echo $page; ?>" 
                               class="btn btn-danger" 
                               onclick="return confirm('Yakin ingin menghapus foto ini?')">
                                Delete
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="admin_dashboard.php?page=<?php echo $page - 1; ?>" class="btn">&laquo; Previous</a>
            <?php endif; ?>
            
            <?php 
            // Show page numbers
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            if ($start > 1) {
                echo '<a href="admin_dashboard.php?page=1" class="btn">1</a>';
                if ($start > 2) echo '<span class="btn disabled">...</span>';
            }
            
            for ($i = $start; $i <= $end; $i++): 
                if ($i == $page): ?>
                    <a href="admin_dashboard.php?page=<?php echo $i; ?>" class="btn active"><?php echo $i; ?></a>
                <?php else: ?>
                    <a href="admin_dashboard.php?page=<?php echo $i; ?>" class="btn"><?php echo $i; ?></a>
                <?php endif; 
            endfor; 
            
            if ($end < $total_pages) {
                if ($end < $total_pages - 1) echo '<span class="btn disabled">...</span>';
                echo '<a href="admin_dashboard.php?page='.$total_pages.'" class="btn">'.$total_pages.'</a>';
            }
            ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="admin_dashboard.php?page=<?php echo $page + 1; ?>" class="btn">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Edit Foto</h2>
            <form method="POST" action="admin_dashboard.php">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="page" value="<?php echo $page; ?>">
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