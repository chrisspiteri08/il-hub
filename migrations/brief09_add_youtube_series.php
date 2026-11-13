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

$bovImage = 'https://yt3.ggpht.com/LLyvaSbtZmLrajIm7mDSq4KjQBXjigZAG6Qw7_a2bheJ-YtvojGOfPrLH1gNACSSLxXDYFPm5hM=s800-c-k-c0x00ffffff-no-rj';
$shows = [
    ["Illum Ma' Steph", 'illum-ma-steph', 'Il-programm magazin u ta’ diskussjoni ta’ kuljum fuq TVM, ippreżentat minn Stephanie Spiteri.', 'https://www.youtube.com/@illummasteph7775', 'https://yt3.ggpht.com/ytc/AIdro_l4Nkl8Cdj2kEbRyjeXrUFu7RogpFuak2xRjlQNSNveYg=s800-c-k-c0x00ffffff-no-rj', 'youtube_channel', 'UCnZAqPgB6oTnyk07jN7W2EQ', [$podcast]],
    ["Mużika Mod Ieħor ma' Toni Sant", 'muzika-mod-iehor-ma-toni-sant', 'Podcast ta’ kull ġimgħa ma’ artisti u performers f’Malta jew minn Malta.', 'https://www.youtube.com/@tonisant', 'https://yt3.ggpht.com/ytc/AIdro_lL3r79yxjuWx7EbFFgtAFsB6K1xiamb8LR-PB3w3ZCFA=s800-c-k-c0x00ffffff-no-rj', 'youtube', 'PLmGDQp3v97O4vdJMq4AdCGl7sYNcTFqGP', [$podcast, $tags['music']]],
    ['Malta Pop Podcast', 'malta-pop-podcast', 'Conversations about pop culture, films, games and fandom in Malta.', 'https://www.youtube.com/playlist?list=PLr_i6jwc5GMPZBAEUsTMTzhvEL-uHT6mt', 'https://yt3.ggpht.com/3zIk37kBzsvpIlOzDQh-vq74oSSgCIFNmaiHw0EM-4gKfpYccbW13SMjceWefg02eyOfn05fuQ=s800-c-k-c0x00ffffff-no-rj', 'youtube', 'PLr_i6jwc5GMPZBAEUsTMTzhvEL-uHT6mt', [$podcast]],
    ['Money Talk', 'money-talk', 'A practical series about money, financial literacy and personal finance from Bank of Valletta and Times of Malta.', 'https://www.youtube.com/playlist?list=PLH6EpIGtQRZJtlzmT5VQfiodgsIvIoqrl', $bovImage, 'youtube', 'PLH6EpIGtQRZJtlzmT5VQfiodgsIvIoqrl', [$podcast]],
    ['The Smart Money Hour', 'the-smart-money-hour', 'Taħditiet bil-Malti dwar il-flus, l-investiment u deċiżjonijiet finanzjarji aktar intelliġenti.', 'https://www.youtube.com/playlist?list=PLepTDDacrazY', $bovImage, 'youtube', 'PLepTDDacrazY', [$podcast]],
    ["L-Għaliex", 'l-ghaliex', 'Serje ta’ diskussjonijiet ma’ Matthew Bartolo li tistaqsi l-għaliex wara suġġetti ta’ kuljum.', 'https://www.youtube.com/playlist?list=PL3AQBKMYD0-HjdVYnRvdHNnhTeQmeqw2D', 'https://yt3.ggpht.com/5LdohYqVEg3BlK8HyspfS-1sXU2OsG-VVVMTs6Jxay1mblCVD18iK4izXszVF23OXnhIJURcrg=s800-c-k-c0x00ffffff-no-rj', 'youtube', 'PL3AQBKMYD0-HjdVYnRvdHNnhTeQmeqw2D', [$podcast]],
    ['NETLIVE', 'netlive', 'Diskussjonijiet u aġġornamenti politiċi minn NET Television.', 'https://www.youtube.com/playlist?list=PLI8U7li71jQoN0TyFDnR1WpJ6nElYbWOX', 'https://yt3.ggpht.com/8tVmwXUFbpCwV_T5e1oX5WmlaZW1oFDXXjgLxlSnC8iHZc1Bx9EhqQmQMMf31TjMoIPpGHVs=s800-c-k-c0x00ffffff-no-rj', 'youtube', 'PLI8U7li71jQoN0TyFDnR1WpJ6nElYbWOX', [$podcast, $tags['politics']]],
];

$find = $pdo->prepare('SELECT id, slug FROM series WHERE main_url = ? OR slug = ? OR LOWER(title) = LOWER(?) ORDER BY main_url = ? DESC LIMIT 1');
$insert = $pdo->prepare('INSERT INTO series (title, description, main_url, series_image, slug) VALUES (?, ?, ?, ?, ?)');
$slugTaken = $pdo->prepare('SELECT COUNT(*) FROM series WHERE slug = ?');

foreach ($shows as [$title,$wantedSlug,$description,$mainUrl,$image,$jobType,$source,$tagIds]) {
    $find->execute([$mainUrl,$wantedSlug,$title,$mainUrl]);
    $series = $find->fetch(PDO::FETCH_ASSOC);
    if (!$series) {
        $slug = make_slug($wantedSlug);
        $base = $slug;
        $suffix = 2;
        while (true) {
            $slugTaken->execute([$slug]);
            if (!(int) $slugTaken->fetchColumn()) break;
            $slug = rtrim(substr($base, 0, 86), '-') . '-' . $suffix++;
        }
        $insert->execute([$title,$description,$mainUrl,$image,$slug]);
        $series = ['id'=>(int)$pdo->lastInsertId(),'slug'=>$slug];
    }
    $jobId = createAutomation($pdo, (int)$series['id'], $jobType, $source, array_values(array_unique(array_filter($tagIds))), 6);
    echo implode("\t", [(int)$series['id'],$jobId,$series['slug'],$title]) . PHP_EOL;
}
