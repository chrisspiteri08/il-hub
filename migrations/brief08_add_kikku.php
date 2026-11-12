<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/db_config.php';
require dirname(__DIR__) . '/slug_helpers.php';
require dirname(__DIR__) . '/automation_helpers.php';

$title = 'Kikku';
$slug = 'kikku';
$channelHandle = '@KikkuMalti';
$channelUrl = 'https://www.youtube.com/@KikkuMalti';
$imageUrl = 'https://yt3.ggpht.com/7hr27IwoxIBGPCd0ssGvMbVTHtO5KbqdN88KHPWt1vkWHLESybp30oQzQE_JwWGNqAUEhWye_Q=s800-c-k-c0x00ffffff-no-rj';
$description = <<<'TEXT'
Merħba fuq Tal-linja ta' Kikku! Għajjejt tisma’ l-istess diski bl-Ingliż? Kikku qiegħed hawn biex jibdel dan kollu! Ingħaqad miegħi u sħabi f’vjaġġ mużikali madwar il-gżejjer Maltin.

F’dan ic-channel, se niskopru diski kollha bil-Malti! Minn diski tradizzjonali li konna nkantaw aħna meta konna żgħar, sa verżjonijiet ġodda u divertenti ta’ dawk li t-tfal iħobbu llum.

Għaliex għandek tagħżel lili?
✅ Malti Pur u Sħiħ: Nagħżlu l-Malti minflok it-taħlita ("Maltenglish") biex ngħinu l-iżvilupp tal-lingwa f’uliedna.
✅ Kultura u Nostalġija: Jien nħobb dak kollu li hu Malti!
✅ Tagħlim bi pjaċir: Mużika li tqanqal il-ferħ u l-moviment, waqt li ssaħħaħ il-vokabolarju.

🎵 Abbona (Subscribe) u tkunx l-aħħar li tirkeb!
Titlifx l-ebda vidjow ġdid! Agħfas il-qanpiena 🔔 biex tkun taf meta noħroġ diska ġdida!

Grazzi talli qed tgħinna nżommu l-ilsien Malti ħaj f'darna! ❤️🇲🇹
#KikkuMalta #MaltiGħatTfal #KanzunettiMaltin #Edukazzjoni #MaltaKids #MalteseLanguage
TEXT;

$tagStmt = $pdo->prepare("SELECT id FROM tags WHERE slug = 'children' OR name = 'children' LIMIT 1");
$tagStmt->execute();
$childrenTagId = (int) $tagStmt->fetchColumn();
if ($childrenTagId < 1) {
    throw new RuntimeException('Children tag is missing.');
}

$findSeries = $pdo->prepare('SELECT id, title, slug FROM series WHERE main_url = ? OR slug = ? ORDER BY main_url = ? DESC LIMIT 1');
$findSeries->execute([$channelUrl, $slug, $channelUrl]);
$series = $findSeries->fetch(PDO::FETCH_ASSOC);

if (!$series) {
    $insert = $pdo->prepare('INSERT INTO series (title, description, main_url, series_image, slug) VALUES (?, ?, ?, ?, ?)');
    $insert->execute([$title, $description, $channelUrl, $imageUrl, $slug]);
    $series = ['id' => (int) $pdo->lastInsertId(), 'title' => $title, 'slug' => $slug];
} else {
    $update = $pdo->prepare('UPDATE series SET title = ?, description = ?, main_url = ?, series_image = ?, slug = ? WHERE id = ?');
    $update->execute([$title, $description, $channelUrl, $imageUrl, $slug, (int) $series['id']]);
    $series['title'] = $title;
    $series['slug'] = $slug;
}

$jobId = createAutomation($pdo, (int) $series['id'], 'youtube_channel', $channelHandle, [$childrenTagId], 1);
$ensureTag = $pdo->prepare('INSERT IGNORE INTO import_tags (import_id, tag_id) VALUES (?, ?)');
$ensureTag->execute([$jobId, $childrenTagId]);

echo implode("\t", [(int) $series['id'], $jobId, $series['slug'], $series['title']]) . PHP_EOL;
