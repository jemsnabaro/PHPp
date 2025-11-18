<?php
include 'session.php';
require 'db.php';

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$currentUserId = $_SESSION['user_id'] ?? null;
if (!$currentUserId) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $_SESSION['user_id'] = $result['id'];
        $currentUserId = $result['id'];
    }
}

$profileUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($profileUserId <= 0) {
    die("User not specified.");
}

$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.email,
        u.created_at,
        p.full_name,
        p.bio,
        p.location,
        p.profile_image
    FROM users u
    LEFT JOIN profiles p ON p.user_id = u.id
    WHERE u.id = ?
");
$stmt->bind_param("i", $profileUserId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

if (!$profile) {
    die("User profile not found.");
}

$profile['full_name'] = $profile['full_name'] ?? 'SkillSwap Member';
$profile['bio'] = $profile['bio'] ?? 'This user has not added a bio yet.';
$profile['location'] = $profile['location'] ?? 'Location not specified';
$profile['profile_image'] = $profile['profile_image'] ?? 'https://via.placeholder.com/120';
$memberSince = $profile['created_at'] ? date("F j, Y", strtotime($profile['created_at'])) : 'Unknown';
$isOwnProfile = $currentUserId && $currentUserId === $profile['id'];

$stmt = $conn->prepare("
    SELECT 
        l.id,
        l.skill_offered,
        l.skill_wanted,
        l.description,
        l.created_at,
        sc.name AS category_name
    FROM skill_listings l
    JOIN skill_categories sc ON sc.id = l.category_id
    WHERE l.user_id = ?
    ORDER BY l.created_at DESC
");
$stmt->bind_param("i", $profileUserId);
$stmt->execute();
$listings = $stmt->get_result();
$hasListings = $listings->num_rows > 0;

$firstListingId = null;
if ($hasListings) {
    $listingsData = [];
    while ($row = $listings->fetch_assoc()) {
        if (!$firstListingId) {
            $firstListingId = $row['id'];
        }
        $listingsData[] = $row;
    }
} else {
    $listingsData = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile['full_name']); ?> - SkillSwap Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-arrow-left-right"></i> SkillSwap
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="browse.php">Browse Skills</a></li>
                    <li class="nav-item"><a class="nav-link" href="chat.php">Chat</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['email']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                            <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="bg-light border-bottom py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-3 text-center text-md-start mb-4 mb-md-0">
                    <img src="<?= htmlspecialchars($profile['profile_image']); ?>" alt="Profile picture"
                         class="rounded-circle border border-3 border-white shadow" width="140" height="140">
                </div>
                <div class="col-md-6">
                    <h2 class="mb-2"><?= htmlspecialchars($profile['full_name']); ?></h2>
                    <p class="mb-1 text-muted">
                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($profile['location']); ?>
                    </p>
                    <p class="mb-1 text-muted">
                        <i class="bi bi-envelope"></i> <?= htmlspecialchars($profile['email']); ?>
                    </p>
                    <small class="text-muted">Member since <?= htmlspecialchars($memberSince); ?></small>
                </div>
                <div class="col-md-3 text-center text-md-end">
                    <?php if ($isOwnProfile): ?>
                        <a href="profile.php" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit My Profile
                        </a>
                    <?php elseif ($hasListings): ?>
                        <a href="connect.php?listing_id=<?= $firstListingId; ?>" class="btn btn-primary">
                            <i class="bi bi-chat-dots"></i> Connect
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="container py-5">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">About</h5>
                        <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($profile['bio'])); ?></p>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Contact</h5>
                        <p class="mb-2"><i class="bi bi-envelope"></i> <?= htmlspecialchars($profile['email']); ?></p>
                        <p class="mb-2"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($profile['location']); ?></p>
                        <?php if (!$isOwnProfile && !$hasListings): ?>
                            <small class="text-muted d-block">This user has no public listings yet.</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Skill Listings</h4>
                    <?php if ($isOwnProfile): ?>
                        <a href="my-listings.php" class="btn btn-sm btn-outline-primary">Manage Listings</a>
                    <?php endif; ?>
                </div>

                <?php if ($hasListings): ?>
                    <?php foreach ($listingsData as $listing): ?>
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <span class="badge bg-primary mb-2"><?= htmlspecialchars($listing['category_name']); ?></span>
                                <h5 class="card-title mb-1"><?= htmlspecialchars($listing['skill_offered']); ?></h5>
                                <p class="text-muted mb-2">
                                    <strong>Wants to learn:</strong> <?= htmlspecialchars($listing['skill_wanted']); ?>
                                </p>
                                <p class="mb-3"><?= nl2br(htmlspecialchars($listing['description'])); ?></p>
                                <div class="d-flex flex-column flex-md-row gap-2">
                                    <a class="btn btn-outline-primary flex-fill"
                                       href="connect.php?listing_id=<?= $listing['id']; ?>">
                                        <i class="bi bi-chat-dots"></i> Connect
                                    </a>
                                    <a class="btn btn-outline-secondary flex-fill"
                                       href="listing-detail.php?id=<?= $listing['id']; ?>">
                                        <i class="bi bi-info-circle"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card shadow-sm">
                        <div class="card-body text-center text-muted py-5">
                            <i class="bi bi-clipboard-x fs-1 mb-3"></i>
                            <p class="mb-0">This user has not posted any listings yet.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

