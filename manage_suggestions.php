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

// 3. Handle "Delete" action
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM suggestions WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Suggestion deleted.</div>';
    } catch (PDOException $e) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: ' . $e->getMessage() . '</div>';
    }
}

// 4. Handle "Mark as Read" action
if (isset($_GET['mark_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE suggestions SET status = 'read' WHERE id = ?");
        $stmt->execute([$_GET['mark_read']]);
        $message = '<div class="bg-blue-600 p-4 rounded-lg text-white">Suggestion marked as read.</div>';
    } catch (PDOException $e) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: ' . $e->getMessage() . '</div>';
    }
}

// 5. Fetch all suggestions
try {
    $suggestions = $pdo->query("
        SELECT * FROM suggestions 
        ORDER BY status ASC, submitted_at DESC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Could not fetch suggestions: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suggestion Inbox - il-hub.com</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-800 text-gray-200 antialiased p-8">

    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">Suggestion Inbox</h1>
            <a href="admin.php" class="text-sm text-orange-400 hover:text-orange-300">&larr; Back to Admin</a>
        </div>

        <?php if ($message) echo '<div class="mb-6">' . $message . '</div>'; ?>

        <div class="bg-gray-700 rounded-lg shadow-lg">
            <ul class="divide-y divide-gray-600">
                <?php if (empty($suggestions)): ?>
                    <li class="p-6 text-gray-400">Your suggestion inbox is empty.</li>
                <?php endif; ?>
                
                <?php foreach ($suggestions as $s): ?>
                    <li class="p-6 <?php echo $s['status'] == 'new' ? 'bg-gray-700' : 'bg-gray-800 opacity-60'; ?>">
                        <div class="flex justify-between items-center mb-3">
                            <div>
                                <span class="font-semibold text-white">
                                    <?php echo !empty($s['contact_info']) ? htmlspecialchars($s['contact_info']) : 'Anonymous'; ?>
                                </span>
                                <?php if ($s['status'] == 'new'): ?>
                                    <span class="ml-2 bg-orange-500 text-white text-xs font-bold px-2 py-1 rounded-full">NEW</span>
                                <?php endif; ?>
                            </div>
                            <span class="text-sm text-gray-400"><?php echo date('d M Y, H:i', strtotime($s['submitted_at'])); ?></span>
                        </div>
                        <p class="text-gray-300 mb-4 whitespace-pre-wrap"><?php echo htmlspecialchars($s['suggestion_text']); ?></p>
                        <div class="flex space-x-2">
                            <?php if ($s['status'] == 'new'): ?>
                                <a href="manage_suggestions.php?mark_read=<?php echo $s['id']; ?>" class="text-sm bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded-md transition-colors">Mark as Read</a>
                            <?php endif; ?>
                            <a href="manage_suggestions.php?delete=<?php echo $s['id']; ?>" class="text-sm bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md transition-colors" onclick="return confirm('Are you sure you want to delete this?')">Delete</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</body>
</html>