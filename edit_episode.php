<?php
session_start();

// --- NEW: Use config_loader ---
require_once 'config_loader.php';

// 1. Check if the user is logged in.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';
$episode_id = $_GET['id'] ?? null;
$page = $_GET['page'] ?? 1; // Get the page number to return to the right spot
$back_link = "manage_episodes.php?page=" . $page; // Create smart back link

if (!$episode_id) {
    die('No episode ID provided.');
}

// 3. Handle "Update Episode" form submission
if (isset($_POST['update_episode'])) {
    $tag_ids = $_POST['tag_ids'] ?? [];
    $wants_transcription = isset($_POST['do_transcribe']) ? 1 : 0;

    if (empty($tag_ids)) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: You must select at least one category tag.</div>';
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Update the main episode details
            $stmt = $pdo->prepare("UPDATE episodes SET series_id = ?, title = ?, description = ?, media_url = ?, thumbnail_url = ?, platform = ?, publish_date = ? WHERE id = ?");
            $stmt->execute([
                $_POST['series_id'],
                $_POST['title'],
                $_POST['description'],
                $_POST['media_url'],
                $_POST['thumbnail_url'],
                $_POST['platform'],
                empty($_POST['publish_date']) ? null : $_POST['publish_date'],
                $episode_id
            ]);

            // Update tags
            $del_stmt = $pdo->prepare("DELETE FROM episode_tags WHERE episode_id = ?");
            $del_stmt->execute([$episode_id]);

            $tag_stmt = $pdo->prepare("INSERT INTO episode_tags (episode_id, tag_id) VALUES (?, ?)");
            foreach ($tag_ids as $tag_id) {
                $tag_stmt->execute([$episode_id, $tag_id]);
            }

            // NEW: Logic for the transcription queue (Reset if requested)
            if ($wants_transcription) {
                $sql = "INSERT INTO transcriptions (episode_id, status, raw_transcript, clean_transcript, summary, social_posts) 
                        VALUES (?, 'pending', NULL, NULL, NULL, NULL)
                        ON DUPLICATE KEY UPDATE 
                        status='pending', 
                        raw_transcript=NULL, 
                        clean_transcript=NULL, 
                        summary=NULL, 
                        social_posts=NULL";
                $trans_stmt = $pdo->prepare($sql);
                $trans_stmt->execute([$episode_id]);
            }
            
            // Commit transaction
            $pdo->commit();

            $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Episode updated successfully! <a href="'.$back_link.'" class="font-bold underline">Go Back</a>.</div>';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// 4. Fetch the existing episode data
try {
    $stmt = $pdo->prepare("SELECT * FROM episodes WHERE id = ?");
    $stmt->execute([$episode_id]);
    $episode = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$episode) {
        die('Episode not found.');
    }
} catch (PDOException $e) {
    die("Could not fetch episode: " . $e->getMessage());
}

// 5. Fetch all data needed for dropdowns and checkboxes
try {
    $series_list = $pdo->query("SELECT id, title FROM series ORDER BY title ASC")->fetchAll();
    $tags_list = $pdo->query("SELECT id, name FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    $tag_stmt = $pdo->prepare("SELECT tag_id FROM episode_tags WHERE episode_id = ?");
    $tag_stmt->execute([$episode_id]);
    $current_tag_ids = $tag_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

} catch (PDOException $e) {
    die("Could not fetch data: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Episode - il-hub.com</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-800 text-gray-200 antialiased p-8">

    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">Edit Episode</h1>
            <a href="<?php echo $back_link; ?>" class="text-sm text-orange-400 hover:text-orange-300">&larr; Back to Episode Manager</a>
        </div>

        <?php if ($message) echo $message; ?>

        <form action="edit_episode.php?id=<?php echo $episode_id; ?>&page=<?php echo $page; ?>" method="POST" class="bg-gray-700 p-6 rounded-lg shadow-lg space-y-4">

            <!-- Series Dropdown -->
            <div>
                <label for="e_series" class="block text-sm font-medium text-gray-300">Series</label>
                <select name="series_id" id="e_series" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50">
                    <option value="">Select the series</option>
                    <?php foreach ($series_list as $series): ?>
                        <option value="<?php echo $series['id']; ?>" <?php echo $episode['series_id'] == $series['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($series['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Title -->
            <div>
                <label for="e_title" class="block text-sm font-medium text-gray-300">Episode Title</label>
                <input type="text" name="title" id="e_title" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($episode['title']); ?>">
            </div>

            <!-- Description -->
            <div>
                <label for="e_desc" class="block text-sm font-medium text-gray-300">Description</label>
                <textarea name="description" id="e_desc" rows="3" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50"><?php echo htmlspecialchars($episode['description']); ?></textarea>
            </div>

            <!-- Media URL -->
            <div>
                <label for="e_url" class="block text-sm font-medium text-gray-300">Media URL (YouTube/Spotify link)</label>
                <input type="text" name="media_url" id="e_url" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($episode['media_url']); ?>">
            </div>

            <!-- Thumbnail URL -->
            <div>
                <label for="e_img" class="block text-sm font-medium text-gray-300">Thumbnail URL (Video/Episode thumbnail)</label>
                <input type="text" name="thumbnail_url" id="e_img" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($episode['thumbnail_url']); ?>">
            </div>

            <!-- Platform -->
            <div>
                <label for="e_platform" class="block text-sm font-medium text-gray-300">Platform (e.g., YouTube, Spotify)</label>
                <input type="text" name="platform" id="e_platform" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($episode['platform']); ?>">
            </div>

            <!-- Category Tags -->
            <div>
                <label class="block text-sm font-medium text-gray-300">Category Tags</label>
                <div class="mt-2 space-y-2 bg-gray-600 p-4 rounded-md">
                    <?php foreach ($tags_list as $tag): ?>
                    <label class="flex items-center">
                        <input type="checkbox" name="tag_ids[]" value="<?php echo $tag['id']; ?>" class="rounded text-orange-500 focus:ring-orange-400" <?php echo in_array($tag['id'], $current_tag_ids) ? 'checked' : ''; ?>>
                        <span class="ml-2 text-white capitalize"><?php echo htmlspecialchars($tag['name']); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Publish Date Field -->
            <div>
                <label for="e_publish_date" class="block text-sm font-medium text-gray-300">Publish Date (Optional)</label>
                <input type="datetime-local" name="publish_date" id="e_publish_date" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" style="color-scheme: dark;" value="<?php echo !empty($episode['publish_date']) ? date('Y-m-d\TH:i', strtotime($episode['publish_date'])) : ''; ?>">
            </div>

            <!-- AI Transcription Option -->
            <div class="p-4 bg-indigo-900/30 border border-indigo-500/50 rounded-lg mt-4 shadow-inner">
                <label class="flex items-center space-x-3 cursor-pointer group">
                    <input type="checkbox" name="do_transcribe" value="1" class="w-5 h-5 rounded text-indigo-500 focus:ring-indigo-400 transition-all group-hover:scale-110">
                    <div>
                        <span class="text-white font-bold block">Re-run AI Transcript & Summary</span>
                        <span class="text-indigo-200 text-xs">Tick this to reset the process. Your MacBook will pick this up within 30 seconds.</span>
                    </div>
                </label>
            </div>

            <button type="submit" name="update_episode" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition-colors mt-4">
                Update Episode
            </button>
        </form>
    </div>

</body>
</html>