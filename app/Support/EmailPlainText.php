<?php

namespace App\Support;

class EmailPlainText
{
    public static function fromHtml(string $html): string
    {
        $text = preg_replace_callback(
            '/<a\b[^>]*href=((["\'])(.*?)\2)[^>]*>(.*?)<\/a>/is',
            function (array $matches): string {
                $label = trim(strip_tags($matches[4]));
                $url = html_entity_decode($matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return $label === '' ? $url : "{$label} ({$url})";
            },
            $html,
        ) ?? $html;

        $text = preg_replace('/<br\s*\/?\s*>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/(p|div|h[1-6]|li|tr|table)>/i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
