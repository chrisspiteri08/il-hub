<?php
/**
 * il-hub.com Transcription API
 * Handles communication between the website and the local MacBook worker.
 */
require_once 'config_loader.php';

// Force UTF-8 encoding so Maltese characters (ħ, ċ, ġ, ż) don't turn into question marks
$pdo->exec("SET NAMES utf8mb4");

// Security check - keep this the same as the one in your Python script
$secret_key = "HUB_M4_SECRET_2026"; 
$provided_key = $_GET['key'] ?? $_POST['key'] ?? '';

if ($provided_key !== $secret_key) {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

/**
 * Helper function to convert Gemini's Markdown into clean HTML
 * This ensures headings and paragraphs look great on the website.
 */
function clean_ai_markdown($text) {
    if (empty($text)) return '';
    
    // Convert Headings (## and ###)
    $text = preg_replace('/### (.*?)(?:\r\n|\n|$)/', '<h3>$1</h3>', $text);
    $text = preg_replace('/## (.*?)(?:\r\n|\n|$)/', '<h2>$1</h2>', $text);
    
    // Convert Bold (**text**)
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    
    // Wrap orphaned text in paragraphs (split by blank lines)
    $blocks = preg_split('/\n\s*\n/', trim($text));
    $html = '';
    foreach ($blocks as $block) {
        $block = trim($block);
        if (empty($block)) continue;
        
        // If it is already an HTML tag like <h2>, just append it
        if (strpos($block, '<h') === 0) {
            $html .= $block . "\n";
        } else {
            // Convert single line breaks to <br> and wrap in <p>
            $html .= "<p>" . nl2br($block) . "</p>\n";
        }
    }
    
    return trim($html);
}

$action = $_GET['action'] ?? '';

if ($action === 'get_job') {
    // Fetches the next pending job, now including the episode description for better AI context!
    $stmt = $pdo->query("
        SELECT t.id as trans_id, t.episode_id, e.media_url, e.title, e.description 
        FROM transcriptions t
        JOIN episodes e ON t.episode_id = e.id
        WHERE t.status = 'pending'
        ORDER BY t.created_at ASC
        LIMIT 1
    ");
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($job) {
        // Mark as processing immediately
        $update = $pdo->prepare("UPDATE transcriptions SET status = 'processing' WHERE id = ?");
        $update->execute([$job['trans_id']]);
        echo json_encode($job);
    } else {
        echo json_encode(['status' => 'no_jobs']);
    }
} 

elseif ($action === 'update_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Receive the polished data or error report
    $trans_id = $_POST['trans_id'];
    $status = $_POST['status'] ?? 'completed'; // Can be 'completed' or 'failed'
    
    // Grab all the data sent by the Python script
    $raw = $_POST['raw_transcript'] ?? '';
    
    // Clean up the Markdown and turn it into web-ready HTML!
    $clean_mt = clean_ai_markdown($_POST['clean_transcript'] ?? '');
    $clean_en = clean_ai_markdown($_POST['clean_text_en'] ?? ''); 
    $summary_mt = clean_ai_markdown($_POST['summary'] ?? '');
    $summary_en = clean_ai_markdown($_POST['summary_en'] ?? '');   
    
    // New SEO fields
    $meta_title_mt = $_POST['meta_title_mt'] ?? '';
    $meta_desc_mt = $_POST['meta_desc_mt'] ?? '';
    $h1_mt = $_POST['h1_mt'] ?? '';
    $meta_title_en = $_POST['meta_title_en'] ?? '';
    $meta_desc_en = $_POST['meta_desc_en'] ?? '';
    $h1_en = $_POST['h1_en'] ?? '';

    $social_posts = $_POST['social_posts'] ?? '';

    $stmt = $pdo->prepare("
        UPDATE transcriptions 
        SET status = ?, 
            raw_transcript = ?, 
            clean_transcript = ?, 
            clean_text_en = ?, 
            summary = ?, 
            summary_en = ?,
            meta_title_mt = ?,
            meta_desc_mt = ?,
            h1_mt = ?,
            meta_title_en = ?,
            meta_desc_en = ?,
            h1_en = ?,
            social_posts = ? 
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $status, 
        $raw, 
        $clean_mt, 
        $clean_en, 
        $summary_mt, 
        $summary_en, 
        $meta_title_mt,
        $meta_desc_mt,
        $h1_mt,
        $meta_title_en,
        $meta_desc_en,
        $h1_en,
        $social_posts, 
        $trans_id
    ]);
    
    echo json_encode(['success' => $result]);
}