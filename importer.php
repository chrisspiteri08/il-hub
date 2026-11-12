<?php
session_start();

// --- NEW: Use config_loader ---
require_once 'config_loader.php';

// 1. Check if the user is logged in.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// --- FACEBOOK CONFIG (Now from $settings) ---
$fb_page_id = $settings['fb_page_id'];
$fb_page_token = $settings['fb_page_token'];
$fb_autopost_enabled = ($settings['fb_autopost_enabled'] ?? '0') === '1';
// --- END FACEBOOK CONFIG ---

$message = '';
$prefill = [
    'title' => '',
    'description' => '',
    'thumbnail_url' => '',
    'media_url' => '',
    'platform' => '',
    'publish_date' => ''
];

// 3. Fetch data for forms
try {
    $series_list = $pdo->query("SELECT id, title FROM series ORDER BY title ASC")->fetchAll();
    // --- NEW: FETCH TAGS DYNAMICALLY ---
    $tags_list = $pdo->query("SELECT id, name FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Could not fetch data: " . $e->getMessage());
}

/**
 * Helper function to fetch URL content via cURL
 */
function fetchUrlContent($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For HTTPS
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_REFERER, "https://il-hub.com");
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

/**
 * NEW: Facebook Post Function (copied from cron script)
 */
function postToFacebook($message, $link, $page_id, $page_token) {
    $fb_api_url = "https://graph.facebook.com/v18.0/{$page_id}/feed";
    $params = [
        'message' => $message,
        'link' => $link,
        'access_token' => $page_token
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fb_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);
    if ($response === FALSE || $curl_error) return "Facebook Post Failed (cURL): " . $curl_error;
    $response_data = json_decode($response, true);
    if (isset($response_data['error'])) return "Facebook Post Failed (API): "."(Code ".$response_data['error']['code'].") ".$response_data['error']['message'];
    return "Posted to Facebook!";
}

// 4. Handle "Fetch URL" submission
if (isset($_POST['fetch_url'])) {
    $url = $_POST['url_to_fetch'];
    // --- NEW: Get API key from settings ---
    $api_key = $settings['youtube_api_key'];
    $prefill['media_url'] = $url; // Pre-fill the URL we're fetching

    try {
        // --- NEW Smart Fetch Logic ---
        $is_youtube_url = (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false);
        $video_id = null;

        if ($is_youtube_url) {
            if (strpos($url, 'youtu.be') !== false) {
                $video_id = parse_url($url, PHP_URL_PATH);
                $video_id = trim($video_id, '/');
            } else if (strpos($url, 'watch') !== false) {
                parse_str(parse_url($url, PHP_URL_QUERY), $query_params);
                if (isset($query_params['v'])) {
                    $video_id = $query_params['v'];
                }
            }
        }

        // --- Use API if it's a YouTube video ---
        if ($video_id) {
            if (empty($api_key)) {
                throw new Exception("YouTube API Key is not set in your admin settings.");
            }

            $details_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,liveStreamingDetails&id=" . urlencode($video_id) . "&key=" . urlencode($api_key);
            $details_json = fetchUrlContent($details_url);
            $details_data = json_decode($details_json, true);

            if (isset($details_data['error'])) {
                throw new Exception("API Error: " . htmlspecialchars($details_data['error']['message']));
            }

            if (!empty($details_data['items'])) {
                $video = $details_data['items'][0];
                $snippet = $video['snippet'];

                $prefill['title'] = $snippet['title'];

                // --- NEW JUNK VIDEO FILTER ---
                if ($prefill['title'] == 'Deleted video' || $prefill['title'] == 'Private video') {
                    throw new Exception("This is a 'Deleted' or 'Private' video and will not be added.");
                }
                // --- END FILTER ---

                $prefill['description'] = $snippet['description'];
                $prefill['thumbnail_url'] = $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['medium']['url'] ?? 'https://placehold.co/600x400/1f2937/7f9cf5?text=No+Image';
                $prefill['platform'] = 'YouTube';

                $publish_date_iso = $snippet['publishedAt'];
                if (isset($video['liveStreamingDetails']['scheduledStartTime'])) {
                    $publish_date_iso = $video['liveStreamingDetails']['scheduledStartTime'];
                }
                $prefill['publish_date'] = date('Y-m-d\TH:i', strtotime($publish_date_iso));

                $message = '<div class="bg-green-600 p-4 rounded-lg text-white">YouTube details fetched successfully! Please select a Series and Tags below.</div>';
            } else {
                throw new Exception("Video not found on YouTube.");
            }

        } else {
            // --- Fallback to scraping og:meta tags (for TVMi, etc.) ---
            $html = fetchUrlContent($url);
            if ($html) {
                $doc = new DOMDocument();
                @$doc->loadHTML($html);
                $meta_tags = $doc->getElementsByTagName('meta');

                $std_description = '';
                $twitter_description = '';
                foreach ($meta_tags as $meta) {
                    $property = $meta->getAttribute('property');
                    $name = $meta->getAttribute('name');
                    $content = $meta->getAttribute('content');
                    if ($property == 'og:title') $prefill['title'] = $content;
                    if ($property == 'og:description') $prefill['description'] = $content;
                    if ($property == 'og:image') $prefill['thumbnail_url'] = $content;
                    if ($name == 'description') $std_description = $content;
                    if ($name == 'twitter:description') $twitter_description = $content;
                }
                // Not every site uses Open Graph tags; fall back to the
                // standard SEO description meta tag, then a Twitter card.
                if (empty($prefill['description'])) {
                    $prefill['description'] = $std_description ?: $twitter_description;
                }

                // --- NEW JUNK VIDEO FILTER ---
                if ($prefill['title'] == 'Deleted video' || $prefill['title'] == 'Private video') {
                    throw new Exception("This is a 'Deleted' or 'Private' video and will not be added.");
                }
                // --- END FILTER ---

                // Also guess platform from URL
                if (strpos($url, 'tvmi.mt') !== false) $prefill['platform'] = 'TVMi';
                if (strpos($url, 'one.com.mt') !== false) $prefill['platform'] = 'ONE';
                if (strpos($url, 'youtube.com') !== false) $prefill['platform'] = 'YouTube';

                $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Details scraped successfully! Please verify info, select Series/Tags.</div>';
            } else {
                $message = '<div class="bg-yellow-500 p-4 rounded-lg text-yellow-900">Could not fetch details. Please fill in the form manually.</div>';
            }
        }
    } catch (Exception $e) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: ' . $e->getMessage() . '</div>';
    }
}

// 5. Handle "Add Episode" submission (same as admin.php)
if (isset($_POST['add_episode'])) {
    $tag_ids = $_POST['tag_ids'] ?? [];
    if (empty($tag_ids) || empty($_POST['series_id'])) {
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: You must select a Series and at least one Category Tag.</div>';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO episodes (series_id, title, description, media_url, thumbnail_url, platform, publish_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['series_id'],
                $_POST['title'],
                $_POST['description'],
                $_POST['media_url'],
                $_POST['thumbnail_url'],
                $_POST['platform'],
                empty($_POST['publish_date']) ? null : $_POST['publish_date']
            ]);
            $new_episode_id = $pdo->lastInsertId();

            $tag_stmt = $pdo->prepare("INSERT INTO episode_tags (episode_id, tag_id) VALUES (?, ?)");
            foreach ($tag_ids as $tag_id) {
                $tag_stmt->execute([$new_episode_id, $tag_id]);
            }

            $pdo->commit();

            // --- NEW: Post to Facebook ---
            if (!$fb_autopost_enabled) {
                $message = '<div class="bg-green-600 p-4 rounded-lg text-white">New episode added successfully. Facebook auto-post is disabled.</div>';
            } else {
                try {
                    // Get the Series Title for the post
                    $series_stmt = $pdo->prepare("SELECT title FROM series WHERE id = ?");
                    $series_stmt->execute([$_POST['series_id']]);
                    $series_title = $series_stmt->fetchColumn();

                    // === NEW: Get template from settings ===
                    $fb_message_template = $settings['fb_post_template_single'];
                    $fb_message = str_replace(
                        ['{title}', '{series}'],
                        [$_POST['title'], $series_title],
                        $fb_message_template
                    );
                    $fb_link = $_POST['media_url'];

                    // Post to Facebook
                    $fb_status = postToFacebook($fb_message, $fb_link, $fb_page_id, $fb_page_token);

                    // Set a success message that includes the Facebook post
                    $message = '<div class="bg-green-600 p-4 rounded-lg text-white">New episode added and posted to Facebook! ('.$fb_status.')</div>';

                } catch (Exception $e) {
                    // Catch Facebook post errors
                    $message = '<div class="bg-yellow-500 p-4 rounded-lg text-yellow-900">Episode added to DB, but FAILED to post to Facebook. Error: ' . $e->getMessage() . '</div>';
                }
            }
            // --- END: Post to Facebook ---

            // Clear the prefill array
            $prefill = ['title' => '', 'description' => '', 'thumbnail_url' => '', 'media_url' => ''];

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add from URL - il-hub.com</title>
    <script src="https://cdn-tailwindcss.vercel.app/"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                },
            },
        }
    </script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-800 text-gray-200 antialiased p-8">

    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">Add From URL</h1>
            <a href="admin.php" class="text-sm text-orange-400 hover:text-orange-300">&larr; Back to Admin</a>
        </div>

        <?php if ($message) echo '<div class="mb-6">' . $message . '</div>'; ?>

        <!-- Step 1: Fetch URL Form -->
        <form action="add_from_url.php" method="POST" class="bg-gray-700 p-6 rounded-lg shadow-lg space-y-4 mb-8">
            <h2 class="text-2xl font-semibold text-white mb-4 border-b border-gray-600 pb-2">Fetch Episode Details</h2>
            <div>
                <label for="url_to_fetch" class="block text-sm font-medium text-gray-300">Paste Episode URL (YouTube, TVMi, etc.)</label>
                <input type="url" name="url_to_fetch" id="url_to_fetch" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($prefill['media_url']); ?>">
            </div>

            <p class="text-xs text-gray-400">If this is a YouTube URL, the site's API key will be used automatically.</p>
            <button type="submit" name="fetch_url" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition-colors">
                Fetch Details
            </button>
        </form>

        <!-- Step 2: Add Episode Form (Pre-filled) -->
        <form action="add_from_url.php" method="POST" class="bg-gray-700 p-6 rounded-lg shadow-lg space-y-4">
            <h2 class="text-2xl font-semibold text-white mb-4 border-b border-gray-600 pb-2">Confirm & Add Episode</h2>

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
                <input type="text" name="title" id="e_title" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($prefill['title']); ?>">
            </div>

            <div>
                <label for="e_desc" class="block text-sm font-medium text-gray-300">Description</label>
                <textarea name="description" id="e_desc" rows="3" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50"><?php echo htmlspecialchars($prefill['description']); ?></textarea>
            </div>

            <div>
                <label for="e_url" class="block text-sm font-medium text-gray-300">Media URL</label>
                <input type="text" name="media_url" id="e_url" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($prefill['media_url']); ?>">
            </div>

            <div>
                <label for="e_img" class="block text-sm font-medium text-gray-300">Thumbnail URL</label>
                <input type="text" name="thumbnail_url" id="e_img" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($prefill['thumbnail_url']); ?>">
            </div>

            <div>
                <label for="e_platform" class="block text-sm font-medium text-gray-300">Platform (e.g., TVMi, YouTube)</label>
                <input type="text" name="platform" id="e_platform" required class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($prefill['platform']); ?>">
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

            <div>
                <label for="e_publish_date" class="block text-sm font-medium text-gray-300">Publish Date (Optional)</label>
                <input type="datetime-local" name="publish_date" id="e_publish_date" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" style="color-scheme: dark;" value="<?php echo htmlspecialchars($prefill['publish_date']); ?>">
            </div>

            <button type="submit" name="add_episode" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition-colors mt-4">
                Add Episode
            </button>
        </form>
    </div>

</body>
</html>
