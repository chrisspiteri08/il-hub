<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db_config.php';
require dirname(__DIR__) . '/slug_helpers.php';
require dirname(__DIR__) . '/automation_helpers.php';

$tagMap = [];
foreach ($pdo->query('SELECT id, name, slug FROM tags') as $tag) {
    $tagMap[$tag['slug']] = (int)$tag['id'];
    $tagMap[$tag['name']] = (int)$tag['id'];
}
$podcast = $tagMap['diskussjonijiet-u-podcasts'] ?? 0;
if (!$podcast) throw new RuntimeException('Diskussjonijiet u Podcasts tag is missing.');

$shows = [
 ['The FinTalks','the-fintalks','https://feed.podbean.com/thefintalks/feed.xml','Conversations about finance, business and investment in Malta.',[$podcast]],
 ['Ganado Meets','ganado-meets','https://rss.buzzsprout.com/1784951.rss','Conversations with professionals and leaders about law, business and life.',[$podcast]],
 ['Il-Podcast tal-Parlament','il-podcast-tal-parlament','https://parlament.podcast.mt/rss/podcast.rss','Awdjo uffiċjali tas-seduti tal-Parlament ta’ Malta, mhux programm ta’ intervisti.',[$podcast,$tagMap['politics']]],
 ['The Sports Desk by Times of Malta','the-sports-desk','https://feeds.captivate.fm/sports-desk-times-malta/','Times of Malta conversations and analysis covering local and international sport.',[$podcast,$tagMap['sports']]],
 ['Tenishia - Podcast','tenishia','https://feeds.soundcloud.com/users/soundcloud:users:681528/sounds.rss','DJ mixes and electronic music from Maltese duo Tenishia.',[$tagMap['music']]],
 ['The Maltiverse','the-maltiverse','https://anchor.fm/s/10eb9139c/podcast/rss','A Malta-made comedy podcast exploring stories, culture and everyday life.',[$podcast,$tagMap['comedy']]],
 ['Antenni Suspettużi','antenni-suspettuzi','https://feeds.zencastr.com/f/mey6yASk.rss','Konversazzjonijiet u stejjer b’taħlita ta’ Malti u Ingliż.',[$podcast]],
 ['Unbrandme','unbrandme','https://anchor.fm/s/ed423b1c/podcast/rss','Conversations about brands, creativity, work and personal growth.',[$podcast]],
 ['The NextGen Hospitality Podcast','the-nextgen-hospitality-podcast','https://media.rss.com/the-nextgen-hospitality-podcast/feed.xml','Conversations about innovation and the next generation of hospitality.',[$podcast]],
 ['Temple Talks: Malta','temple-talks-malta','https://rss.buzzsprout.com/944590.rss','Stories and conversations inspired by Malta’s history, culture and people.',[$podcast]],
 ['Tiegħi Tiegħek Tagħna','tieghi-tieghek-taghna','https://anchor.fm/s/d782738c/podcast/rss','Podcast bil-Malti dwar esperjenzi, ideat u stejjer li naqsmu flimkien.',[$podcast]],
 ['Il-Ħass mal-Bass','il-hass-mal-bass','https://anchor.fm/s/f5a18c90/podcast/rss','Taħditiet bil-Malti dwar kultura, mużika u l-ħajja ta’ kuljum.',[$podcast]],
 ['More Than Friends','more-than-friends','https://anchor.fm/s/b406232c/podcast/rss','Conversations about relationships, friendship and modern life.',[$podcast,$tagMap['lifestyle']]],
 ['Rahamim','rahamim','https://anchor.fm/s/4969f84/podcast/rss','Podcast bil-Malti dwar il-fidi, il-komunità u temi soċjali.',[$podcast,$tagMap['taghrif-socjali']]],
 ['il-Podkast Uncensored','il-podkast-uncensored','https://anchor.fm/s/58ba3260/podcast/rss','Konversazzjonijiet diretti u bla ċensura bil-Malti.',[$podcast]],
 ['Treasures from Malta','treasures-from-malta','https://rss.buzzsprout.com/1758509.rss','Discovering Malta’s people, places, heritage and hidden stories.',[$podcast,$tagMap['lifestyle']]],
 ['KwartaKtieb','kwartaktieb','https://anchor.fm/s/2260959c/podcast/rss','Podcast qasir bil-Malti dwar kotba, qari u letteratura.',[$podcast,$tagMap['lifestyle']]],
 ['CoROM cast','corom-cast','https://anchor.fm/s/b008e28c/podcast/rss','Conversations and research from the community of researchers on Malta.',[$podcast]],
 ['Markapaġna','markapagna','https://anchor.fm/s/b125185c/podcast/rss','Podcast bil-Malti dwar kotba, awturi u l-kultura tal-qari.',[$podcast,$tagMap['lifestyle']]],
 ['Malta en español','malta-en-espanol','https://anchor.fm/s/fa24d290/podcast/rss','Historias, consejos y conversaciones sobre la vida en Malta.',[$podcast,$tagMap['lifestyle']]],
 ['African Media Malta Podcast','african-media-malta','https://africanmediamalta-podcast.podomatic.com/rss2.xml','Stories and conversations from African communities living in Malta.',[$podcast,$tagMap['taghrif-socjali']]],
 ['MPBcast','mpbcast','https://anchor.fm/s/4f1fc170/podcast/rss','Conversas em português produzidas em Malta sobre cultura e cotidiano.',[$podcast,$tagMap['lifestyle']]],
 ['Bible Baptist Church Malta Sermon Audio','bible-baptist-church-malta','https://feed.podbean.com/biblebaptistchurchmalta/feed.xml','Weekly sermon audio from Bible Baptist Church Malta.',[$tagMap['taghrif-socjali'] ?? $podcast]],
];

function brief09FeedImage(string $url): ?string {
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20,CURLOPT_USERAGENT=>'il-hub podcast importer']);
    $raw=curl_exec($ch); curl_close($ch);
    if (!$raw) return null;
    $old=libxml_use_internal_errors(true); $xml=simplexml_load_string($raw,'SimpleXMLElement',LIBXML_NOCDATA); libxml_clear_errors(); libxml_use_internal_errors($old);
    if (!$xml) return null;
    $channel=strtolower($xml->getName())==='feed'?$xml:$xml->channel;
    $ns=$xml->getNamespaces(true);
    if (!empty($ns['itunes'])) { $a=$channel->children($ns['itunes'])->image->attributes(); if ($a && !empty($a['href'])) return (string)$a['href']; }
    if (!empty($channel->image->url)) return trim((string)$channel->image->url);
    if (strtolower($xml->getName())==='feed') foreach($channel->link as $link){$a=$link->attributes(); if((string)($a['rel']??'')==='logo' && !empty($a['href'])) return (string)$a['href'];}
    return null;
}

$find=$pdo->prepare('SELECT id,slug FROM series WHERE main_url=? OR LOWER(title)=LOWER(?) OR slug=? LIMIT 1');
$insert=$pdo->prepare('INSERT INTO series (title,description,main_url,series_image,slug) VALUES (?,?,?,?,?)');
$taken=$pdo->prepare('SELECT COUNT(*) FROM series WHERE slug=?');
foreach($shows as [$title,$wantedSlug,$feed,$description,$tagIds]){
    $find->execute([$feed,$title,$wantedSlug]); $series=$find->fetch(PDO::FETCH_ASSOC);
    if(!$series){
        $slug=make_slug($wantedSlug); $base=$slug; $n=2;
        while(true){$taken->execute([$slug]); if(!(int)$taken->fetchColumn())break; $slug=rtrim(substr($base,0,86),'-').'-'.$n++;}
        $image=brief09FeedImage($feed) ?: 'https://placehold.co/600x600/21201E/F45859?text=Podcast';
        $insert->execute([$title,$description,$feed,$image,$slug]);
        $series=['id'=>(int)$pdo->lastInsertId(),'slug'=>$slug];
    }
    $job=createAutomation($pdo,(int)$series['id'],'podcast_rss',$feed,array_values(array_unique(array_filter($tagIds))),6);
    echo implode("\t",[(int)$series['id'],$job,$series['slug'],$title]).PHP_EOL;
}
