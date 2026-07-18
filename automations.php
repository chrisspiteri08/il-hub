<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// --- NEW: Use config_loader ---
require_once 'config_loader.php';

// Get the cron secret key for the "Run Now" button
$cron_secret_key = $settings['cron_secret_key'];

$message = '';

// 1. Handle "Add New Job" form
if (isset($_POST['add_job'])) {
    $tag_ids = $_POST['tag_ids'] ?? [];
    $job_type = $_POST['job_type'] ?? '';
    
    // Default validation
    $validation_passed = true;
    if (empty($tag_ids) || empty($_POST['series_id']) || empty($_POST['import_url_or_id']) || empty($job_type)) {
        $validation_passed = false;
    }
    
    if (!$validation_passed) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: You must fill out all required fields.</div>';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insert into the main table
            $stmt = $pdo->prepare("INSERT INTO automated_imports (series_id, job_type, import_url_or_id) VALUES (?, ?, ?)");
            $stmt->execute([
                $_POST['series_id'],
                $job_type,
                $_POST['import_url_or_id']
            ]);
            $new_import_id = $pdo->lastInsertId();

            // Insert into the tags junction table
            $tag_stmt = $pdo->prepare("INSERT INTO import_tags (import_id, tag_id) VALUES (?, ?)");
            foreach ($tag_ids as $tag_id) {
                $tag_stmt->execute([$new_import_id, $tag_id]);
            }
            
            $pdo->commit();
            $message = '<div class="bg-green-600 p-4 rounded-lg text-white">New automation job added successfully!</div>';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// 2. Handle "Delete Job"
if (isset($_GET['delete_job'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM automated_imports WHERE id = ?");
        $stmt->execute([$_GET['delete_job']]);
        $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Automation job deleted.</div>';
    } catch (PDOException $e) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: ' . $e->getMessage() . '</div>';
    }
}

// 3. Fetch data for forms and lists
try {
    $series_list = $pdo->query("SELECT id, title FROM series ORDER BY title ASC")->fetchAll();
    // Fetch all tags dynamically
    $tags_list = $pdo->query("SELECT id, name FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    // Fetch all existing jobs
    $jobs_list = $pdo->query("
        SELECT ai.id, ai.import_url_or_id, ai.job_type, ai.last_checked_at, s.title AS series_title
        FROM automated_imports AS ai
        JOIN series AS s ON ai.series_id = s.id
        ORDER BY s.title ASC
    ")->fetchAll();
} catch (PDOException $e) {
    die("Could not fetch data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automation Manager - il-hub.com</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
    <script>
        function runSelectedJobs() {
            // Get all checked checkboxes
            const checkboxes = document.querySelectorAll('input[name="selected_jobs[]"]:checked');
            if (checkboxes.length === 0) {
                alert("Please select at least one job to run.");
                return;
            }

            // Build list of IDs (e.g., "1,5,8")
            let ids = [];
            checkboxes.forEach((cb) => {
                ids.push(cb.value);
            });
            const jobsParam = ids.join(',');

            // Construct the cron URL
            // Note: Using 'cron_run_imports.php' directly.
            // This assumes it's in the same directory.
            const secretKey = "<?php echo htmlspecialchars($cron_secret_key); ?>";
            const url = `cron_run_imports.php?key=${secretKey}&jobs=${jobsParam}`;

            // Open in new tab
            window.open(url, '_blank');
        }
    </script>
</head>
<body class="bg-gray-800 text-gray-200 antialiased p-8">

    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">Automation Manager</h1>
            <a href="admin.php" class="text-sm text-orange-400 hover:text-orange-300">&larr; Back to Admin</a>
        </div>

        <?php if ($message) echo '<div class="mb-6">' . $message . '</div>'; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Add New Job Form -->
            <form action="automations.php" method="POST" class="bg-gray-700 p-6 rounded-lg shadow-lg space-y-4">
                <h2 class="text-2xl font-semibold text-white mb-4 border-b border-gray-600 pb-2">Add New Auto-Import</h2>
                
                <div>
                    <label for="job_type" class="block text-sm font-medium text-gray-300">Job Type</label>
                    <select name="job_type" id="job_type" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50">
                        <option value="">Select a job type</option>
                        <option value="youtube">YouTube Playlist</option>
                        <option value="youtube_channel">YouTube Channel (All Uploads)</option>
                        <option value="tvmi">TVMi Series</option>
                        <option value="one">ONE Series</option>
                    </select>
                </div>

                <div>
                    <label for="import_url_or_id" class="block text-sm font-medium text-gray-300">Import URL or ID</label>
                    <input type="text" name="import_url_or_id" id="import_url_or_id" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" placeholder="e.g., Playlist ID, Channel Handle, or TVMi Link">
                </div>

                <!-- API Key Field Removed - It's now in Settings -->
                <p class="text-xs text-gray-400">Note: The site's YouTube API key (from Settings) will be used automatically for YouTube jobs.</p>

                <div>
                    <label for="series_id" class="block text-sm font-medium text-gray-300">Assign to Series</label>
                    <select name="series_id" id="series_id" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50">
                        <option value="">Select the series</option>
                        <?php foreach ($series_list as $series): ?>
                            <option value="<?php echo $series['id']; ?>"><?php echo htmlspecialchars($series['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300">Assign to Categories</label>
                    <div class="mt-2 space-y-2 bg-gray-600 p-4 rounded-md max-h-40 overflow-y-auto">
                        <?php foreach ($tags_list as $tag): ?>
                        <label class="flex items-center">
                            <input type="checkbox" name="tag_ids[]" value="<?php echo $tag['id']; ?>" class="rounded text-orange-500 focus:ring-orange-400">
                            <span class="ml-2 text-white capitalize"><?php echo htmlspecialchars($tag['name']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" name="add_job" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition-colors">
                    Save Automation Job
                </button>
            </form>

            <!-- Manage Existing Jobs List -->
            <div class="bg-gray-700 p-6 rounded-lg shadow-lg flex flex-col h-full">
                <div class="flex justify-between items-center mb-4 border-b border-gray-600 pb-2">
                    <h2 class="text-2xl font-semibold text-white">Current Jobs</h2>
                    <!-- NEW: Run Selected Button -->
                    <button type="button" onclick="runSelectedJobs()" class="bg-teal-500 hover:bg-teal-600 text-white text-sm font-medium py-2 px-3 rounded-md transition-colors shadow-sm">
                        Run Selected Now
                    </button>
                </div>
                
                <div class="flex-grow overflow-y-auto pr-2" style="max-height: 600px;">
                    <ul class="space-y-3">
                        <?php if (empty($jobs_list)): ?>
                            <li class="text-gray-400">No automation jobs set up yet.</li>
                        <?php endif; ?>
                        <?php foreach ($jobs_list as $job): ?>
                            <li class="flex items-start space-x-3 bg-gray-600 p-3 rounded-md">
                                <!-- NEW: Checkbox for selecting jobs -->
                                <div class="pt-1">
                                    <input type="checkbox" name="selected_jobs[]" value="<?php echo $job['id']; ?>" class="h-5 w-5 rounded text-orange-500 focus:ring-orange-400 bg-gray-700 border-gray-500 cursor-pointer">
                                </div>
                                
                                <div class="flex-grow overflow-hidden">
                                    <span class="font-medium block truncate"><?php echo htmlspecialchars($job['series_title']); ?> 
                                        <span class="text-xs uppercase bg-blue-500 text-white px-2 py-0.5 rounded-full ml-1"><?php echo htmlspecialchars($job['job_type']); ?></span>
                                    </span>
                                    <span class="block text-sm text-gray-400 truncate" title="<?php echo htmlspecialchars($job['import_url_or_id']); ?>">
                                        ID: <?php echo htmlspecialchars(substr($job['import_url_or_id'], 0, 30)) . (strlen($job['import_url_or_id']) > 30 ? '...' : ''); ?>
                                    </span>
                                    <span class="block text-xs text-gray-500">Last Checked: <?php echo $job['last_checked_at'] ?? 'Never'; ?></span>
                                </div>
                                <a href="automations.php?delete_job=<?php echo $job['id']; ?>" class="text-sm bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md transition-colors ml-2 self-center" onclick="return confirm('Are you sure you want to delete this job?')">Delete</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>