<?php

function initializeLocalDemoDatabase(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
        setting_name TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL DEFAULT ""
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS series (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT,
        description TEXT,
        series_image TEXT
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS episodes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        series_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        slug TEXT,
        description TEXT,
        media_url TEXT NOT NULL,
        thumbnail_url TEXT,
        platform TEXT,
        publish_date TEXT
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        display_order INTEGER NOT NULL DEFAULT 0
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS episode_tags (
        episode_id INTEGER NOT NULL,
        tag_id INTEGER NOT NULL,
        PRIMARY KEY (episode_id, tag_id)
    )');

    if ((int) $pdo->query('SELECT COUNT(*) FROM episodes')->fetchColumn() > 0) {
        return;
    }

    $pdo->beginTransaction();
    $setting = $pdo->prepare('INSERT INTO settings (setting_name, setting_value) VALUES (?, ?)');
    foreach ([
        ['login_password', 'local-demo-only'],
        ['youtube_api_key', 'local-demo-only'],
        ['google_analytics_id', ''],
    ] as $row) {
        $setting->execute($row);
    }

    $tagStatement = $pdo->prepare('INSERT INTO tags (name, display_order) VALUES (?, ?)');
    foreach ([['politics', 1], ['sports', 2], ['lifestyle', 3], ['comedy', 4], ['music', 5], ['business', 6]] as $tag) {
        $tagStatement->execute($tag);
    }

    $seriesStatement = $pdo->prepare('INSERT INTO series (title, slug, description, series_image) VALUES (?, ?, ?, ?)');
    $seriesRows = [
        ['Ta\' Kuljum', 'ta-kuljum', 'Diskussjonijiet friski dwar dak li qed jiġri madwarna.', 'https://images.unsplash.com/photo-1589903308904-1010c2294adc?auto=format&fit=crop&w=900&q=80'],
        ['Il-Logħba', 'il-loghba', 'Aħbarijiet, analiżi u stejjer mill-isport Malti.', 'https://images.unsplash.com/photo-1579952363873-27f3bade9f55?auto=format&fit=crop&w=900&q=80'],
        ['Stejjer Maltin', 'stejjer-maltin', 'Nies, ideat u esperjenzi mill-komunità Maltija.', 'https://images.unsplash.com/photo-1519682337058-a94d519337bc?auto=format&fit=crop&w=900&q=80'],
        ['Nibnu Negozju', 'nibnu-negozju', 'Konversazzjonijiet prattiċi ma\' intraprendituri lokali.', 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=900&q=80'],
    ];
    foreach ($seriesRows as $series) {
        $seriesStatement->execute($series);
    }

    $episodes = [
        [1, 'X\'qed ibiddel il-ħajja ta\' kuljum?', 'xqed-ibiddel-il-hajja', 'Diskussjoni dwar it-teknoloġija, ix-xogħol u l-ħajja moderna f\'Malta.', 'https://www.youtube.com/watch?v=jNQXAC9IVRw',  'https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=900&q=80', 'YouTube', '2026-08-13', 'politics'],
        [2, 'Staġun ġdid, aspettattivi ġodda', 'stagun-gdid', 'Analiżi qabel il-bidu tal-istaġun u l-istejjer li għandna nsegwu.', 'https://www.youtube.com/watch?v=jNQXAC9IVRw', 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=900&q=80', 'YouTube', '2026-08-12', 'sports'],
        [3, 'Minn idea żgħira għal proġett kbir', 'minn-idea-ghal-progett', 'Storja ta\' kreattività, perseveranza u komunità.', 'https://example.com/demo-episode', 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=900&q=80', 'External', '2026-08-11', 'lifestyle'],
        [4, 'Kif tibni marka li tibqa\' mfakkra', 'kif-tibni-marka', 'Pariri prattiċi dwar identità, komunikazzjoni u tkabbir.', 'https://www.youtube.com/watch?v=jNQXAC9IVRw', 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=900&q=80', 'YouTube', '2026-08-10', 'business'],
        [1, 'Konversazzjoni dwar il-kultura Maltija', 'kultura-maltija', 'Ħarsa lejn il-lingwa, l-arti u l-identità tagħna.', 'https://www.youtube.com/watch?v=jNQXAC9IVRw', 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=80', 'YouTube', '2026-08-09', 'lifestyle'],
        [2, 'Lil hinn mir-riżultat', 'lil-hinn-mir-rizultat', 'L-istejjer umani wara l-atleti u t-timijiet.', 'https://example.com/sports-demo', 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=900&q=80', 'External', '2026-08-08', 'sports'],
        [3, 'Il-mużika li trabbejna magħha', 'il-muzika-li-trabbejna', 'Vjaġġ nostalgiku ma\' kanzunetti u artisti Maltin.', 'https://www.youtube.com/watch?v=jNQXAC9IVRw', 'https://images.unsplash.com/photo-1516280440614-37939bbacd81?auto=format&fit=crop&w=900&q=80', 'YouTube', '2026-08-07', 'music'],
        [4, 'L-ewwel sena fin-negozju', 'l-ewwel-sena-fin-negozju', 'X\'tgħallmu fundaturi lokali mill-ewwel tnax-il xahar tagħhom.', 'https://example.com/business-demo', 'https://images.unsplash.com/photo-1524758631624-e2822e304c36?auto=format&fit=crop&w=900&q=80', 'External', '2026-08-06', 'business'],
    ];
    $episodeStatement = $pdo->prepare('INSERT INTO episodes (series_id, title, slug, description, media_url, thumbnail_url, platform, publish_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $episodeTagStatement = $pdo->prepare('INSERT INTO episode_tags (episode_id, tag_id) VALUES (?, (SELECT id FROM tags WHERE name = ?))');
    foreach ($episodes as $episode) {
        $tag = array_pop($episode);
        $episodeStatement->execute($episode);
        $episodeTagStatement->execute([(int) $pdo->lastInsertId(), $tag]);
    }
    $pdo->commit();
}
