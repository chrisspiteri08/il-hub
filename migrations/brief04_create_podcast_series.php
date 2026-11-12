<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/db_config.php';
require dirname(__DIR__) . '/slug_helpers.php';
require dirname(__DIR__) . '/automation_helpers.php';

$intervalColumn = $pdo->query("SHOW COLUMNS FROM automated_imports LIKE 'check_interval_hours'")->fetch(PDO::FETCH_ASSOC);
if (!$intervalColumn) {
    $pdo->exec('ALTER TABLE automated_imports ADD COLUMN check_interval_hours SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER last_checked_at');
}

$shows = [
    ['Negozju bla Fruntieri','PLnIJiL_SX872Qp56M9R1_2ELE4fjQA8qJ','podcast','Intervisti dwar in-negozju (Newsbook/RTK)'],
    ['Kre-attivi','PLWUrScBsTVgU','lifestyle',"Intervisti ma' nies kreattivi"],
    ['By The Way (BTW)','PLnIJiL_SX871Fb2h5008b6n_7FX5gqDVe','podcast',"Programm ta' intervisti fuq RTK103"],
    ['Minn Għajn il-Fotografu','PLaCHDoGbcl4c','lifestyle',"Intervisti ma' fotografi Maltin"],
    ['L-Intervista (Newsbook)','PLnIJiL_SX870ARaQEZo1pLyrfdDxn0ZR8','podcast',"Intervisti ta' Newsbook/RTK."],
    ['EdukaSport','PLnIJiL_SX871KLiCytjLB66iB_7DWAUCC','sports','Intervisti u edukazzjoni sportiva'],
    ['Mill-Kbir saż-Żgħir','PLeGONC02qa8s','podcast',"Serje ta' intervisti"],
    ["Tkun 'l Hawn Għaddi",'PLnIJiL_SX8719WFCXHmczr8i6VWas244Q','podcast',"Mark Azzopardi jitkellem ma' nies, artisti u kantanti (RTK103)"],
    ["Ħalli f'idi",'PLnIJiL_SX872UcUds0I0PX94jJeSrlyJB','podcast','Diskussjoni dwar temi soċjali u personali'],
    ["Kif Wasalna s'Hawn",'PLLIdhNo8zwmI','podcast',"Intervisti dwar il-vjaġġ personali tal-mistednin"],
    ['Cool Bro!','PLGaibLpafBzA','comedy','Taħdit u divertiment'],
    ['Insibu l-Boxxla','PLBaAo8hR9h0k','podcast',"It-tagħlim soċjali Kattoliku u l-attwalità"],
    ['Attwalità','PLnIJiL_SX873ioWQYdqa94Uot2UVYmsEc','politics','Diskussjoni u intervisti politiċi (Newsbook/RTK)'],
    ['Trident Football Pod','PLLlDR71Zs8N8','sports','Diskussjoni u intervisti dwar il-futbol'],
    ['Aphroconfuso','PLvRbv0Lp0vDvHim7kdh7Ac8l97ePgSmUI','lifestyle','Il-podcast tal-ġurnal letterarju Aphroconfuso'],
    ['Drinks Explained','PLGqK7hl_Mw8BpsYhPHzgwvhCf0JcJxWBW','lifestyle','Conversation podcast: each guest brings a favourite drink (Cavemen Studios)'],
    ['Bejn Xena u Oħra','PLLlxlUAar-r8','lifestyle','Nikolai Azzopardi jintervista nies mit-teatru Malti'],
    ['Bedingfield Straightforward','PLS5rJh4N7t5w','politics','Glenn Bedingfield on politics and public life in Malta'],
    ['Int fl-Ewropa','PLnIJiL_SX871w9kz9naElamnBX686e3qo','politics','Intervisti u spjegazzjonijiet dwar l-UE'],
    ['Top il-Full','PLnIJiL_SX872avZDiuLXSGy8SHo34kFr9','podcast','Intervisti (Newsbook/RTK)'],
    ['Cavemen Podcast','PLGqK7hl_Mw8CmRwJoi-Kni9Jtbe-hqAYv','comedy','Cavemen Studios conversation and comedy podcast'],
    ['Mary Iddur Iddur','PLGqK7hl_Mw8DmlmkWYx_L92dVOJAz6g0Q','podcast',"Serje ta' intervisti (Cavemen Studios)"],
];

function firstPlaylistThumbnail(string $playlistId): ?string
{
    $url = 'https://www.youtube.com/feeds/videos.xml?playlist_id=' . rawurlencode($playlistId);
    $context = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'il-hub importer/1.0']]);
    $xmlText = @file_get_contents($url, false, $context);
    if (!$xmlText) return null;
    $xml = @simplexml_load_string($xmlText);
    if (!$xml || empty($xml->entry[0])) return null;
    $media = $xml->entry[0]->children('http://search.yahoo.com/mrss/');
    $attributes = $media->group->thumbnail->attributes();
    return isset($attributes['url']) ? (string) $attributes['url'] : null;
}

$tagRows = $pdo->query('SELECT id, name FROM tags')->fetchAll(PDO::FETCH_KEY_PAIR);
$tagIds = array_change_key_case(array_flip($tagRows), CASE_LOWER);
$podcastTagId = $tagIds['diskussjonijiet u podcasts'] ?? null;
if (!$podcastTagId) throw new RuntimeException('Podcast tag is missing; run brief04_categories.php first.');

$findSeriesByUrl = $pdo->prepare('SELECT id, title, slug FROM series WHERE main_url = ? LIMIT 1');
$findSlug = $pdo->prepare('SELECT COUNT(*) FROM series WHERE slug = ?');
$insertSeries = $pdo->prepare('INSERT INTO series (title, description, main_url, series_image, slug) VALUES (?, ?, ?, ?, ?)');

foreach ($shows as [$title, $playlistId, $category, $description]) {
    $playlistUrl = 'https://www.youtube.com/playlist?list=' . $playlistId;
    $findSeriesByUrl->execute([$playlistUrl]);
    $series = $findSeriesByUrl->fetch(PDO::FETCH_ASSOC);
    if (!$series) {
        $baseSlug = $title === 'L-Intervista (Newsbook)' ? 'l-intervista-newsbook' : make_slug($title);
        $slug = $baseSlug;
        $suffix = 2;
        do {
            $findSlug->execute([$slug]);
            if (!(int) $findSlug->fetchColumn()) break;
            $slug = rtrim(substr($baseSlug, 0, 86), '-') . '-' . $suffix++;
        } while (true);
        $insertSeries->execute([$title, $description, $playlistUrl, firstPlaylistThumbnail($playlistId), $slug]);
        $series = ['id' => (int) $pdo->lastInsertId(), 'title' => $title, 'slug' => $slug];
    }

    $automationTags = [(int) $podcastTagId];
    if ($category !== 'podcast') {
        $categoryTagId = $tagIds[$category] ?? null;
        if (!$categoryTagId) throw new RuntimeException("Missing category tag: {$category}");
        $automationTags[] = (int) $categoryTagId;
    }
    $jobId = createAutomation($pdo, (int) $series['id'], 'youtube', $playlistId, $automationTags, 6);
    echo implode("\t", [$series['id'], $jobId, $series['slug'], $title]) . PHP_EOL;
}
