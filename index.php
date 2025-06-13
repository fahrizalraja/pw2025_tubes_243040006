<?php
session_start();
include("config.php");

if(isset($_POST['submit'])) {
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = $_POST['password'];

    // Use proper column names from your database
    $stmt = mysqli_prepare($con, "SELECT id, Email, Username, Age, Password, is_admin FROM users WHERE Email = ?");
    
    if(!$stmt) {
        die("Error preparing statement: " . mysqli_error($con));
    }
    
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if(!$user) {
        $error = "Email not found";
    } else {
        if(password_verify($password, $user['Password'])) {
            // Login successful - set all session variables
            $_SESSION['valid'] = true;
            $_SESSION['id'] = $user['id'];
            $_SESSION['username'] = $user['Username'];
            $_SESSION['email'] = $user['Email'];
            $_SESSION['age'] = $user['Age'];
            
            // Set admin status based on database value
            $_SESSION['admin'] = ($user['is_admin'] == 1);
            
            header("Location: home.php");
            exit();
        } else {
            $error = "Incorrect password";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="box form-box">
            <header>Login</header>
            <?php if(isset($error)): ?>
                <div class='message error'><p><?php echo $error; ?></p></div>
            <?php endif; ?>
            
            <form action="" method="post">
                <div class="field input">
                    <label for="email">Email</label>
                    <input type="text" name="email" id="email" required>
                </div>
                
                <div class="field input">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                
                <div class="field">
                    <input type="submit" class="btn" name="submit" value="Login">
                </div>
                <div class="links">
                    Don't have an account? <a href="register.php">Sign Up Now</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>