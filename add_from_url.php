<?php
session_start();

// --- STEP-BY-STEP DEBUG LOGGING UTILITY ---
$log_file = __DIR__ . '/add_from_url_debug.log';

function writeDebugLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Ensure we record any script start
writeDebugLog("--- SCRIPT INITIALISED ---");

// Register a shutdown function to capture any hidden fatal PHP errors causing 500 crashes
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        writeDebugLog("FATAL SHUTDOWN ERROR: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
    }
});

// Set global exception handler
set_exception_handler(function($exception) {
    writeDebugLog("UNCAUGHT EXCEPTION: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    echo "<div style='background:#fdd;color:#900;padding:15px;border:1px solid #900;margin:15px 0;border-radius:8px;'>";
    echo "<strong>Fatal Exception:</strong> " . htmlspecialchars($exception->getMessage()) . " on line " . $exception->getLine();
    echo "</div>";
});

// --- NEW: Use config_loader ---
writeDebugLog("Attempting to load config_loader.php");
require_once 'config_loader.php';
writeDebugLog("config_loader.php successfully loaded.");

// 1. Check if the user is logged in.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    writeDebugLog("Redirecting unauthorised guest user to login.php");
    header('Location: login.php');
    exit;
}

// --- FACEBOOK CONFIG (Now from $settings) ---
$fb_page_id = $settings['fb_page_id'] ?? '';
$fb_page_token = $settings['fb_page_token'] ?? '';
writeDebugLog("Loaded settings. FB Page ID: " . ($fb_page_id ? 'Configured' : 'Missing'));
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
    writeDebugLog("Fetching series list and category tags from DB...");
    $series_list = $pdo->query("SELECT id, title FROM series ORDER BY title ASC")->fetchAll();
    $tags_list = $pdo->query("SELECT id, name FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    writeDebugLog("Successfully fetched " . count($series_list) . " series and " . count($tags_list) . " tags.");
} catch (PDOException $e) {
    writeDebugLog("DATABASE FATAL QUERY ERROR: " . $e->getMessage());
    die("Could not fetch data: " . $e->getMessage());
}

/**
 * Helper function to create a URL friendly slug safely without relying on unstable iconv
 */
function createSlug($text) {
    $text = strtolower($text);
    
    // Explicitly replace Maltese characters first to avoid issues
    $text = str_replace(
        ['ċ', 'ġ', 'ħ', 'ż', 'à', 'è', 'ì', 'ò', 'ù'], 
        ['c', 'g', 'h', 'z', 'a', 'e', 'i', 'o', 'u'], 
        $text
    );
    
    // Replace non-letters or digits with -
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    // Trim dashes
    $text = trim($text, '-');
    // Remove duplicate dashes
    $text = preg_replace('/-+/', '-', $text);

    if (empty($text)) {
        return 'n-a';
    }
    return $text;
}

/**
 * Helper function to fetch URL content via cURL
 */
function fetchUrlContent($url) {
    writeDebugLog("fetchUrlContent started for URL: $url");
    if (!function_exists('curl_init')) {
        writeDebugLog("FATAL FUNCTION MISSING: curl_init is not enabled on this server.");
        throw new Exception("cURL PHP extension is missing from this server.");
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_REFERER, "https://il-hub.com");

    $html = curl_exec($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);
    
    if ($curl_err) {
        writeDebugLog("cURL transfer error reported: $curl_err");
    } else {
        writeDebugLog("cURL transfer complete. Content-Length: " . strlen($html));
    }
    return $html;
}

/**
 * UPDATED: Facebook Photo Post Function
 * Posts the thumbnail image directly with the link in the caption.
 */
function postToFacebook($message, $image_url, $page_id, $page_token) {
    if (empty($page_id) || empty($page_token)) {
        writeDebugLog("Skipping Facebook posting: missing credentials.");
        return "Skipped Facebook (Config missing)";
    }
    
    writeDebugLog("Posting newly added episode to Facebook page $page_id...");
    $fb_api_url = "https://graph.facebook.com/v18.0/{$page_id}/photos";
    
    $params = [
        'url' => $image_url,
        'caption' => $message,
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
    
    if ($response === FALSE || $curl_error) {
        writeDebugLog("Facebook API post error (cURL): $curl_error");
        return "Facebook Post Failed (cURL): " . $curl_error;
    }
    
    $response_data = json_decode($response, true);
    if (isset($response_data['error'])) {
        writeDebugLog("Facebook API returned error response: " . json_encode($response_data['error']));
        return "Facebook Post Failed (API): " . "(Code ".$response_data['error']['code'].") ".$response_data['error']['message'];
    }
    
    writeDebugLog("Successfully posted content to Facebook.");
    return "Posted Photo to Facebook!";
}

// 4. Handle "Fetch URL" submission
if (isset($_POST['fetch_url'])) {
    $url = $_POST['url_to_fetch'];
    writeDebugLog("Fetch URL submitted for URL: $url");
    $api_key = $settings['youtube_api_key'] ?? ''; 
    $prefill['media_url'] = $url;

    try {
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
            writeDebugLog("Detected YouTube URL pattern. Target Video ID: " . ($video_id ? $video_id : 'Could not isolate'));
        }

        if ($video_id) {
            if (empty($api_key)) {
                writeDebugLog("Exception: YouTube API Key is missing in general admin settings.");
                throw new Exception("YouTube API Key is not set in your admin settings.");
            }
            
            $details_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,liveStreamingDetails&id=" . urlencode($video_id) . "&key=" . urlencode($api_key);
            writeDebugLog("Sending query request directly to YouTube API...");
            $details_json = fetchUrlContent($details_url);
            $details_data = json_decode($details_json, true);

            if (isset($details_data['error'])) {
                writeDebugLog("YouTube API responded with error: " . $details_data['error']['message']);
                throw new Exception("API Error: " . htmlspecialchars($details_data['error']['message']));
            }
            
            if (!empty($details_data['items'])) {
                $video = $details_data['items'][0];
                $snippet = $video['snippet'];
                
                $prefill['title'] = $snippet['title'];
                writeDebugLog("Prefill Title successfully mapped: " . $prefill['title']);
                
                if ($prefill['title'] == 'Deleted video' || $prefill['title'] == 'Private video') {
                    throw new Exception("This is a 'Deleted' or 'Private' video and will not be added.");
                }

                $prefill['description'] = $snippet['description'];
                $prefill['thumbnail_url'] = $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['medium']['url'] ?? 'https://placehold.co/600x400/1f2937/7f9cf5?text=No+Image';
                $prefill['platform'] = 'YouTube';
                
                $publish_date_iso = $snippet['publishedAt'];
                if (isset($video['liveStreamingDetails']['scheduledStartTime'])) {
                    $publish_date_iso = $video['liveStreamingDetails']['scheduledStartTime'];
                }
                $prefill['publish_date'] = date('Y-m-d\TH:i', strtotime($publish_date_iso));
                
                $message = '<div class="bg-green-600 p-4 rounded-lg text-white">YouTube details fetched successfully!</div>';
            } else {
                writeDebugLog("No valid items returned from YouTube API query.");
                throw new Exception("Video not found on YouTube.");
            }
        
        } else {
            writeDebugLog("Proceeding with custom fallback page scraper...");
            $html = fetchUrlContent($url);
            if ($html) {
                $og_title = '';
                $og_desc = '';
                $og_image = '';

                // Safe check for DOMDocument class (avoids 500 error if dom extension is missing)
                if (class_exists('DOMDocument')) {
                    writeDebugLog("DOMDocument class found. Parsing meta nodes...");
                    $doc = new DOMDocument();
                    @$doc->loadHTML($html);
                    $meta_tags = $doc->getElementsByTagName('meta');

                    foreach ($meta_tags as $meta) {
                        $property = $meta->getAttribute('property');
                        $content = $meta->getAttribute('content');
                        if ($property == 'og:title') $og_title = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
                        if ($property == 'og:description') $og_desc = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
                        if ($property == 'og:image') $og_image = $content;
                    }
                } else {
                    writeDebugLog("DOMDocument missing. Reverting to custom regular expression meta-parsing tags...");
                    if (preg_match('/<meta[^>]*property=["\']og:title["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $matches)) {
                        $og_title = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                    } elseif (preg_match('/<meta[^>]*content=["\']([^"\']*)["\'][^>]*property=["\']og:title["\']/i', $html, $matches)) {
                        $og_title = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                    }

                    if (preg_match('/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $matches)) {
                        $og_desc = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                    } elseif (preg_match('/<meta[^>]*content=["\']([^"\']*)["\'][^>]*property=["\']og:description["\']/i', $html, $matches)) {
                        $og_desc = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                    }

                    if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $matches)) {
                        $og_image = $matches[1];
                    } elseif (preg_match('/<meta[^>]*content=["\']([^"\']*)["\'][^>]*property=["\']og:image["\']/i', $html, $matches)) {
                        $og_image = $matches[1];
                    }
                }

                $prefill['title'] = $og_title;
                $prefill['description'] = $og_desc;
                $prefill['thumbnail_url'] = $og_image;
                writeDebugLog("Scraper successfully mapped. Title: " . $prefill['title']);

                if (strpos($url, 'facebook.com') !== false || strpos($url, 'fb.watch') !== false) {
                    if (strpos($prefill['title'], '|') !== false) {
                        $parts = explode('|', $prefill['title']);
                        if (stripos($parts[0], 'views') !== false) {
                            array_shift($parts);
                        }
                        $prefill['title'] = trim(implode('|', $parts));
                    }
                }

                if ($prefill['title'] == 'Deleted video' || $prefill['title'] == 'Private video') {
                    throw new Exception("This is a 'Deleted' or 'Private' video and will not be added.");
                }
                
                if (strpos($url, 'tvmi.mt') !== false) $prefill['platform'] = 'TVMi';
                if (strpos($url, 'one.com.mt') !== false) $prefill['platform'] = 'ONE';
                if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) $prefill['platform'] = 'YouTube';
                if (strpos($url, 'facebook.com') !== false || strpos($url, 'fb.watch') !== false) $prefill['platform'] = 'Facebook';

                $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Details scraped successfully!</div>';
            } else {
                writeDebugLog("Scraper failed to pull page body content.");
                $message = '<div class="bg-yellow-500 p-4 rounded-lg text-yellow-900">Could not fetch details. Please fill in the form manually.</div>';
            }
        }
    } catch (Exception $e) {
        writeDebugLog("FETCH PROCESS EXCEPTION ERROR: " . $e->getMessage());
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: ' . $e->getMessage() . '</div>';
    }
}

// 5. Handle "Add Episode" submission
if (isset($_POST['add_episode'])) {
    writeDebugLog("Add Episode manual form submission received.");
    $tag_ids = $_POST['tag_ids'] ?? [];
    $wants_transcription = isset($_POST['do_transcribe']) ? 1 : 0;
    
    if (empty($tag_ids) || empty($_POST['series_id'])) {
        writeDebugLog("Validation failed: Series or Category tags are empty.");
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error: You must select a Series and at least one Category Tag.</div>';
    } else {
        try {
            writeDebugLog("Database transaction beginning...");
            $pdo->beginTransaction();
            
            // Format datetime-local string to clean SQL timestamp format safely
            $publish_date_raw = empty($_POST['publish_date']) ? null : $_POST['publish_date'];
            $publish_date_sql = null;
            if ($publish_date_raw) {
                $ts = strtotime($publish_date_raw);
                if ($ts !== false) {
                    $publish_date_sql = date('Y-m-d H:i:s', $ts);
                }
            }
            writeDebugLog("Publish date parsed into SQL format: " . ($publish_date_sql ? $publish_date_sql : 'NULL'));

            // Generate a temporary unique slug to bypass all UNIQUE key index constraints on initial insert
            $temp_slug = 'temp-' . uniqid() . '-' . rand(1000, 9999);
            writeDebugLog("Inserting temporary unique slug: $temp_slug");

            // Insert using our guaranteed unique temp slug
            $stmt = $pdo->prepare("INSERT INTO episodes (series_id, title, slug, description, media_url, thumbnail_url, platform, publish_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['series_id'],
                $_POST['title'],
                $temp_slug,
                $_POST['description'],
                $_POST['media_url'],
                $_POST['thumbnail_url'],
                $_POST['platform'],
                $publish_date_sql
            ]);
            $new_episode_id = $pdo->lastInsertId();
            writeDebugLog("Temporary episode successfully inserted with ID: $new_episode_id");

            // Generate the clean base slug using our safe regex translation function
            $base_slug = createSlug($_POST['title']);

            // Unique hybrid strategy (Prioritise date suffix, fallback to database ID)
            $has_valid_date = !empty($publish_date_sql) && $publish_date_sql !== '0000-00-00 00:00:00' && $publish_date_sql !== '0000-00-00';
            if ($has_valid_date) {
                $slug = $base_slug . "-" . date('Y-m-d', strtotime($publish_date_sql));
            } else {
                $slug = $base_slug . "-" . $new_episode_id;
            }
            writeDebugLog("Final unique slug generated: $slug");

            // Save the finalized unique slug
            $update_slug_stmt = $pdo->prepare("UPDATE episodes SET slug = ? WHERE id = ?");
            $update_slug_stmt->execute([$slug, $new_episode_id]);
            writeDebugLog("Final slug successfully updated.");

            writeDebugLog("Mapping and inserting episode category tags...");
            $tag_stmt = $pdo->prepare("INSERT INTO episode_tags (episode_id, tag_id) VALUES (?, ?)");
            foreach ($tag_ids as $tag_id) {
                $tag_stmt->execute([$new_episode_id, $tag_id]);
            }
            writeDebugLog("Inserted " . count($tag_ids) . " category tags.");

            if ($wants_transcription) {
                writeDebugLog("User checked AI transcription option. Enqueueing pending transcription record...");
                $trans_stmt = $pdo->prepare("INSERT INTO transcriptions (episode_id, status, created_at) VALUES (?, 'pending', NOW())");
                $trans_stmt->execute([$new_episode_id]);
                writeDebugLog("Transcription successfully flagged as pending.");
            }
            
            writeDebugLog("Committing database transaction...");
            $pdo->commit();
            writeDebugLog("Database transaction successfully committed.");
            
            // --- Post Photo to Facebook ---
            try {
                $series_stmt = $pdo->prepare("SELECT title FROM series WHERE id = ?");
                $series_stmt->execute([$_POST['series_id']]);
                $series_title = $series_stmt->fetchColumn();
                
                $fb_message = "New Episode Added on il-hub.com\n\n";
                $fb_message .= "'" . $_POST['title'] . "' from " . $series_title . "\n\n";
                $fb_message .= "Watch here: https://il-hub.com/episode/" . $slug;
                $fb_image = $_POST['thumbnail_url'];
                
                $fb_status = postToFacebook($fb_message, $fb_image, $fb_page_id, $fb_page_token);
                $message = '<div class="bg-green-600 p-4 rounded-lg text-white">New episode added and posted to Facebook! ('.$fb_status.')</div>';
                
            } catch (Exception $e) {
                writeDebugLog("Facebook integration failed: " . $e->getMessage());
                $message = '<div class="bg-yellow-500 p-4 rounded-lg text-yellow-900">Episode added, but FAILED to post to Facebook. Error: ' . $e->getMessage() . '</div>';
            }

            // Fully reset prefill array keys with empty strings to avoid notice-based 500 error crashes
            $prefill = [
                'title' => '',
                'description' => '',
                'thumbnail_url' => '',
                'media_url' => '',
                'platform' => '',
                'publish_date' => ''
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                writeDebugLog("Database error caught. Rolling back open transaction...");
                $pdo->rollBack();
            }
            writeDebugLog("MANUAL ADD SAVE FATAL ERROR EXCEPTION: " . $e->getMessage());
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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

            <div>
                <label class="block text-sm font-medium text-gray-300">Category Tags</label>
                <div class="mt-2 space-y-2 bg-gray-600 p-4 rounded-md">
                    <?php foreach ($tags_list as $tag): ?>
                    <label class="flex items-center">
                        <input type="checkbox" name="tag_ids[]" value="<?php echo $tag['id']; ?>" class="rounded text-orange-500 focus:ring-orange-400">
                        <span class="ml-2 text-white capitalize"><?php echo htmlspecialchars($tag['name']); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label for="e_publish_date" class="block text-sm font-medium text-gray-300">Publish Date (Optional)</label>
                <input type="datetime-local" name="publish_date" id="e_publish_date" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" style="color-scheme: dark;" value="<?php echo htmlspecialchars($prefill['publish_date']); ?>">
            </div>

            <!-- AI Transcription Option -->
            <div class="p-4 bg-indigo-900/30 border border-indigo-500/50 rounded-lg mt-4 shadow-inner">
                <label class="flex items-center space-x-3 cursor-pointer group">
                    <input type="checkbox" name="do_transcribe" value="1" class="w-5 h-5 rounded text-indigo-500 focus:ring-indigo-400 transition-all group-hover:scale-110">
                    <div>
                        <span class="text-white font-bold block">Generate AI Transcript & Summary</span>
                        <span class="text-indigo-200 text-xs">Your MacBook will automatically process this episode for search and social media.</span>
                    </div>
                </label>
            </div>

            <button type="submit" name="add_episode" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition-colors mt-4">
                Add Episode
            </button>
        </form>
    </div>

</body>
</html>