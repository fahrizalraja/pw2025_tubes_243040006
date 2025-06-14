<?php
session_start();
include("config.php");

if(!isset($_SESSION['valid'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['id'];

// Get user posts
$query = "SELECT u.*, GROUP_CONCAT(c.name SEPARATOR ', ') as category_names
          FROM user_uploads u
          LEFT JOIN upload_categories uc ON u.id = uc.upload_id
          LEFT JOIN categories c ON uc.category_id = c.id
          WHERE u.user_id = ?
          GROUP BY u.id
          ORDER BY u.upload_date DESC";

$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Delete function
if(isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $verify_query = "SELECT user_id, file_path FROM user_uploads WHERE id = ?";
    $verify_stmt = mysqli_prepare($con, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "i", $id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    $file_data = mysqli_fetch_assoc($verify_result);
    
    if($file_data && $file_data['user_id'] == $user_id) {
        if(file_exists($file_data['file_path'])) {
            unlink($file_data['file_path']);
        }
        
        $delete_query = "DELETE FROM user_uploads WHERE id = ?";
        $delete_stmt = mysqli_prepare($con, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "i", $id);
        mysqli_stmt_execute($delete_stmt);
        
        $_SESSION['message'] = "Post deleted successfully";
        header("Location: mypost.php");
        exit();
    } else {
        $_SESSION['message'] = "You don't have permission to delete this post";
        header("Location: mypost.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="mypost.css?v=<?php echo time(); ?>">
    <link rel="Website icon" type="png" href="icon_web.png">
    <title>My Posts - Boaverse</title>
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

    
    <!-- Main Content -->
    <div class="container">
        <div class="mypost-header">
            <h1>My Posts</h1>
            <p>Here are all the artworks you've uploaded</p>
        </div>
        
        <?php if(isset($_SESSION['message'])): ?>
            <div class="message <?php echo strpos($_SESSION['message'], 'successfully') !== false ? 'success' : 'error'; ?>">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(mysqli_num_rows($result) > 0): ?>
            <div class="gallery">
                <?php while($upload = mysqli_fetch_assoc($result)): ?>
                    <div class="card">
                        <div class="card-img" style="background-image: url('<?php echo htmlspecialchars($upload['file_path']); ?>')"></div>
                        <div class="card-body">
                            <h3 class="card-title"><?php echo htmlspecialchars($upload['title']); ?></h3>
                            <p class="card-text"><?php echo htmlspecialchars($upload['description']); ?></p>
                            
                            <?php if(!empty($upload['category_names'])): ?>
                                <p class="card-meta">
                                    Categories: <?php echo htmlspecialchars($upload['category_names']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="actions">
                                <a href="edit_post.php?id=<?php echo $upload['id']; ?>" class="btn btn-primary">Edit</a>
                                <form action="mypost.php" method="get" onsubmit="return confirm('Are you sure you want to delete this post?');">
                                    <input type="hidden" name="delete" value="<?php echo $upload['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-message">
                <p>You haven't uploaded any artworks yet.</p>
                <a href="upload.php" class="btn btn-primary" style="margin-top: 20px; display: inline-block;">Upload Your First Artwork</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer>
        <p>© <?php echo date("Y"); ?> BOAVERSE. All rights reserved.</p>
    </footer>

    <script>
    if(window.location.search.includes('profile_updated')) {
        const profilePic = document.getElementById('navbar-profile-pic');
        if(profilePic) {
            const newSrc = profilePic.src.split('?')[0] + '?v=' + Date.now();
            profilePic.src = newSrc;
            history.replaceState(null, null, window.location.pathname);
        }
    }
    </script>
</body>
</html>