<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db_config.php';
require dirname(__DIR__) . '/slug_helpers.php';
require dirname(__DIR__) . '/automation_helpers.php';

$tags = [];
foreach ($pdo->query('SELECT id, name, slug FROM tags') as $tag) {
    $tags[$tag['slug']] = (int) $tag['id'];
    $tags[$tag['name']] = (int) $tag['id'];
}
$podcast = $tags['diskussjonijiet-u-podcasts'] ?? 0;
if (!$podcast) throw new RuntimeException('Diskussjonijiet u Podcasts tag is missing.');

function brief11Meta(string $url): array {
    $context = stream_context_create(['http' => ['timeout' => 20, 'user_agent' => 'il-hub importer/1.0']]);
    $html = @file_get_contents($url, false, $context);
    if (!$html) return ['', '', ''];
    $doc = new DOMDocument(); @$doc->loadHTML($html);
    $values = ['og:title'=>'', 'og:description'=>'', 'og:image'=>''];
    foreach ($doc->getElementsByTagName('meta') as $meta) {
        $property = $meta->getAttribute('property');
        if (array_key_exists($property, $values)) $values[$property] = trim($meta->getAttribute('content'));
    }
    return [$values['og:title'], $values['og:description'], $values['og:image']];
}

function brief11Series(PDO $pdo, string $title, string $wantedSlug, string $description, string $mainUrl, string $image): int {
    $find = $pdo->prepare('SELECT id FROM series WHERE main_url = ? OR slug = ? OR LOWER(title) = LOWER(?) ORDER BY main_url = ? DESC LIMIT 1');
    $find->execute([$mainUrl, $wantedSlug, $title, $mainUrl]);
    $id = $find->fetchColumn();
    if ($id) return (int) $id;
    $taken = $pdo->prepare('SELECT COUNT(*) FROM series WHERE slug = ?');
    $slug = make_slug($wantedSlug); $base = $slug; $suffix = 2;
    while (true) { $taken->execute([$slug]); if (!(int)$taken->fetchColumn()) break; $slug = rtrim(substr($base, 0, 86), '-') . '-' . $suffix++; }
    $insert = $pdo->prepare('INSERT INTO series (title, description, main_url, series_image, slug) VALUES (?, ?, ?, ?, ?)');
    $insert->execute([$title, $description, $mainUrl, $image, $slug]);
    return (int) $pdo->lastInsertId();
}

$netShows = [
    ['Analiżi',33], ['Flusek',9], ['Hello! Min Hemm?',315], ['JienInt',18], ['Magħkom',21],
    ['Meander',3], ['Min-Nies Għan-Nies Podcast',351], ['NET AM',350], ['NETLIVE',15],
    ['Nifs Gdid',355], ['Opinjoni',27], ['Perspettivi',107], ['Profili',20], ['Replay',37],
    ['Replay Podcast',362], ['MZPN Unfiltered',349], ['Traċċi',373], ['Viva Malta',372],
    ['Kuntatt',195], ['Issues',234], ['Feedback',196],
];
$netJobIds = [];
foreach ($netShows as [$title, $sourceId]) {
    $url = 'https://netondemand.mt/series/' . $sourceId;
    [$officialTitle, $description, $image] = brief11Meta($url);
    if ($officialTitle !== '') $title = $officialTitle;
    $tagIds = [$podcast];
    if (in_array($sourceId, [37,362], true) && !empty($tags['sports'])) $tagIds[] = $tags['sports'];
    if (in_array($sourceId, [15,27,33,107,195,196,234], true) && !empty($tags['politics'])) $tagIds[] = $tags['politics'];
    $seriesId = brief11Series($pdo, $title, make_slug($title), $description, $url, $image);

    if ($sourceId === 15) {
        $findOld = $pdo->prepare("SELECT id FROM automated_imports WHERE series_id = ? AND job_type = 'youtube' ORDER BY id LIMIT 1");
        $findOld->execute([$seriesId]);
        $oldJob = $findOld->fetchColumn();
        if ($oldJob) {
            $pdo->prepare("UPDATE automated_imports SET job_type='netondemand', import_url_or_id=?, check_interval_hours=6, last_checked_at=NULL WHERE id=?")->execute([$url, $oldJob]);
            $netJobIds[] = (int) $oldJob;
            echo "NET\t$seriesId\t$oldJob\t$title (upgraded)\n";
            continue;
        }
    }
    $jobId = createAutomation($pdo, $seriesId, 'netondemand', $url, array_values(array_unique($tagIds)), 6);
    $netJobIds[] = $jobId;
    echo "NET\t$seriesId\t$jobId\t$title\n";
}

$oneShows = [
    [665,'Bil-Fatti',"Il-programm ta' ġrajjiet kurrenti b'diversi mistiedna politiċi u oħrajn li jiddiskutu l-attwalità politika ta' kuljum",'https://play.one.com.mt/media/cache/thumb/305040.jpg'],
    [900,'Kwarta',"Programm ta' ġrajjiet kurrenti ma' Andre Mizzi",'https://play.one.com.mt/media/cache/thumb/395595.jpg'],
    [212,'Awla',"Il-programm li jinvestiga u jiddiskuti suġġetti kurrenti mill-lenti t'esperti u esperjenzi umani",'https://play.one.com.mt/media/cache/thumb/140368.jpg'],
    [937,'Ngħidu Kollox',"Programm ma' Nolan Camilleri",'https://play.one.com.mt/media/cache/thumb/398128.jpg'],
    [94,'Paperscan',"L-analiżi tal-gazzetti ppubblikati l-Ħadd ma' diversi mistiedna",'https://play.one.com.mt/media/cache/thumb/191107.jpg'],
    [604,'Sibt il-Punt',"Ġrajjiet kurrenti, politika u attwalità f'diskussjoni fuq ir-radju u t-televiżjoni",'https://play.one.com.mt/media/cache/thumb/305550.jpg'],
    [668,'Benefiċċji Għalik','Ħarsa lejn benefiċċji soċjali differenti u kif dawn jgħinu lill-familji kollha.','https://play.one.com.mt/media/cache/thumb/214804.jpg'],
    [106,'L-Argument',"Id-diskussjoni sportiva prinċipali dwar il-futbol lokali",'https://play.one.com.mt/media/cache/thumb/140664.jpg'],
    [793,'il-Kliem bħaċ-Ċirasa',"Diskursata ma' Dorian Cassar fejn il-mistiedna jkunu lesti jitkellmu fuq kollox",'https://play.one.com.mt/media/cache/thumb/303335.jpg'],
];
$oneJobIds = [];
foreach ($oneShows as [$sourceId,$title,$description,$image]) {
    $mainUrl = 'https://play.one.com.mt/tv';
    $tagIds = [$podcast];
    if ($sourceId === 106 && !empty($tags['sports'])) $tagIds[] = $tags['sports'];
    if (in_array($sourceId, [665,900,212,94,604], true) && !empty($tags['politics'])) $tagIds[] = $tags['politics'];
    if ($sourceId === 668 && !empty($tags['taghrif-socjali'])) $tagIds[] = $tags['taghrif-socjali'];
    $seriesId = brief11Series($pdo, $title, make_slug($title), $description, $mainUrl . '#series-' . $sourceId, $image);
    $jobId = createAutomation($pdo, $seriesId, 'one_play', (string)$sourceId, array_values(array_unique($tagIds)), 24);
    $oneJobIds[] = $jobId;
    echo "ONE\t$seriesId\t$jobId\t$title\n";
}

$smashShows = [
    ['Kulħadd Jgħid Tiegħu','PLJuylpJR8B2w2D7QU7q66qZWJ02ddfmTS'],
    ['Jien Nistaqsi','PLJuylpJR8B2wNlEkTc7BMr-dR-ZzGEmbc'],
    ['Il-Parlament tal-Poplu','PLJuylpJR8B2xPbH9mMaC9zv0hW2V11BHc'],
    ["30Minuta+ ma' Darren Marmara",'PLJuylpJR8B2z8QOJgQdnlz4sH8DsTNT7j'],
    ["30Minuta+ ma' Darren Marmara",'PLJuylpJR8B2xq4jww2U9UkZL_RRV1hzJv'],
    ["30Minuta+ ma' Anthony Calleja",'PLJuylpJR8B2xjRYJQXC06jLl-6jQ43bhg'],
];
$smashJobIds = [];
foreach ($smashShows as [$title,$playlist]) {
    $url = 'https://www.youtube.com/playlist?list=' . $playlist;
    $image = 'https://i.ytimg.com/vi/' . ($playlist === 'PLJuylpJR8B2w2D7QU7q66qZWJ02ddfmTS' ? 'iYXQ4lrr1ks' : 'Vf2BcLyvd5A') . '/hqdefault.jpg';
    $seriesId = brief11Series($pdo, $title, make_slug($title), 'Diskussjonijiet u intervisti storiċi minn Smash TV Malta.', $url, $image);
    $jobId = createAutomation($pdo, $seriesId, 'youtube', $playlist, [$podcast], 24);
    $smashJobIds[] = $jobId;
    echo "SMASH\t$seriesId\t$jobId\t$title\n";
}

echo 'NET_JOBS=' . implode(',', $netJobIds) . PHP_EOL;
echo 'ONE_JOBS=' . implode(',', $oneJobIds) . PHP_EOL;
echo 'SMASH_JOBS=' . implode(',', $smashJobIds) . PHP_EOL;
