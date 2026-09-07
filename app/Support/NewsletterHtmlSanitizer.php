<?php

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class NewsletterHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'div', 'em', 'h2', 'h3', 'hr',
        'i', 'li', 'ol', 'p', 's', 'strong', 'u', 'ul',
    ];

    private const REMOVE_WITH_CONTENT = [
        'button', 'embed', 'form', 'iframe', 'input', 'math', 'object',
        'script', 'style', 'svg', 'textarea',
    ];

    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        if (! preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $html)) {
            return $this->plainTextToHtml($html);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="newsletter-sanitizer-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new DOMXPath($document))->query('//*[@id="newsletter-sanitizer-root"]')->item(0);

        if (! $root instanceof DOMElement) {
            return '';
        }

        foreach ($this->children($root) as $child) {
            $this->cleanNode($child);
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    public function hasMeaningfulContent(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\xc2\xa0", ' ', $text)) !== '';
    }

    private function cleanNode(DOMNode $node): void
    {
        if ($node instanceof DOMComment) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if (! $node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, self::REMOVE_WITH_CONTENT, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        foreach ($this->children($node) as $child) {
            $this->cleanNode($child);
        }

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            $this->unwrap($node);

            return;
        }

        $href = $tag === 'a' ? $node->getAttribute('href') : null;

        foreach (iterator_to_array($node->attributes) as $attribute) {
            $node->removeAttribute($attribute->name);
        }

        if ($tag === 'a') {
            $this->cleanLink($node, (string) $href);
        }

        $style = $this->emailStyle($tag);
        if ($style !== null) {
            $node->setAttribute('style', $style);
        }
    }

    private function cleanLink(DOMElement $link, string $href): void
    {
        $href = trim($href);
        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        if ($href === '' || ($scheme !== '' && ! in_array($scheme, ['http', 'https', 'mailto', 'tel'], true))) {
            return;
        }

        $link->setAttribute('href', $href);
        $link->setAttribute('target', '_blank');
        $link->setAttribute('rel', 'noopener noreferrer');
    }

    private function emailStyle(string $tag): ?string
    {
        return match ($tag) {
            'a' => 'color:#8c6906;text-decoration:underline',
            'blockquote' => 'margin:20px 0;padding:16px 18px;border-left:4px solid #f5b800;background:#f7f4ec;color:#4c5159',
            'div', 'p' => 'margin:0 0 16px',
            'h2' => 'margin:26px 0 12px;color:#25282d;font-size:24px;line-height:1.25',
            'h3' => 'margin:22px 0 10px;color:#25282d;font-size:19px;line-height:1.3',
            'hr' => 'margin:24px 0;border:0;border-top:1px solid #dedbd2',
            'li' => 'margin:0 0 8px',
            'ol', 'ul' => 'margin:0 0 18px;padding-left:24px',
            default => null,
        };
    }

    private function plainTextToHtml(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/', $text) ?: [$text];

        return implode('', array_map(
            fn (string $paragraph): string => '<p style="margin:0 0 16px">'.nl2br(
                htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                false,
            ).'</p>',
            $paragraphs,
        ));
    }

    /** @return array<int, DOMNode> */
    private function children(DOMNode $node): array
    {
        return iterator_to_array($node->childNodes);
    }

    private function unwrap(DOMElement $node): void
    {
        $parent = $node->parentNode;

        if ($parent === null) {
            return;
        }

        while ($node->firstChild !== null) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }
}
