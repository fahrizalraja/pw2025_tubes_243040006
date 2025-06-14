<?php
$con = mysqli_connect("localhost", "root", "", "pw2025_tubes_243040006");

if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

mysqli_set_charset($con, "utf8mb4");

date_default_timezone_set('Asia/Jakarta');
?>