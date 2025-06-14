<?php
session_start();
if (!isset($_SESSION['valid'])) {
    header("Location: index.php");
    exit();
}

include("config.php");

$messageSent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $subject = mysqli_real_escape_string($con, $_POST['subject']);
    $message = mysqli_real_escape_string($con, $_POST['message']);
    $userId = $_SESSION['id'];
    
    $query = "INSERT INTO contact_messages (user_id, name, email, subject, message, created_at) 
              VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "issss", $userId, $name, $email, $subject, $message);
    
    if (mysqli_stmt_execute($stmt)) {
        $messageSent = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="contact_me.css?v=<?php echo time(); ?>">
    <link rel="Website icon" type="png" href="icon_web.png">
    <title>Contact Us - Boaverse</title>
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
    
    <main class="contact-container">
        <h1>Contact Us</h1>
        <p>Have questions or suggestions? Send us a message!</p>
        
        <?php if ($messageSent): ?>
            <div class="alert success">
                <p>Your message has been sent successfully!</p>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="contact-form">
            <div class="form-group">
                <label for="name">Your Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" required>
            </div>
            
            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" rows="6" required></textarea>
            </div>
            
            <button type="submit" class="submit-btn">Send Message</button>
        </form>
    </main>
</body>
</html>