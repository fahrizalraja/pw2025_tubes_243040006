<?php
session_start();
if(!isset($_SESSION['valid'])) {
    header("Location: index.php");
    exit();
}

include("config.php");

$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';

// Initialize filter variables before use
$whereClauses = [];
$params = [];
$types = '';

// Handle search and filter functionality
$searchKeyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$fileFilter = isset($_GET['filter']) ? $_GET['filter'] : '';

if (!empty($categoryFilter)) {
    $whereClauses[] = "uc.category_id = (SELECT id FROM categories WHERE slug = ?)";
    $params[] = $categoryFilter;
    $types .= 's';
}

if (!empty($searchKeyword)) {
    $whereClauses[] = "(u.title LIKE ? OR u.description LIKE ? OR us.Username LIKE ?)";
    $searchTerm = "%$searchKeyword%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if (!empty($fileFilter)) {
    if ($fileFilter == 'image') {
        $whereClauses[] = "(u.file_path LIKE '%.jpg%' OR u.file_path LIKE '%.png%' OR u.file_path LIKE '%.gif%')";
    } elseif ($fileFilter == 'pdf') {
        $whereClauses[] = "u.file_path LIKE '%.pdf%'";
    }
}

$whereClause = '';
if (!empty($whereClauses)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereClauses);
}

$query = "SELECT u.*, us.Username, 
          GROUP_CONCAT(c.name SEPARATOR ', ') as category_names,
          GROUP_CONCAT(c.slug SEPARATOR ',') as category_slugs
          FROM user_uploads u
          JOIN users us ON u.user_id = us.id
          LEFT JOIN upload_categories uc ON u.id = uc.upload_id
          LEFT JOIN categories c ON uc.category_id = c.id
          $whereClause
          GROUP BY u.id
          ORDER BY u.upload_date DESC LIMIT 6";

$stmt = mysqli_prepare($con, $query);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get all categories for the category filter
$categoriesQuery = "SELECT * FROM categories ORDER BY name";
$categoriesResult = mysqli_query($con, $categoriesQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="home.css">
    <title>Boaverse</title>
    <style>
        .categories-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }
        .category-tag {
            display: inline-block;
            padding: 5px 15px;
            background-color: #f0f0f0;
            border-radius: 20px;
            text-decoration: none;
            color: #333;
            transition: all 0.2s;
        }
        .category-tag:hover, .category-tag.active {
            background-color: #007bff;
            color: white;
        }
        .project-card {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .project-card:hover {
            transform: translateY(-5px);
        }
        .project-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .project-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 10px;
        }
        .project-category {
            background-color: rgba(0,123,255,0.1);
            color: #007bff;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .project-category:hover {
            background-color: rgba(0,123,255,0.2);
        }
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .project-info {
            padding: 15px;
        }
    </style>
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
    
    <!-- Main Content -->
    <div class="container">
        <div class="header">
            <h1>Welcome to the Best Of Art Universe</h1>
            <p>Showcase your creative work with the world</p>
        </div>

        <div class="categories-container">
            <h3>Categories</h3>
            <div class="categories-list">
                <a href="home.php" class="category-tag<?php echo empty($categoryFilter) ? ' active' : ''; ?>">All</a>
                <?php while($category = mysqli_fetch_assoc($categoriesResult)): ?>
                    <a href="home.php?category=<?php echo $category['slug']; ?>" 
                       class="category-tag<?php echo ($categoryFilter == $category['slug']) ? ' active' : ''; ?>">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
        
        <!-- Search Form -->
        <div class="search-container">
            <form method="get" action="">
                <input type="text" name="search" placeholder="Search projects..." value="<?php echo htmlspecialchars($searchKeyword); ?>">
                <select name="filter">
                    <option value="">All Types</option>
                    <option value="image" <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'image' ? 'selected' : ''); ?>>Images</option>
                    <option value="pdf" <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'pdf' ? 'selected' : ''); ?>>PDFs</option>
                </select>
                <button type="submit" class="search-btn">Search</button>
                <?php if(!empty($categoryFilter)): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryFilter); ?>">
                <?php endif; ?>
            </form>
        </div>
        
        <?php if(!empty($searchKeyword) || !empty($fileFilter)): ?>
            <div class="search-results-info">
                <p>Showing results for: 
                <?php 
                if (!empty($searchKeyword)) echo "Search: '" . htmlspecialchars($searchKeyword) . "'";
                if (!empty($searchKeyword) && !empty($fileFilter)) echo " and ";
                if (!empty($fileFilter)) echo "Filter: " . htmlspecialchars(ucfirst($fileFilter));
                ?>
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Projects Grid -->
        <div class="projects-grid">
            <?php while($upload = mysqli_fetch_assoc($result)): ?>
                <div class="project-card">
                    <?php if(strpos($upload['file_path'], '.pdf') === false): ?>
                        <img src="<?php echo htmlspecialchars($upload['file_path']); ?>" class="project-image" alt="<?php echo htmlspecialchars($upload['title']); ?>">
                    <?php else: ?>
                        <div style="height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                            <span style="font-size: 48px;">📄</span>
                        </div>
                    <?php endif; ?>
                    <div class="project-info">
                        <h3 class="project-title"><?php echo htmlspecialchars($upload['title']); ?></h3>
                        <p><?php echo htmlspecialchars($upload['description']); ?></p>
                        
                        <?php if(!empty($upload['category_names'])): ?>
                            <div class="project-categories">
                                <?php 
                                $category_names = explode(', ', $upload['category_names']);
                                $category_slugs = explode(',', $upload['category_slugs']);
                                foreach($category_names as $index => $category_name): 
                                ?>
                                    <span class="project-category" 
                                          onclick="window.location.href='home.php?category=<?php echo htmlspecialchars($category_slugs[$index]); ?>'">
                                        <?php echo htmlspecialchars($category_name); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="project-author">
                            <div class="author-avatar"><?php echo substr($upload['Username'], 0, 1); ?></div>
                            <span class="author-name"><?php echo htmlspecialchars($upload['Username']); ?></span>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    
    <!-- Footer -->
    <footer>
        <p>© <?php echo date("Y"); ?> BOAVERSE. All rights reserved.</p>
    </footer>

    <script>
    // Force refresh image when coming from profile edit
    if(window.location.search.includes('profile_updated=1')) {
        const profilePic = document.getElementById('navbar-profile-pic');
        if(profilePic) {
            // Add timestamp to bypass cache
            const newSrc = profilePic.src.split('?')[0] + '?v=' + Date.now();
            profilePic.src = newSrc;
            
            // Clean URL without reload
            history.replaceState(null, null, window.location.pathname);
        }
    }
    </script>
</body>
</html>