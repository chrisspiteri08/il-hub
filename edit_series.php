<?php
session_start();
// 1. Check if the user is logged in.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// 2. Include database config
require_once 'db_config.php';

$message = '';
$series_id = $_GET['id'] ?? null;

if (!$series_id) {
    die('No series ID provided.');
}

// 3. Handle "Update Series" form submission
if (isset($_POST['update_series'])) {
    try {
        $stmt = $pdo->prepare("UPDATE series SET title = ?, description = ?, main_url = ?, series_image = ? WHERE id = ?");
        $stmt->execute([
            $_POST['title'],
            // $_POST['category'], // REMOVED
            $_POST['description'],
            $_POST['main_url'],
            $_POST['series_image'],
            $series_id
        ]);
        $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Series updated successfully!</div>';
    } catch (PDOException $e) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: ' . $e->getMessage() . '</div>';
    }
}

// 4. Fetch the existing series data to populate the form
try {
    $stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
    $stmt->execute([$series_id]);
    $series = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$series) {
        die('Series not found.');
    }
} catch (PDOException $e) {
    die("Could not fetch series: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Series - il-hub.com</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-800 text-gray-200 antialiased p-8">

    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">Edit Series</h1>
            <a href="admin.php" class="text-sm text-orange-400 hover:text-orange-300">&larr; Back to Admin</a>
        </div>

        <?php if ($message) echo $message; ?>

        <form action="edit_series.php?id=<?php echo $series_id; ?>" method="POST" class="bg-gray-700 p-6 rounded-lg shadow-lg space-y-4">
            
            <div>
                <label for="s_title" class="block text-sm font-medium text-gray-300">Series Title</label>
                <input type="text" name="title" id="s_title" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($series['title']); ?>">
            </div>

            <!-- REMOVED: Category field
            <div>
                <label for="s_category" class="block text-sm font-medium text-gray-300">Category</label>
                <select name="category" id="s_category" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50">
                    <option value="politics" <?php echo $series['category'] == 'politics' ? 'selected' : ''; ?>>Politics</option>
                    <option value="sports" <?php echo $series['category'] == 'sports' ? 'selected' : ''; ?>>Sports</option>
                    <option value="lifestyle" <?php echo $series['category'] == 'lifestyle' ? 'selected' : ''; ?>>Lifestyle</option>
                </select>
            </div>
            -->

            <div>
                <label for="s_desc" class="block text-sm font-medium text-gray-300">Description</label>
                <textarea name="description" id="s_desc" rows="3" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50"><?php echo htmlspecialchars($series['description']); ?></textarea>
            </div>

            <div>
                <label for="s_url" class="block text-sm font-medium text-gray-300">Main URL (Channel/Spotify page)</label>
                <input type="text" name="main_url" id="s_url" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($series['main_url']); ?>">
            </div>

            <div>
                <label for="s_img" class="block text-sm font-medium text-gray-300">Series Image URL (e.g., channel logo)</label>
                <input type="text" name="series_image" id="s_img" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($series['series_image']); ?>">
            </div>

            <button type="submit" name="update_series" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition-colors">
                Update Series
            </button>
        </form>
    </div>

</body>
</html>