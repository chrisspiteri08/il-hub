<?php

if (!function_exists('make_slug')) {
    function make_slug(string $text): string
    {
        $text = strtr($text, [
            'ċ' => 'c', 'Ċ' => 'C',
            'ġ' => 'g', 'Ġ' => 'G',
            'ħ' => 'h', 'Ħ' => 'H',
            'ż' => 'z', 'Ż' => 'Z',
            'à' => 'a', 'À' => 'A',
            'è' => 'e', 'È' => 'E',
            'ì' => 'i', 'Ì' => 'I',
            'ò' => 'o', 'Ò' => 'O',
            'ù' => 'u', 'Ù' => 'U',
        ]);

        $text = preg_replace('/\s*-\s*TVMi\.mt\b|\bTVMi\.mt\b/iu', ' ', $text) ?? $text;
        $text = str_replace(["'", '’'], '', $text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim(preg_replace('/-+/', '-', $text) ?? '', '-');

        if (strlen($text) <= 90) {
            return $text;
        }

        $short = substr($text, 0, 90);
        $lastBoundary = strrpos($short, '-');
        if ($lastBoundary !== false) {
            $short = substr($short, 0, $lastBoundary);
        }

        return trim($short, '-');
    }
}
