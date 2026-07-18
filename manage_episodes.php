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
$page = $_GET['page'] ?? 1; // Get current page for pagination
$search = $_GET['search'] ?? ''; // Get search term

// 2. Handle "Delete Episode"
if (isset($_GET['delete_episode'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM episodes WHERE id = ?");
        $stmt->execute([$_GET['delete_episode']]);
        $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Episode deleted successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error deleting episode: ' . $e->getMessage() . '</div>';
    }
}

// 3. NEW: Handle Bulk Tag Actions (Add OR Remove)
if (!empty($_POST['selected_episodes']) && !empty($_POST['bulk_tag_id'])) {
    $target_tag_id = $_POST['bulk_tag_id'];
    $selected_ids = $_POST['selected_episodes'];
    
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['apply_bulk_add'])) {
            // ADD TAG (Skip duplicates with INSERT IGNORE)
            $stmt = $pdo->prepare("INSERT IGNORE INTO episode_tags (episode_id, tag_id) VALUES (?, ?)");
            foreach ($selected_ids as $ep_id) {
                $stmt->execute([$ep_id, $target_tag_id]);
            }
            $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Tag successfully added to ' . count($selected_ids) . ' episodes.</div>';
        
        } elseif (isset($_POST['apply_bulk_remove'])) {
            // REMOVE TAG
            $stmt = $pdo->prepare("DELETE FROM episode_tags WHERE episode_id = ? AND tag_id = ?");
            foreach ($selected_ids as $ep_id) {
                $stmt->execute([$ep_id, $target_tag_id]);
            }
            $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Tag successfully removed from ' . count($selected_ids) . ' episodes.</div>';
        }
        
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error updating tags: ' . $e->getMessage() . '</div>';
    }
}

// 4. Fetch Tags for Bulk Dropdown
try {
    $all_tags = $pdo->query("SELECT id, name FROM tags ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_tags = [];
}

// 5. Pagination setup
$limit = 20; // 20 items per page
$offset = ($page - 1) * $limit;

try {
    // Build Search Logic
    $search_condition = "";
    $search_term = "";
    
    if (!empty($search)) {
        // Use unique placeholders because PDO emulation is off
        $search_condition = "WHERE ep.title LIKE :search1 OR s.title LIKE :search2";
        $search_term = '%' . $search . '%';
    }

    // 6. Query 1: Get the TOTAL count (filtered)
    $count_sql = "SELECT COUNT(*) FROM episodes AS ep JOIN series AS s ON ep.series_id = s.id $search_condition";
    $count_stmt = $pdo->prepare($count_sql);
    
    if (!empty($search)) {
        $count_stmt->bindValue(':search1', $search_term);
        $count_stmt->bindValue(':search2', $search_term);
    }
    
    $count_stmt->execute();
    $total_items = (int)$count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $limit);
    if ($total_pages < 1) $total_pages = 1;

    // 7. Query 2: Get the paginated EPISODES (filtered)
    $sql = "
        SELECT ep.id, ep.title, ep.publish_date, s.title AS series_title 
        FROM episodes AS ep
        JOIN series AS s ON ep.series_id = s.id
        $search_condition
        ORDER BY ep.publish_date DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $episode_list = $pdo->prepare($sql);
    
    if (!empty($search)) {
        $episode_list->bindValue(':search1', $search_term);
        $episode_list->bindValue(':search2', $search_term);
    }
    
    $episode_list->bindValue(':limit', $limit, PDO::PARAM_INT);
    $episode_list->bindValue(':offset', $offset, PDO::PARAM_INT);
    $episode_list->execute();

} catch (PDOException $e) {
    die("Could not fetch episodes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Episode Manager - il-hub.com</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-800 text-gray-200 antialiased p-8">

    <div class="max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">Episode Manager</h1>
            <a href="admin.php" class="text-sm text-orange-400 hover:text-orange-300">&larr; Back to Admin</a>
        </div>

        <?php if ($message) echo '<div class="mb-6">' . $message . '</div>'; ?>

        <div class="bg-gray-700 p-6 rounded-lg shadow-lg">
            
            <!-- Search Bar -->
            <div class="flex justify-between items-center mb-6 border-b border-gray-600 pb-4">
                <h2 class="text-2xl font-semibold text-white">All Episodes</h2>
                <form action="manage_episodes.php" method="GET" class="flex gap-2">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search title or series..." class="bg-gray-600 border-gray-500 rounded-md text-white text-sm px-3 py-2 focus:ring-orange-400 focus:border-orange-400">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium px-4 py-2 rounded-md transition-colors">
                        Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="manage_episodes.php" class="bg-gray-500 hover:bg-gray-400 text-white text-sm font-medium px-3 py-2 rounded-md transition-colors flex items-center">X</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Bulk Actions Form Wrapper -->
            <form action="manage_episodes.php?page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>" method="POST">
                
                <!-- Bulk Action Bar -->
                <div class="flex flex-col sm:flex-row items-center sm:space-x-3 space-y-2 sm:space-y-0 mb-4 bg-gray-800 p-3 rounded-md border border-gray-600">
                    <span class="text-sm font-medium text-gray-300">Bulk Action:</span>
                    
                    <!-- Tag Selector -->
                    <select name="bulk_tag_id" class="bg-gray-700 border-gray-500 text-white text-sm rounded-md px-3 py-1.5 focus:ring-orange-400 focus:border-orange-400 w-full sm:w-auto">
                        <option value="">-- Select Tag --</option>
                        <?php foreach ($all_tags as $tag): ?>
                            <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <!-- Action Buttons -->
                    <div class="flex space-x-2 w-full sm:w-auto">
                        <button type="submit" name="apply_bulk_add" class="bg-green-600 hover:bg-green-500 text-white text-sm font-medium px-4 py-1.5 rounded-md transition-colors flex-grow sm:flex-grow-0" onclick="return confirm('Add this tag to selected episodes?')">
                            Add Tag
                        </button>
                        <button type="submit" name="apply_bulk_remove" class="bg-red-600 hover:bg-red-500 text-white text-sm font-medium px-4 py-1.5 rounded-md transition-colors flex-grow sm:flex-grow-0" onclick="return confirm('Remove this tag from selected episodes?')">
                            Remove Tag
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-gray-600">
                                <!-- Select All Checkbox -->
                                <th class="py-2 pr-2 w-10">
                                    <input type="checkbox" id="select_all" class="rounded bg-gray-600 border-gray-500 text-orange-500 focus:ring-orange-400">
                                </th>
                                <th class="py-2 pr-4">Episode Title</th>
                                <th class="py-2 pr-4">Series</th>
                                <th class="py-2 pr-4">Published</th>
                                <th class="py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-600">
                            <?php if ($episode_list->rowCount() == 0): ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-gray-400">No episodes found.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($episode_list as $episode): ?>
                                <tr class="hover:bg-gray-600/50 transition-colors">
                                    <!-- Individual Row Checkbox -->
                                    <td class="py-3 pr-2">
                                        <input type="checkbox" name="selected_episodes[]" value="<?php echo $episode['id']; ?>" class="episode-checkbox rounded bg-gray-600 border-gray-500 text-orange-500 focus:ring-orange-400">
                                    </td>
                                    <td class="py-3 pr-4 font-medium">
                                        <?php echo htmlspecialchars($episode['title']); ?>
                                    </td>
                                    <td class="py-3 pr-4 text-gray-400"><?php echo htmlspecialchars($episode['series_title']); ?></td>
                                    <td class="py-3 pr-4 text-gray-400 text-sm whitespace-nowrap"><?php echo date('d M Y', strtotime($episode['publish_date'])); ?></td>
                                    <td class="py-3 flex space-x-2">
                                        <a href="edit_episode.php?id=<?php echo $episode['id']; ?>&page=<?php echo $page; ?>" class="text-sm bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded-md transition-colors">Edit</a>
                                        <a href="manage_episodes.php?delete_episode=<?php echo $episode['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>" class="text-sm bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md transition-colors" onclick="return confirm('Are you sure you want to delete this episode?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <!-- Pagination -->
            <div class="flex justify-between items-center text-sm mt-6">
                <!-- "Previous" Button -->
                <div>
                    <?php if ($page > 1): ?>
                        <a href="manage_episodes.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                            &larr; Previous
                        </a>
                    <?php else: ?>
                        <span class="bg-gray-600 text-gray-500 font-medium py-2 px-4 rounded-lg cursor-not-allowed">
                            &larr; Previous
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Page Info -->
                <div class="text-gray-400">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </div>

                <!-- "Next" Button -->
                <div>
                    <?php if ($page < $total_pages): ?>
                        <a href="manage_episodes.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                            Next &rarr;
                        </a>
                    <?php else: ?>
                        <span class="bg-gray-600 text-gray-500 font-medium py-2 px-4 rounded-lg cursor-not-allowed">
                            Next &rarr;
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Simple Script for "Select All" -->
    <script>
        document.getElementById('select_all').addEventListener('click', function(e) {
            const checkboxes = document.querySelectorAll('.episode-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
        });
    </script>
</body>
</html>