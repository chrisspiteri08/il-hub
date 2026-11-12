<?php
// --- FORCE ERROR DISPLAY ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Set a longer execution time, as scraping/API pagination can be slow
set_time_limit(600); // 10 minutes

// Start output buffering to capture all echos for the log
ob_start();

// --- NEW: Use config_loader ---
require_once 'config_loader.php';
require_once 'slug_helpers.php';

// --- CONFIGURATION (Now from $settings) ---
$secret_key = $settings['cron_secret_key'];
$fb_page_id = $settings['fb_page_id'];
$fb_page_token = $settings['fb_page_token'];
$youtube_api_key = $settings['youtube_api_key']; // Get the global key

// 1. Check the secret key
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    die("Access denied. This script must be run by the server.");
}

// 2. DB connection is already included via config_loader.php

// --- NEW: Fetch the 'music' tag ID dynamically ---
// We do this once at the start so we know which ID to look for in the loop
$music_tag_id = null;
try {
    $m_stmt = $pdo->prepare("SELECT id FROM tags WHERE name = 'music' LIMIT 1");
    $m_stmt->execute();
    $music_tag_id = $m_stmt->fetchColumn();
} catch (Exception $e) {
    // If this fails, we just won't have special music handling
}

// --- HELPER FUNCTIONS ---

function writeToLog($pdo, $summary, $status = 'Success') {
    try {
        $log_stmt = $pdo->prepare("INSERT INTO cron_logs (status, summary) VALUES (?, ?)");
        $log_stmt->execute([$status, $summary]);
    } catch (PDOException $e) {
        echo "LOGGING FAILED: " . $e->getMessage() . "\n";
    }
}

/**
 * Smart Helper to automatically generate a unique slug for a new episode
 */
function updateSlug($pdo, $episode_id, $title, $publish_date) {
    $series_stmt = $pdo->prepare(
        'SELECT s.title FROM episodes e LEFT JOIN series s ON s.id = e.series_id WHERE e.id = ?'
    );
    $series_stmt->execute([$episode_id]);
    $series_title = (string) $series_stmt->fetchColumn();

    $base_slug = make_slug((string) $title);
    $series_slug = make_slug($series_title);
    $generic_title = $base_slug !== '' && $base_slug === $series_slug;
    $candidate = $base_slug !== '' ? $base_slug : ($series_slug !== '' ? $series_slug : 'episode');

    $is_taken = $pdo->prepare('SELECT COUNT(*) FROM episodes WHERE slug = ? AND id != ?');
    $is_taken->execute([$candidate, $episode_id]);
    if ($generic_title || (int) $is_taken->fetchColumn() > 0) {
        $has_valid_date = !empty($publish_date)
            && $publish_date !== '0000-00-00 00:00:00'
            && $publish_date !== '0000-00-00';
        $suffix = $has_valid_date ? date('Y-m-d', strtotime($publish_date)) : (string) $episode_id;
        $candidate = ($series_slug !== '' ? $series_slug : $candidate) . '-' . $suffix;
    }

    $slug = $candidate;
    $number = 2;
    while (true) {
        $is_taken->execute([$slug, $episode_id]);
        if ((int) $is_taken->fetchColumn() === 0) {
            break;
        }
        $number_suffix = '-' . $number++;
        $slug = rtrim(substr($candidate, 0, 90 - strlen($number_suffix)), '-') . $number_suffix;
    }

    $update_slug_stmt = $pdo->prepare("UPDATE episodes SET slug = ? WHERE id = ?");
    $update_slug_stmt->execute([$slug, $episode_id]);
}

/**
 * UPDATED: Facebook Photo Post Function
 * Posts an IMAGE with the link in the caption.
 */
function postToFacebook($message, $image_url, $page_id, $page_token) {
    // We use the /photos endpoint instead of /feed to get the big image
    $fb_api_url = "https://graph.facebook.com/v18.0/{$page_id}/photos";
    
    $params = [
        'url' => $image_url,       // The image to upload
        'caption' => $message,     // The text (including the link)
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
    $curl_close($ch);
    
    if ($response === FALSE || $curl_error) return "Facebook Post Failed (cURL): " . $curl_error;
    
    $response_data = json_decode($response, true);
    if (isset($response_data['error'])) {
        return "Facebook Post Failed (API): " . "(Code ".$response_data['error']['code'].") ".$response_data['error']['message'];
    }
    
    return "Posted Photo to Facebook!";
}

/**
 * Helper function to fetch URL content via cURL
 */
function fetchUrlContent($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_REFERER, "https://il-hub.com");
    
    // --- Cookie Jar for Session Consistency ---
    $cookie_file = sys_get_temp_dir() . '/ilhub_cookies.txt';
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);

    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Cache-Control: max-age=0',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: none',
        'Sec-Fetch-User: ?1'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

function scrapeEpisodePage($episode_url) {
    $html = fetchUrlContent($episode_url);
    if (!$html) {
        return null;
    }
    
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    $meta_tags = $doc->getElementsByTagName('meta');
    
    $details = [
        'title' => '',
        'description' => '',
        'thumbnail_url' => 'https://placehold.co/600x400/1f2937/7f9cf5?text=No+Image',
        'publish_date' => null // Start with NULL
    ];

    // 1. Try Meta Tags
    $og_description = '';
    $std_description = '';
    $twitter_description = '';
    foreach ($meta_tags as $meta) {
        $property = $meta->getAttribute('property');
        $name = $meta->getAttribute('name');
        $content = $meta->getAttribute('content');

        if ($property == 'og:title') $details['title'] = $content;
        if ($property == 'og:description') $og_description = $content;
        if ($property == 'og:image') $details['thumbnail_url'] = $content;
        if ($property == 'article:published_time') $details['publish_date'] = $content;
        if ($name == 'description') $std_description = $content;
        if ($name == 'twitter:description') $twitter_description = $content;
    }
    // Not every site uses Open Graph tags; fall back to the standard SEO
    // description meta tag, then a Twitter card, whichever is present.
    $details['description'] = $og_description ?: $std_description ?: $twitter_description;
    
    // 2. Try <title> tag if meta failed (LAST ATTEMPT FOR TITLE)
    if (empty($details['title'])) {
        $title_tags = $doc->getElementsByTagName('title');
        if ($title_tags->length > 0) {
            $details['title'] = $title_tags->item(0)->nodeValue;
            // Clean up common TVMi junk titles
            $details['title'] = trim(str_replace('- TVMi', '', $details['title']));
            $details['title'] = trim(str_replace('- ONE', '', $details['title']));
            $details['title'] = trim(str_replace('- Online', '', $details['title']));
        }
    }

    // 3. Try <h1> tag if still empty
    if (empty($details['title'])) {
        $h1_tags = $doc->getElementsByTagName('h1');
        if ($h1_tags->length > 0) {
            $details['title'] = trim($h1_tags->item(0)->nodeValue);
        }
    }
    
    // Date Logic
    if (!$details['publish_date']) {
        $time_tags = $doc->getElementsByTagName('time');
        if ($time_tags->length > 0) {
            $date_str = $time_tags->item(0)->getAttribute('datetime');
            if ($date_str) $details['publish_date'] = $date_str;
        }
    }
    
    if (!$details['publish_date']) {
        $xpath = new DOMXPath($doc);
        $class_query = "//div[contains(concat(' ', normalize-space(@class), ' '), ' bg-primary ') and contains(concat(' ', normalize-space(@class), ' '), ' font-semibold ') and contains(concat(' ', normalize-space(@class), ' '), ' rounded-sm ')]";
        $date_divs = $xpath->query($class_query);

        if ($date_divs->length > 0) {
            $date_text = $date_divs->item(0)->nodeValue;
            $date_obj = DateTime::createFromFormat('d/m/Y', $date_text);
            if ($date_obj) $details['publish_date'] = $date_obj->format('Y-m-d H:i:s');
        }
    }
    
    if ($details['publish_date']) {
        $details['publish_date'] = date('Y-m-d H:i:s', strtotime($details['publish_date']));
    } else {
        $details['publish_date'] = date('Y-m-d H:i:s');
    }

    return $details;
}

// --- MAIN CRON SCRIPT ---

$start_time = date('Y-m-d H:i:s');
echo "Cron Job Started: $start_time\n";

$total_episodes_added = 0;
$newly_added_episodes = []; 

try {
    // --- Selective Job Running ---
    $jobs_filter = "";
    $params = [];
    
    if (isset($_GET['jobs']) && !empty($_GET['jobs'])) {
        $requested_ids = array_map('intval', explode(',', $_GET['jobs']));
        $requested_ids = array_filter($requested_ids);
        
        if (!empty($requested_ids)) {
            $placeholders = implode(',', array_fill(0, count($requested_ids), '?'));
            $jobs_filter = "AND ai.id IN ($placeholders)";
            $params = $requested_ids;
            echo "Running specifically for Job IDs: " . implode(',', $requested_ids) . "\n";
        }
    } else {
        $jobs_filter = "AND (ai.last_checked_at IS NULL OR ai.last_checked_at < (NOW() - INTERVAL 1 HOUR))";
        echo "Running standard scheduled check.\n";
    }

    $jobs = $pdo->prepare("
        SELECT ai.*, s.title AS series_title, s.id AS series_db_id, GROUP_CONCAT(it.tag_id) AS tag_ids
        FROM automated_imports AS ai
        JOIN series AS s ON ai.series_id = s.id
        JOIN import_tags AS it ON ai.id = it.import_id
        WHERE 1=1 $jobs_filter
        GROUP BY ai.id
    ");
    $jobs->execute($params);
    $jobs = $jobs->fetchAll(PDO::FETCH_ASSOC);

    if (empty($jobs)) {
        echo "No jobs scheduled to run. Exiting.\n";
        $summary = ob_get_clean();
        writeToLog($pdo, $summary, 'Success');
        echo $summary;
        exit;
    }

    echo "Found " . count($jobs) . " job(s) to process.\n";

    // Prepare reusable database statements
    $insert_stmt = $pdo->prepare("INSERT INTO episodes (series_id, title, description, media_url, thumbnail_url, platform, publish_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $check_stmt = $pdo->prepare("SELECT id FROM episodes WHERE media_url = ?");
    $tag_stmt = $pdo->prepare("INSERT INTO episode_tags (episode_id, tag_id) VALUES (?, ?)");
    $update_job_stmt = $pdo->prepare("UPDATE automated_imports SET last_checked_at = NOW() WHERE id = ?");

    foreach ($jobs as $job) {
        $job_id = $job['id'];
        $series_id = $job['series_id'];
        $series_title = $job['series_title'];
        $series_db_id = $job['series_db_id']; 
        $import_url = $job['import_url_or_id'];
        $job_type = $job['job_type'];
        $tag_ids_array = explode(',', $job['tag_ids']);

        // --- NEW: DETERMINE DURATION LIMIT & MUSIC STATUS ---
        // Default limit is 600 seconds (10 mins) to block shorts/trailers
        $min_duration_seconds = 600;
        $is_music_job = false;

        // Check if this job has the 'music' tag attached
        if ($music_tag_id && in_array($music_tag_id, $tag_ids_array)) {
            $is_music_job = true;
            $min_duration_seconds = 0; // Disable limit for music
        }

        echo "\n--- Processing Job #$job_id for '$series_title' (Type: $job_type) ---\n";
        echo "Tags: " . implode(', ', $tag_ids_array) . ($is_music_job ? " [Music Detected - No Duration Limit, No FB Post]" : " [Limit: 10 mins]") . "\n";
        
        $pdo->beginTransaction();
        
        // --- NEW LOGGING COUNTERS ---
        $job_added_count = 0;
        $job_skipped_existing = 0;
        $job_skipped_duration = 0;
        $job_skipped_junk = 0;

        try {
            // --- JOB ROUTER ---
            if ($job_type == 'youtube' || $job_type == 'youtube_channel') {
                #region YouTube Logic
                $api_key = $youtube_api_key;
                $playlist_id = null;

                if ($job_type == 'youtube_channel') {
                    if (strpos($import_url, 'UU') === 0) {
                        $playlist_id = $import_url;
                    } else {
                        $identifier = $import_url;
                        if (strpos($import_url, 'youtube.com/') !== false || strpos($import_url, 'youtu.be/') !== false) {
                            $path = parse_url($import_url, PHP_URL_PATH);
                            $path_parts = explode('/', trim($path, '/'));
                            $last = end($path_parts);
                            if ($path_parts[0] == 'channel') $identifier = $path_parts[1];
                            else $identifier = $last;
                        }
                        
                        if (substr($identifier, 0, 2) !== 'UC' && substr($identifier, 0, 1) !== '@') {
                            $identifier = '@' . $identifier;
                        }

                        $chan_params = "part=contentDetails&key=" . urlencode($api_key);
                        if (substr($identifier, 0, 2) == 'UC') $chan_params .= "&id=" . urlencode($identifier);
                        else $chan_params .= "&forHandle=" . urlencode($identifier);
                        
                        $chan_url = "https://www.googleapis.com/youtube/v3/channels?" . $chan_params;
                        $chan_json = fetchUrlContent($chan_url);
                        $chan_data = json_decode($chan_json, true);
                        
                        if (!empty($chan_data['items'])) {
                            $playlist_id = $chan_data['items'][0]['contentDetails']['relatedPlaylists']['uploads'];
                        } else {
                             $search_url = "https://www.googleapis.com/youtube/v3/search?part=snippet&type=channel&q=" . urlencode($identifier) . "&key=" . urlencode($api_key);
                             $search_json = fetchUrlContent($search_url);
                             $search_data = json_decode($search_json, true);
                             if (!empty($search_data['items'])) {
                                 $channel_id = $search_data['items'][0]['id']['channelId'];
                                 $chan_url = "https://www.googleapis.com/youtube/v3/channels?part=contentDetails&id=" . urlencode($channel_id) . "&key=" . urlencode($api_key);
                                 $chan_json = fetchUrlContent($chan_url);
                                 $chan_data = json_decode($chan_json, true);
                                 if (!empty($chan_data['items'])) $playlist_id = $chan_data['items'][0]['contentDetails']['relatedPlaylists']['uploads'];
                             }
                        }
                    }
                    if (!$playlist_id) echo "Error: Could not find channel/playlist for '$import_url'. Skipping.\n";
                }
                elseif ($job_type == 'youtube') {
                    $playlist_id = $import_url;
                    if (filter_var($import_url, FILTER_VALIDATE_URL)) {
                        $query_string = parse_url($import_url, PHP_URL_QUERY);
                        parse_str($query_string, $query_params);
                        if (isset($query_params['list'])) $playlist_id = $query_params['list'];
                    }
                }

                if ($playlist_id) {
                    $nextPageToken = '';
                    do {
                        $api_url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId=" . urlencode($playlist_id) . "&key=" . urlencode($api_key) . "&maxResults=50&pageToken=" . $nextPageToken;
                        $json_data = fetchUrlContent($api_url);
                        $data = json_decode($json_data, true);

                        if (isset($data['error'])) {
                            echo "API Error: " . $data['error']['message'] . "\n";
                            break;
                        }
                        
                        if (empty($data['items'])) {
                            if (empty($nextPageToken)) echo "No videos found.\n";
                            break; 
                        }

                        $video_ids = [];
                        foreach ($data['items'] as $item) $video_ids[] = $item['snippet']['resourceId']['videoId'];
                        $details_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,liveStreamingDetails,contentDetails&id=" . implode(',', $video_ids) . "&key=" . urlencode($api_key);
                        $details_json = fetchUrlContent($details_url);
                        $details_data = json_decode($details_json, true);

                        $video_details_map = [];
                        if (!empty($details_data['items'])) {
                            foreach ($details_data['items'] as $video_item) $video_details_map[$video_item['id']] = $video_item;
                        }

                        foreach ($data['items'] as $item) {
                            $snippet = $item['snippet'];
                            $video_id = $snippet['resourceId']['videoId'];
                            $media_url = "https://www.youtube.com/watch?v=" . $video_id;
                            $title = $snippet['title'];

                            if ($title == 'Deleted video' || $title == 'Private video') { 
                                $job_skipped_junk++; 
                                continue; 
                            }
                            
                            // --- DURATION CHECK (SMART) ---
                            if (isset($video_details_map[$video_id]['contentDetails']['duration'])) {
                                try {
                                    $interval = new DateInterval($video_details_map[$video_id]['contentDetails']['duration']);
                                    $seconds = $interval->h * 3600 + $interval->i * 60 + $interval->s;
                                    
                                    if ($seconds < $min_duration_seconds) {
                                        $job_skipped_duration++; 
                                        echo "Skipped (Too Short - {$seconds}s): $title\n";
                                        continue; 
                                    }
                                } catch (Exception $e) {}
                            }

                            // --- VERBOSE DUPLICATE CHECK ---
                            $check_stmt->execute([$media_url]); 
                            $existing_id = $check_stmt->fetchColumn(); 
                            if ($existing_id) { 
                                echo "Skipping (Existing): ID #$existing_id for URL: $media_url\n";
                                $job_skipped_existing++; 
                                continue; 
                            }
                            // --- END DUPLICATE CHECK ---

                            $description = $snippet['description'];
                            $thumbnail_url = $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['medium']['url'] ?? $snippet['thumbnails']['default']['url'] ?? 'https://placehold.co/600x400/1f2937/7f9cf5?text=No+Image';
                            $publish_date_iso = $snippet['publishedAt'];
                            if (isset($video_details_map[$video_id]['liveStreamingDetails']['scheduledStartTime'])) {
                                $publish_date_iso = $video_details_map[$video_id]['liveStreamingDetails']['scheduledStartTime'];
                            }
                            $publish_date = date('Y-m-d H:i:s', strtotime($publish_date_iso));

                            // Insert basic episode data
                            $insert_stmt->execute([$series_id, $title, $description, $media_url, $thumbnail_url, 'YouTube', $publish_date]);
                            $new_episode_id = $pdo->lastInsertId();
                            
                            // Generate unique slug & enqueue transcription automatically
                            updateSlug($pdo, $new_episode_id, $title, $publish_date);

                            $job_added_count++;
                            // --- UPDATED: Pass 'is_music' flag to storage array ---
                            $newly_added_episodes[] = [
                                'title' => $title, 
                                'series_title' => $series_title, 
                                'url' => $media_url, 
                                'thumbnail_url' => $thumbnail_url, 
                                'series_id' => $series_db_id,
                                'is_music' => $is_music_job // Store the flag
                            ]; 
                            foreach ($tag_ids_array as $tag_id) $tag_stmt->execute([$new_episode_id, $tag_id]);
                        }
                        
                        $nextPageToken = isset($data['nextPageToken']) ? $data['nextPageToken'] : '';
                    } while (!empty($nextPageToken));
                }
                #endregion
            
            } elseif ($job_type == 'tvmi' || $job_type == 'one') {
                #region TVMi and ONE Logic
                $series_page_html = fetchUrlContent($import_url);
                
                if (!$series_page_html || strlen($series_page_html) < 100) {
                    throw new Exception("Could not fetch series page (Blocked or Empty). Len: " . strlen($series_page_html));
                }

                $doc = new DOMDocument();
                @$doc->loadHTML($series_page_html);
                $links = $doc->getElementsByTagName('a');
                
                $episode_urls = [];
                foreach ($links as $link) {
                    $href = $link->getAttribute('href');
                    if (strpos($href, '/episode/') !== false || strpos($href, '/episodes/') !== false || strpos($href, '/follow/') !== false || strpos($href, '/webisode/') !== false) {
                        if (strpos($href, 'http') !== 0) {
                            if (substr($href, 0, 1) == '/') {
                                $parsed_url = parse_url($import_url);
                                $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
                                $href = $base_url . $href;
                            } else {
                                $href = rtrim($import_url, '/') . '/' . $href;
                            }
                        }
                        $episode_urls[$href] = 1;
                    }
                }
                
                $episode_urls = array_keys($episode_urls); 
                echo "Found " . count($episode_urls) . " potential episode link(s).\n";

                foreach ($episode_urls as $media_url) {
                    // --- VERBOSE DUPLICATE CHECK ---
                    $check_stmt->execute([$media_url]);
                    $existing_id = $check_stmt->fetchColumn(); 
                    if ($existing_id) { 
                        echo "Skipping (Existing): ID #$existing_id for URL: $media_url\n";
                        $job_skipped_existing++; 
                        continue; 
                    }
                    // --- END DUPLICATE CHECK ---

                    $details = scrapeEpisodePage($media_url);
                    
                    if (!$details) {
                         echo "Failed to scrape details for: $media_url\n";
                         continue;
                    }
                    if (empty($details['title'])) {
                         echo "Skipping (Empty Title) for: $media_url\n";
                         continue;
                    }

                    if ($details && !empty($details['title'])) {
                        if ($details['title'] == 'Deleted video' || $details['title'] == 'Private video') { 
                            $job_skipped_junk++; 
                            continue; 
                        }

                        $platform_name = ($job_type == 'one') ? 'ONE' : 'TVMi';
                        $insert_stmt->execute([$series_id, $details['title'], $details['description'], $media_url, $details['thumbnail_url'], $platform_name, $details['publish_date']]);
                        $new_episode_id = $pdo->lastInsertId();
                        
                        // Generate unique slug & enqueue transcription automatically
                        updateSlug($pdo, $new_episode_id, $details['title'], $details['publish_date']);

                        $job_added_count++;
                        // --- UPDATED: Pass 'is_music' flag to storage array ---
                        $newly_added_episodes[] = [
                            'title' => $details['title'], 
                            'series_title' => $series_title, 
                            'url' => $media_url, 
                            'thumbnail_url' => $details['thumbnail_url'],
                            'series_id' => $series_db_id,
                            'is_music' => $is_music_job // Store the flag
                        ]; 
                        foreach ($tag_ids_array as $tag_id) $tag_stmt->execute([$new_episode_id, $tag_id]);
                    }
                }
                #endregion
            }

            $pdo->commit();
            $total_episodes_added += $job_added_count;
            
            // --- IMPROVED LOG SUMMARY ---
            echo "Import Complete: Added $job_added_count. Existing: $job_skipped_existing. Too Short: $job_skipped_duration. Junk: $job_skipped_junk.\n";
            
            $update_job_stmt->execute([$job['id']]);
            echo "Job #{$job['id']} marked as checked.\n";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo "ERROR processing Job #$job_id: " . $e->getMessage() . "\n";
        }
    }

    // --- NEW: Filter out music episodes before posting to FB ---
    $episodes_to_post = array_filter($newly_added_episodes, function($ep) {
        return empty($ep['is_music']); // Keep episode ONLY if 'is_music' is falsy
    });
    // Re-index array
    $episodes_to_post = array_values($episodes_to_post);
    $count_to_post = count($episodes_to_post);

    // --- Use the filtered count for FB posting logic ---
    if ($count_to_post > 0) {
        $fb_message = "";
        $fb_image = "";
        
        if ($count_to_post == 1) {
            $first_episode = $episodes_to_post[0];
            $fb_message_template = $settings['fb_post_template_single'];
            
            // Inject all variables into the template
            $search = ['{title}', '{series}', '{url}', '{series_url}'];
            $replace = [
                $first_episode['title'], 
                $first_episode['series_title'], 
                $first_episode['url'], 
                "https://il-hub.com/series.php?series_id={$first_episode['series_id']}" // Use new series link
            ];

            $fb_message = str_replace($search, $replace, $fb_message_template);
            $fb_image = $first_episode['thumbnail_url']; 

        } else {
            $fb_message_template = $settings['fb_post_template_multi'];
            // Note: We say "X episodes added" (from the filtered list)
            $fb_message = str_replace(['{count}'], [$count_to_post], $fb_message_template);
            
            // Build the list of episodes from the filtered list
            foreach ($episodes_to_post as $ep) {
                // Link to the specific episode video, but keep the series context in the text
                $fb_message .= "- '{$ep['title']}' from {$ep['series_title']}\n";
            }
            $fb_message .= "\nCheck them all out at https://il-hub.com";
            $fb_image = $episodes_to_post[0]['thumbnail_url']; // Use first episode's image for the photo post
        }
        
        $fb_status = postToFacebook($fb_message, $fb_image, $fb_page_id, $fb_page_token);
        echo $fb_status . "\n";
    } else {
        if ($total_episodes_added > 0) {
            echo "Episodes added, but all were music (or otherwise filtered), so NO Facebook post was sent.\n";
        }
    }

    echo "Cron Job Finished: " . date('Y-m-d H:i:s') . "\n";
    $summary = ob_get_clean();
    writeToLog($pdo, $summary, 'Success');
    echo $summary;

} catch (Exception $e) {
    $error_message = $e->getMessage();
    echo "\n--- CRON FAILED ---\n";
    echo $error_message . "\n";
    $summary = ob_get_clean();
    writeToLog($pdo, $summary, 'Failure');
    echo $summary;
}
?>
