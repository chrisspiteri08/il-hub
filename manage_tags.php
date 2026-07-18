<?php
session_start();

// --- NEW: Use config_loader ---
require_once 'config_loader.php';

// Helper for Maltese translation
function translateTag($tag) {
    $map = [
        'politics' => 'Politika',
        'sports' => 'Sports',
        'lifestyle' => 'Lifestyle',
        'comedy' => 'Kummiedja',
        'children' => 'Tfal',
        'music' => 'Mużika minn Malta' // FIXED: Traduzzjoni aħjar
    ];
    return $map[strtolower($tag)] ?? ucfirst($tag);
}

// 1. Check if the user is logged in.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';

// 3. Handle "Add Tag" form
if (isset($_POST['add_tag'])) {
    $new_tag_name = strtolower(trim($_POST['tag_name']));
    $display_order = (int)$_POST['display_order'];

    if (!empty($new_tag_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tags (name, display_order) VALUES (?, ?)");
            $stmt->execute([$new_tag_name, $display_order]);
            $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Tag "' . htmlspecialchars($new_tag_name) . '" added successfully!</div>';
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: Tag "' . htmlspecialchars($new_tag_name) . '" already exists.</div>';
            } else {
                $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: ' . $e->getMessage() . '</div>';
            }
        }
    } else {
        $message = '<div class="bg-yellow-500 p-4 rounded-lg text-yellow-900">Tag name cannot be empty.</div>';
    }
}

// 4. Handle "Delete Tag"
if (isset($_GET['delete_tag'])) {
    $tag_id = $_GET['delete_tag'];
    try {
        // We must delete from 'episode_tags' first due to foreign key constraints
        $stmt = $pdo->prepare("DELETE FROM episode_tags WHERE tag_id = ?");
        $stmt->execute([$tag_id]);
        
        // Now delete from 'tags'
        $stmt = $pdo->prepare("DELETE FROM tags WHERE id = ?");
        $stmt->execute([$tag_id]);
        
        $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Tag deleted successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error deleting tag: ' . $e->getMessage() . '</div>';
    }
}

// 5. NEW: Handle "Update Order" form
if (isset($_POST['update_order'])) {
    $orders = $_POST['orders'] ?? [];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE tags SET display_order = ? WHERE id = ?");
        foreach ($orders as $tag_id => $order) {
            $stmt->execute([(int)$order, $tag_id]);
        }
        $pdo->commit();
        $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Tag display order updated successfully!</div>';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error updating order: ' . $e->getMessage() . '</div>';
    }
}

// 6. Fetch all tags for the list, sorted by the new display_order
try {
    $tags_list = $pdo->query("SELECT * FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Could not fetch tags: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tag Manager - il-hub.com</title>
    <!-- --- FIXED: USING UNPKG CDN --- -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background-color: #1f2937; color: white; }</style>
</head>
<body class="bg-gray-800 text-gray-200 antialiased p-8">

    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">Tag Manager</h1>
            <a href="admin.php" class="text-sm text-orange-400 hover:text-orange-300">&larr; Back to Admin</a>
        </div>

        <?php if ($message) echo '<div class="mb-6">' . $message . '</div>'; ?>

        <!-- Add New Tag Form -->
        <form action="manage_tags.php" method="POST" class="bg-gray-700 p-6 rounded-lg shadow-lg space-y-4 mb-8">
            <h2 class="text-2xl font-semibold text-white mb-4 border-b border-gray-600 pb-2">Add New Tag</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label for="tag_name" class="block text-sm font-medium text-gray-300">New Tag Name</label>
                    <input type="text" name="tag_name" id="tag_name" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" placeholder="e.g., music">
                </div>
                <div>
                    <label for="display_order" class="block text-sm font-medium text-gray-300">Order</label>
                    <input type="number" name="display_order" id="display_order" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="99">
                </div>
            </div>
            <button type="submit" name="add_tag" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition-colors">
                Add Tag
            </button>
        </form>

        <!-- Manage Existing Tags List -->
        <form action="manage_tags.php" method="POST" class="bg-gray-700 p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-semibold text-white mb-4 border-b border-gray-600 pb-2">Current Tags</h2>
            <ul class="space-y-3 mb-6">
                <li class="flex justify-between items-center text-sm text-gray-400 px-3">
                    <span>Tag Name</span>
                    <span>Order</span>
                </li>
                <?php if (empty($tags_list)): ?>
                    <li class="text-gray-400 px-3">No tags found.</li>
                <?php endif; ?>
                <?php foreach ($tags_list as $tag): ?>
                    <li class="flex justify-between items-center bg-gray-600 p-3 rounded-md">
                        <span class="font-medium capitalize"><?php echo translateTag($tag['name']); ?></span>
                        <div class="flex items-center space-x-2">
                            <input type="number" name="orders[<?php echo $tag['id']; ?>]" class="w-16 bg-gray-500 rounded-md text-white text-center" value="<?php echo $tag['display_order']; ?>">
                            <a href="manage_tags.php?delete_tag=<?php echo $tag['id']; ?>" class="text-sm bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md transition-colors" onclick="return confirm('Are you sure you want to delete this tag? This is permanent.')">Delete</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <button type="submit" name="update_order" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition-colors">
                Update Order
            </button>
        </form>
    </div>
</body>
</html>