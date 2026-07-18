<?php
session_start();

// --- NEW: Use config_loader ---
require_once 'config_loader.php';

// 1. Check if the user is logged in.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Fetch all logs, newest first
try {
    $logs = $pdo->query("
        SELECT * FROM cron_logs 
        ORDER BY run_time DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Could not fetch logs: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cron Job Logs - il-hub.com</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-800 text-gray-200 antialiased p-8">

    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">Cron Job Logs</h1>
            <a href="admin.php" class="text-sm text-orange-400 hover:text-orange-300">&larr; Back to Admin</a>
        </div>

        <div class="bg-gray-700 p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-semibold text-white mb-4 border-b border-gray-600 pb-2">Latest 100 Runs</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-gray-600">
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Time</th>
                            <th class="py-2">Summary</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-600">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="3" class="py-4 text-gray-400">No logs found. The cron job may not have run yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="py-3 pr-4">
                                    <?php if ($log['status'] === 'Success'): ?>
                                        <span class="bg-green-600 text-green-100 font-medium py-1 px-3 rounded-full text-sm">Success</span>
                                    <?php else: ?>
                                        <span class="bg-red-600 text-red-100 font-medium py-1 px-3 rounded-full text-sm">Failure</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 pr-4 text-gray-400 text-sm whitespace-nowrap"><?php echo date('d M Y, H:i:s', strtotime($log['run_time'])); ?></td>
                                <td class="py-3 text-gray-300 text-sm whitespace-pre-wrap"><?php echo htmlspecialchars($log['summary']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>