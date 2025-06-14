<?php
session_start();
include("config.php");

if(isset($_POST['submit'])) {
    $username = mysqli_real_escape_string($con, $_POST['username']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $age = mysqli_real_escape_string($con, $_POST['age']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if(empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        echo "<div class='message'><p>Semua field harus diisi!</p></div>";
    } elseif($password !== $confirm_password) {
        echo "<div class='message'><p>Password tidak cocok!</p></div>";
    } else {
        $check_email = mysqli_query($con, "SELECT Email FROM users WHERE Email='$email'");
        
        if(mysqli_num_rows($check_email) > 0) {
            echo "<div class='message'><p>Email sudah terdaftar!</p></div>";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (Username, Email, Age, Password) VALUES ('$username', '$email', '$age', '$hashed_password')";
            $result = mysqli_query($con, $query);
            
            if($result) {
                $_SESSION['valid'] = $email;
                $_SESSION['username'] = $username;
                $_SESSION['age'] = $age;
                $_SESSION['id'] = mysqli_insert_id($con);
                
                header("Location: home.php");
                exit();
            } else {
                echo "<div class='message'><p>Pendaftaran gagal: ".mysqli_error($con)."</p></div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="style.css">
          <link rel="Website icon" type="png" href="icon_web.png">

</head>
<body>
    <div class="container">
        <div class="box form-box">
            <header>Daftar Akun</header>
            <form action="" method="post">
                <div class="field input">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" required>
                </div>
                
                <div class="field input">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" required>
                </div>
                
                <div class="field input">
                    <label for="age">Umur</label>
                    <input type="number" name="age" id="age" required>
                </div>
                
                <div class="field input">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                
                <div class="field input">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                
                <div class="field">
                    <input type="submit" class="btn" name="submit" value="Daftar">
                </div>
                <div class="links">
                    Sudah punya akun? <a href="index.php">Login Sekarang</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>