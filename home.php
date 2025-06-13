<?php
session_start();
if (!isset($_SESSION['valid'])) {
    header("Location: index.php");
    exit();
}

include("config.php");

// Pagination settings
$itemsPerPage = 4;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$sortFilter = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Initialize filter variables
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
    if ($fileFilter === 'image') {
        $whereClauses[] = "(u.file_path LIKE '%.jpg%' OR u.file_path LIKE '%.png%' OR u.file_path LIKE '%.gif%')";
    } elseif ($fileFilter === 'pdf') {
        $whereClauses[] = "u.file_path LIKE '%.pdf%'";
    }
}

$whereClause = '';
if (!empty($whereClauses)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Determine sorting order
$orderBy = 'u.upload_date DESC'; // Default
switch ($sortFilter) {
    case 'oldest':
        $orderBy = 'u.upload_date ASC';
        break;
    case 'a-z':
        $orderBy = 'u.title ASC';
        break;
    case 'z-a':
        $orderBy = 'u.title DESC';
        break;
    case 'popular':
        $orderBy = 'u.views DESC';
        break;
}

// Get total count for pagination
$countQuery = "SELECT COUNT(DISTINCT u.id) as total 
              FROM user_uploads u
              JOIN users us ON u.user_id = us.id
              LEFT JOIN upload_categories uc ON u.id = uc.upload_id
              LEFT JOIN categories c ON uc.category_id = c.id
              $whereClause";

$countStmt = mysqli_prepare($con, $countQuery);
if (!empty($params)) {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
}
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalItems = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalItems / $itemsPerPage);

// Adjust current page if it's beyond total pages
if ($currentPage > $totalPages && $totalPages > 0) {
    $currentPage = $totalPages;
}

// Main query with pagination
$offset = ($currentPage - 1) * $itemsPerPage;
$query = "SELECT u.*, us.Username, us.profile_picture,
          GROUP_CONCAT(c.name SEPARATOR ', ') as category_names,
          GROUP_CONCAT(c.slug SEPARATOR ',') as category_slugs
          FROM user_uploads u
          JOIN users us ON u.user_id = us.id
          LEFT JOIN upload_categories uc ON u.id = uc.upload_id
          LEFT JOIN categories c ON uc.category_id = c.id
          $whereClause
          GROUP BY u.id
          ORDER BY $orderBy 
          LIMIT ?, ?";

$paramsWithLimit = $params;
$paramsWithLimit[] = $offset;
$paramsWithLimit[] = $itemsPerPage;
$typesWithLimit = $types . 'ii';

$stmt = mysqli_prepare($con, $query);
if (!empty($paramsWithLimit)) {
    mysqli_stmt_bind_param($stmt, $typesWithLimit, ...$paramsWithLimit);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch profile pictures for all users in current page projects
$profilePictures = [];
$userIds = [];
mysqli_data_seek($result, 0);
while ($row = mysqli_fetch_assoc($result)) {
    $userIds[] = $row['user_id'];
}
if (!empty($userIds)) {
    $userIdsPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
    $typesUsers = str_repeat('i', count($userIds));
    $profileQuery = "SELECT id, profile_picture FROM users WHERE id IN ($userIdsPlaceholders)";
    $profileStmt = mysqli_prepare($con, $profileQuery);
    mysqli_stmt_bind_param($profileStmt, $typesUsers, ...$userIds);
    mysqli_stmt_execute($profileStmt);
    $profileResult = mysqli_stmt_get_result($profileStmt);
    while ($profileRow = mysqli_fetch_assoc($profileResult)) {
        if (!empty($profileRow['profile_picture'])) {
            $profilePictures[$profileRow['id']] = $profileRow['profile_picture'];
        }
    }
}

// Reset result pointer for projects to re-fetch for display
mysqli_data_seek($result, 0);

// Get all categories for the category filter
$categoriesQuery = "SELECT * FROM categories ORDER BY name";
$categoriesResult = mysqli_query($con, $categoriesQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="home.css" />
    <link rel="icon" type="image/png" href="icon_web.png" />
    <title>Boaverse</title>
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

    <main class="container" role="main">
        <header class="header">
            <h1 tabindex="0">Welcome to the Best Of Art Universe</h1>
            <p tabindex="0">Showcase your creative work with the world</p>
        </header>

        <section class="categories-container" aria-label="Categories">
            <h2>Categories</h2>
            <div class="categories-list" role="list">
                <a href="home.php" class="category-tag<?php echo empty($categoryFilter) ? ' active' : ''; ?>" role="listitem" aria-current="<?php echo empty($categoryFilter) ? 'true' : 'false'; ?>">All</a>
                <?php while ($category = mysqli_fetch_assoc($categoriesResult)): ?>
                    <a href="home.php?category=<?php echo urlencode($category['slug']); ?>" 
                       class="category-tag<?php echo ($categoryFilter === $category['slug']) ? ' active' : ''; ?>" 
                       role="listitem" aria-current="<?php echo ($categoryFilter === $category['slug']) ? 'true' : 'false'; ?>">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </section>

        <section class="search-container" aria-label="Search projects">
            <form method="get" action="">
                <input type="text" name="search" placeholder="Search projects..." value="<?php echo htmlspecialchars($searchKeyword); ?>" aria-label="Search projects">
                <select name="sort" id="sort" onchange="this.form.submit()" aria-label="Sort projects by">
                    <option value="newest" <?php echo ($sortFilter === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo ($sortFilter === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="a-z" <?php echo ($sortFilter === 'a-z') ? 'selected' : ''; ?>>A-Z</option>
                    <option value="z-a" <?php echo ($sortFilter === 'z-a') ? 'selected' : ''; ?>>Z-A</option>
                </select>
                <?php if (!empty($categoryFilter)): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryFilter); ?>" />
                <?php endif; ?>
                <button type="submit" class="search-btn">Search</button>
            </form>
        </section>

        <?php if (!empty($searchKeyword) || !empty($fileFilter)): ?>
            <section class="search-results-info" aria-live="polite" aria-atomic="true">
                <p>Showing results for: 
                    <?php 
                    if (!empty($searchKeyword)) echo "Search: '" . htmlspecialchars($searchKeyword) . "'";
                    if (!empty($searchKeyword) && !empty($fileFilter)) echo " and ";
                    if (!empty($fileFilter)) echo "Filter: " . htmlspecialchars(ucfirst($fileFilter));
                    ?>
                </p>
            </section>
        <?php endif; ?>

        <section class="projects-grid" aria-label="Projects">
            <?php while ($upload = mysqli_fetch_assoc($result)): ?>
                <article class="project-card" tabindex="0" role="article" aria-labelledby="title-<?php echo $upload['id']; ?>">
                    <?php if (strpos($upload['file_path'], '.pdf') === false): ?>
                        <img src="<?php echo htmlspecialchars($upload['file_path']); ?>" class="project-image" alt="<?php echo htmlspecialchars($upload['title']); ?>" />
                    <?php else: ?>
                        <div class="project-pdf-icon" style="height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                            <span style="font-size: 48px;" aria-hidden="true">&#128196;</span>
                            <span class="sr-only"><?php echo htmlspecialchars($upload['title']); ?> PDF Document</span>
                        </div>
                    <?php endif; ?>
                    <div class="project-info">
                        <h3 id="title-<?php echo $upload['id']; ?>" class="project-title"><?php echo htmlspecialchars($upload['title']); ?></h3>
                        <p><?php echo htmlspecialchars($upload['description']); ?></p>

                        <?php if (!empty($upload['category_names'])): ?>
                            <div class="project-categories" role="list" aria-label="Categories">
                                <?php 
                                $category_names = explode(', ', $upload['category_names']);
                                $category_slugs = explode(',', $upload['category_slugs']);
                                foreach ($category_names as $index => $category_name): ?>
                                    <span class="project-category" 
                                          role="listitem"
                                          tabindex="0"
                                          onclick="window.location.href='home.php?category=<?php echo urlencode($category_slugs[$index]); ?>'"
                                          onkeypress="if(event.key==='Enter'){window.location.href='home.php?category=<?php echo urlencode($category_slugs[$index]); ?>';}" 
                                          aria-label="Filter category <?php echo htmlspecialchars($category_name); ?>">
                                        <?php echo htmlspecialchars($category_name); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="project-author" aria-label="Project author">
                            <div class="author-avatar">
                                <?php if (!empty($profilePictures[$upload['user_id']])): ?>
                                    <img src="<?php echo htmlspecialchars($profilePictures[$upload['user_id']]); ?>" 
                                         alt="<?php echo htmlspecialchars($upload['Username']); ?>'s avatar" 
                                         class="author-avatar-img">
                                <?php else: ?>
                                    <div class="author-avatar-initial"><?php echo strtoupper(substr($upload['Username'], 0, 1)); ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="author-name"><?php echo htmlspecialchars($upload['Username']); ?></span>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </section>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="pagination" role="navigation" aria-label="Pagination Navigation">
                <!-- First page -->
                <?php if ($currentPage > 1): ?>
                    <a href="?<?php 
                        echo http_build_query(array_merge($_GET, ['page' => 1])); 
                    ?>" class="pagination-link pagination-first" aria-label="First page">« First</a>
                <?php else: ?>
                    <span class="pagination-link pagination-first disabled" aria-disabled="true" tabindex="-1">« First</span>
                <?php endif; ?>

                <!-- Previous -->
                <?php if ($currentPage > 1): ?>
                    <a href="?<?php 
                        echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); 
                    ?>" class="pagination-link pagination-prev" rel="prev" title="Previous Page" aria-label="Previous page">&laquo;</a>
                <?php else: ?>
                    <span class="pagination-link pagination-prev disabled" aria-disabled="true" tabindex="-1">&laquo;</span>
                <?php endif; ?>

                <?php
                // Show limited page numbers with ellipsis
                $start = max(1, $currentPage - 2);
                $end = min($totalPages, $currentPage + 2);

                if ($start > 1) {
                    ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-link" aria-label="Page 1">1</a>
                    <?php 
                    if ($start > 2) echo '<span class="pagination-dots" aria-hidden="true">...</span>';
                }

                for ($i = $start; $i <= $end; $i++): 
                    if ($i == $currentPage): ?>
                        <span class="pagination-link current" aria-current="page" tabindex="0"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php 
                            echo http_build_query(array_merge($_GET, ['page' => $i])); 
                        ?>" class="pagination-link" aria-label="Page <?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif;
                endfor;

                if ($end < $totalPages) {
                    if ($end < $totalPages - 1) echo '<span class="pagination-dots" aria-hidden="true">...</span>';
                    ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" class="pagination-link" aria-label="Page <?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                <?php
                }
                ?>

                <!-- Next -->
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?<?php 
                        echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); 
                    ?>" class="pagination-link pagination-next" rel="next" title="Next Page" aria-label="Next page">&raquo;</a>
                <?php else: ?>
                    <span class="pagination-link pagination-next disabled" aria-disabled="true" tabindex="-1">&raquo;</span>
                <?php endif; ?>

                <!-- Last page -->
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?<?php 
                        echo http_build_query(array_merge($_GET, ['page' => $totalPages])); 
                    ?>" class="pagination-link pagination-last" aria-label="Last page">Last »</a>
                <?php else: ?>
                    <span class="pagination-link pagination-last disabled" aria-disabled="true" tabindex="-1">Last »</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    </main>

    <footer role="contentinfo">
        <p>© <?php echo date("Y"); ?> BOAVERSE. All rights reserved.</p>
    </footer>

    <script>
    // Force refresh profile picture when coming from profile edit
    if (window.location.search.includes('profile_updated=1')) {
        const profilePic = document.getElementById('navbar-profile-pic');
        if (profilePic) {
            const newSrc = profilePic.src.split('?')[0] + '?v=' + Date.now();
            profilePic.src = newSrc;
            history.replaceState(null, null, window.location.pathname);
        }
    }
    </script>
</body>
</html>

