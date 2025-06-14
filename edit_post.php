<?php
session_start();
include("config.php");

if(!isset($_SESSION['valid'])) {
    header("Location: index.php");
    exit();
}

if(!isset($_GET['id'])) {
    header("Location: mypost.php");
    exit();
}

$post_id = intval($_GET['id']);
$user_id = $_SESSION['id'];

$query = "SELECT * FROM user_uploads WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, "ii", $post_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$post = mysqli_fetch_assoc($result);

if(!$post) {
    $_SESSION['message'] = "Post not found or you don't have permission";
    header("Location: mypost.php");
    exit();
}

$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = mysqli_query($con, $categories_query);

$selected_cats_query = "SELECT category_id FROM upload_categories WHERE upload_id = ?";
$stmt_cats = mysqli_prepare($con, $selected_cats_query);
mysqli_stmt_bind_param($stmt_cats, "i", $post_id);
mysqli_stmt_execute($stmt_cats);
$selected_cats_result = mysqli_stmt_get_result($stmt_cats);
$selected_categories = [];
while($row = mysqli_fetch_assoc($selected_cats_result)) {
    $selected_categories[] = $row['category_id'];
}

if(isset($_POST['submit'])) {
    $title = mysqli_real_escape_string($con, $_POST['title']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    
    $update_query = "UPDATE user_uploads SET title = ?, description = ? WHERE id = ?";
    $stmt = mysqli_prepare($con, $update_query);
    mysqli_stmt_bind_param($stmt, "ssi", $title, $description, $post_id);
    mysqli_stmt_execute($stmt);
    
    $delete_query = "DELETE FROM upload_categories WHERE upload_id = ?";
    $stmt_del = mysqli_prepare($con, $delete_query);
    mysqli_stmt_bind_param($stmt_del, "i", $post_id);
    mysqli_stmt_execute($stmt_del);
    
    if(!empty($categories)) {
        foreach($categories as $cat_id) {
            $cat_id = intval($cat_id);
            if($cat_id > 0) {
                $insert_query = "INSERT INTO upload_categories (upload_id, category_id) VALUES (?, ?)";
                $stmt_ins = mysqli_prepare($con, $insert_query);
                mysqli_stmt_bind_param($stmt_ins, "ii", $post_id, $cat_id);
                mysqli_stmt_execute($stmt_ins);
            }
        }
    }
    
    $_SESSION['message'] = "Post updated successfully";
    header("Location: mypost.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post - Boaverse</title>
    <link rel="stylesheet" href="upload.css"> <!-- Gunakan style yang sama dengan upload form -->
    <link rel="Website icon" type="png" href="icon_web.png">
</head>
<body>
    <nav class="navbar">
    </nav>

    <div class="container">
        <h1>Edit Post</h1>
        
        <form action="edit_post.php?id=<?php echo $post_id; ?>" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" required 
                       value="<?php echo htmlspecialchars($post['title']); ?>">
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description"><?php echo htmlspecialchars($post['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="categories">Categories (Hold Ctrl to select multiple)</label>
                <select name="categories[]" id="categories" multiple>
                    <?php while($cat = mysqli_fetch_assoc($categories_result)): ?>
                        <option value="<?php echo $cat['id']; ?>"
                            <?php echo in_array($cat['id'], $selected_categories) ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Current File:</label>
                <?php if(strpos($post['file_path'], '.pdf') !== false): ?>
                    <p>PDF File: <?php echo htmlspecialchars($post['file_name']); ?></p>
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($post['file_path']); ?>" style="max-width: 300px; display: block; margin-bottom: 10px;">
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <input type="submit" class="btn" name="submit" value="Update Post">
                <a href="mypost.php" class="btn" style="background:rgb(255, 0, 34); margin-left: 10px;">Cancel</a>
            </div>
            <style>
                a {
                    text-decoration: none;
                }
            </style>
        </form>
    </div>
</body>
</html>