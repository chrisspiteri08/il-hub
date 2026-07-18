<?php
session_start();

// --- NEW: Use config_loader ---
// This file includes db_config.php AND populates the $settings array
require_once 'config_loader.php';

// 1. Check if the user is logged in.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';

// 3. Handle "Add Series" form submission
if (isset($_POST['add_series'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO series (title, description, main_url, series_image) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['main_url'],
            $_POST['series_image']
        ]);
        $message = '<div class="bg-green-600 p-4 rounded-lg text-white">New series added successfully!</div>';
    } catch (PDOException $e) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: ' . $e->getMessage() . '</div>';
    }
}

// 4. Handle "Add Episode" form submission
if (isset($_POST['add_episode'])) {
    $tag_ids = $_POST['tag_ids'] ?? [];
    if (empty($tag_ids) || empty($_POST['series_id'])) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: You must select a Series and at least one Category Tag.</div>';
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO episodes (series_id, title, description, media_url, thumbnail_url, platform, publish_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['series_id'],
                $_POST['title'],
                $_POST['description'],
                $_POST['media_url'],
                $_POST['thumbnail_url'], // Use NULL if empty
                $_POST['platform'],
                empty($_POST['publish_date']) ? null : $_POST['publish_date'] // Handle empty date
            ]);
            $new_episode_id = $pdo->lastInsertId();

            // Insert tags into the junction table
            $tag_stmt = $pdo->prepare("INSERT INTO episode_tags (episode_id, tag_id) VALUES (?, ?)");
            foreach ($tag_ids as $tag_id) {
                $tag_stmt->execute([$new_episode_id, $tag_id]);
            }
            
            // Commit transaction
            $pdo->commit();
            $message = '<div class="bg-green-600 p-4 rounded-lg text-white">New episode added successfully!</div>';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// 5. Handle "Delete Series"
if (isset($_GET['delete_series'])) {
    $series_id = $_GET['delete_series'];
    
    // Check if this series has any episodes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM episodes WHERE series_id = ?");
    $stmt->execute([$series_id]);
    $episode_count = $stmt->fetchColumn();

    if ($episode_count > 0) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Cannot delete series: It still has ' . $episode_count . ' episodes linked to it. Delete them first from the Episode Manager.</div>';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM series WHERE id = ?");
            $stmt->execute([$series_id]);
            $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Series deleted successfully.</div>';
        } catch (PDOException $e) {
            $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error deleting series: ' . $e->getMessage() . '</div>';
        }
    }
}


// 6. Fetch all series for dropdown AND management list
try {
    $series_list = $pdo->query("SELECT id, title FROM series ORDER BY title ASC")->fetchAll();
    // --- NEW: FETCH TAGS DYNAMICALLY ---
    $tags_list = $pdo->query("SELECT id, name FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    // --- NEW: FETCH UNREAD SUGGESTION COUNT ---
    $unread_count = $pdo->query("SELECT COUNT(*) FROM suggestions WHERE status = 'new'")->fetchColumn();
    // --- NEW: FETCH TOTAL EPISODE COUNT ---
    $total_episodes = $pdo->query("SELECT COUNT(*) FROM episodes")->fetchColumn();
} catch (PDOException $e) {
    die("Could not fetch data: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - il-hub.com</title>
    <!-- --- FIXED: USING UNPKG CDN --- -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background-color: #1f2937; color: white; }</style>
</head>
<body class="bg-gray-800 text-gray-200 antialiased p-4 md:p-8">

    <div class="max-w-7xl mx-auto">
        <!-- Header Row -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 border-b border-gray-700 pb-6">
            <div>
                <h1 class="text-3xl font-bold text-white"><span class="text-orange-400">il-hub</span>.com Admin</h1>
                <!-- NEW: Total Episode Count -->
                <p class="text-sm text-gray-400 mt-1">Total Episodes: <span class="text-white font-bold"><?php echo number_format($total_episodes); ?></span></p>
            </div>
            
            <!-- Navigation Buttons -->
            <div class="flex flex-wrap gap-2">
                <a href="settings.php" class="text-sm bg-gray-600 hover:bg-gray-500 text-white py-2 px-3 rounded-md transition-colors flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0l-.1.41a1.5 1.5 0 01-2.1 1.44l-.4-.16c-1.5-.6-3.13.8-2.52 2.26l.24.58a1.5 1.5 0 01-1.28 2.1l-.43.04c-1.6.16-1.6 2.54 0 2.7l.43.04a1.5 1.5 0 011.28 2.1l-.24.58c-.6 1.46.9 2.87 2.52 2.26l.4-.16a1.5 1.5 0 012.1 1.44l.1.41c.38 1.56 2.6 1.56 2.98 0l.1-.41a1.5 1.5 0 012.1-1.44l.4.16c1.6.6 3.13-.8 2.52-2.26l-.24-.58a1.5 1.5 0 011.28-2.1l.43-.04c1.6-.16 1.6-2.54 0-2.7l-.43-.04a1.5 1.5 0 01-1.28-2.1l.24-.58c.6-1.46-.9-2.87-2.52-2.26l-.4.16a1.5 1.5 0 01-2.1-1.44l-.1-.41zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                    </svg>
                    Settings
                </a>
                <a href="manage_suggestions.php" class="relative text-sm bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-3 rounded-md transition-colors">
                    Suggestions
                    <?php if ($unread_count > 0): ?>
                        <span class="absolute -top-2 -right-2 flex h-5 w-5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-5 w-5 bg-red-500 text-white text-xs items-center justify-center"><?php echo $unread_count; ?></span>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="logs.php" class="text-sm bg-gray-600 hover:bg-gray-500 text-white py-2 px-3 rounded-md transition-colors">Cron Logs</a>
                <a href="manage_tags.php" class="text-sm bg-purple-600 hover:bg-purple-700 text-white py-2 px-3 rounded-md transition-colors">Tag Manager</a>
                <a href="automations.php" class="text-sm bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 rounded-md transition-colors">Auto-Imports</a>
                <a href="add_from_url.php" class="text-sm bg-teal-500 hover:bg-teal-600 text-white py-2 px-3 rounded-md transition-colors">Smart Add (URL)</a>
                <a href="importer.php" class="text-sm bg-green-600 hover:bg-green-700 text-white py-2 px-3 rounded-md transition-colors">YT Importer</a>
                <a href="logout.php" class="text-sm text-orange-400 hover:text-orange-300 py-2 px-3">Log Out &rarr;</a>
            </div>
        </div>

        <!-- Display feedback message -->
        <?php if ($message): ?>
            <div class="mb-6"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- "Add" Forms Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
            <!-- Add New Series Form -->
            <form action="admin.php" method="POST" class="bg-gray-700 p-6 rounded-lg shadow-lg space-y-4">
                <h2 class="text-2xl font-semibold text-white mb-4 border-b border-gray-600 pb-2">Add New Series</h2>
                <div>
                    <label for="s_title" class="block text-sm font-medium text-gray-300">Series Title</label>
                    <input type="text" name="title" id="s_title" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50">
                </div>
                <div>
                    <label for="s_desc" class="block text-sm font-medium text-gray-300">Description</label>
                    <textarea name="description" id="s_desc" rows="3" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50"></textarea>
                </div>
                <div>
                    <label for="s_url" class="block text-sm font-medium text-gray-300">Main URL (Channel/Spotify page)</label>
                    <input type="text" name="main_url" id="s_url" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50">
                </div>
                <div>
                    <label for="s_img" class="block text-sm font-medium text-gray-300">Series Image URL (e.g., channel logo)</label>
                    <input type="text" name="series_image" id="s_img" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50">
                </div>
                <button type="submit" name="add_series" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition-colors">
                    Add Series
                </button>
            </form>

            <!-- Add New Episode Form -->
            <form action="admin.php" method="POST" class="bg-gray-700 p-6 rounded-lg shadow-lg space-y-4">
                <h2 class="text-2xl font-semibold text-white mb-4 border-b border-gray-600 pb-2">Add New Episode (Manual)</h2>
                <div>
                    <label for="e_series" class="block text-sm font-medium text-gray-300">Series</label>
                    <select name="series_id" id="e_series" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50">
                        <option value="">Select the series</option>
                        <?php foreach ($series_list as $series): ?>
                            <option value="<?php echo $series['id']; ?>"><?php echo htmlspecialchars($series['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="e_title" class="block text-sm font-medium text-gray-300">Episode Title</label>
                    <input type="text" name="title" id="e_title" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50">
                </div>
                <div>
                    <label for="e_desc" class="block text-sm font-medium text-gray-300">Description</label>
                    <textarea name="description" id="e_desc" rows="3" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50"></textarea>
                </div>
                <div>
                    <label for="e_url" class="block text-sm font-medium text-gray-300">Media URL (YouTube/Spotify link)</label>
                    <input type="text" name="media_url" id="e_url" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50">
                </div>
                <div>
                    <label for="e_img" class="block text-sm font-medium text-gray-300">Thumbnail URL (Video/Episode thumbnail)</label>
                    <input type="text" name="thumbnail_url" id="e_img" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50">
                </div>
                <div>
                    <label for="e_platform" class="block text-sm font-medium text-gray-300">Platform (e.g., YouTube, Spotify)</label>
                    <input type="text" name="platform" id="e_platform" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50">
                </div>

                <!-- --- NEW: DYNAMIC CATEGORY CHECKBOXES --- -->
                <div>
                    <label class="block text-sm font-medium text-gray-300">Category Tags</label>
                    <div class="mt-2 space-y-2 bg-gray-600 p-4 rounded-md max-h-40 overflow-y-auto">
                        <?php foreach ($tags_list as $tag): ?>
                        <label class="flex items-center">
                            <input type="checkbox" name="tag_ids[]" value="<?php echo $tag['id']; ?>" class="rounded text-orange-500 focus:ring-orange-400">
                            <span class="ml-2 text-white capitalize"><?php echo htmlspecialchars($tag['name']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- --- END DYNAMIC CHECKBOXES --- -->

                <!-- Publish Date Field -->
                <div>
                    <label for="e_publish_date" class="block text-sm font-medium text-gray-300">Publish Date (Optional)</label>
                    <input type="datetime-local" name="publish_date" id="e_publish_date" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" style="color-scheme: dark;">
                </div>

                <button type="submit" name="add_episode" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition-colors mt-4">
                    Add Episode
                </button>
            </form>
        </div>

        <!-- "Manage" Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Manage Episodes -->
            <div class="bg-gray-700 p-6 rounded-lg shadow-lg">
                <h2 class="text-2xl font-semibold text-white mb-4 border-b border-gray-600 pb-2">Manage Episodes</h2>
                <p class="text-gray-300 mb-4">Click the button below to edit, delete, or manage all episodes on the site.</p>
                <a href="manage_episodes.php" class="w-full text-center inline-block bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition-colors">
                    Go to Episode Manager &rarr;
                </a>
            </div>

            <!-- Manage Series -->
            <div class="bg-gray-700 p-6 rounded-lg shadow-lg">
                <h2 class="text-2xl font-semibold text-white mb-4 border-b border-gray-600 pb-2">Manage Series</h2>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach ($series_list as $series): ?>
                        <div class="flex justify-between items-center bg-gray-600 p-3 rounded-md">
                            <span class="font-medium"><?php echo htmlspecialchars($series['title']); ?></span>
                            <div class="flex space-x-2">
                                <a href="edit_series.php?id=<?php echo $series['id']; ?>" class="text-sm bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded-md transition-colors">Edit</a>
                                <a href="admin.php?delete_series=<?php echo $series['id']; ?>" class="text-sm bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md transition-colors" onclick="return confirm('Are you sure you want to delete this series? This is permanent and can only be done if no episodes are attached.')">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</body>
</html>