<?php

if (!function_exists('isHttpMediaUrl')) {
    function isHttpMediaUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') return false;
        $parts = parse_url($url);
        if (!is_array($parts)) return false;
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        return in_array($scheme, ['http', 'https'], true) && !empty($parts['host']);
    }
}

if (!function_exists('podcastMediaKind')) {
    function podcastMediaKind(?string $contentType, string $url): ?string
    {
        $contentType = strtolower(trim((string) $contentType));
        if (strpos($contentType, 'audio/') === 0) return 'audio';
        if (strpos($contentType, 'video/') === 0) return 'video';
        if ($contentType !== '') return null;

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array($extension, ['mp4', 'webm', 'mov'], true)) return 'video';
        if (in_array($extension, ['mp3', 'm4a', 'aac', 'ogg'], true)) return 'audio';
        return null;
    }
}
