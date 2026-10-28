<?php

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class QuotationHtmlSanitizer
{
    private const ALLOWED_TAGS = ['b', 'br', 'em', 'hr', 'i', 'li', 'ol', 'p', 'strong', 'ul'];

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
            return '<p>'.nl2br(htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</p>';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="quotation-sanitizer-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new DOMXPath($document))->query('//*[@id="quotation-sanitizer-root"]')->item(0);
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

    public function forEmail(?string $html): string
    {
        $html = $this->sanitize($html);

        return str_replace(
            ['<p>', '<ul>', '<ol>', '<li>', '<hr>'],
            [
                '<p style="margin:6px 0 10px;color:#4c5159;line-height:1.55">',
                '<ul style="margin:7px 0 11px;padding-left:21px;color:#4c5159">',
                '<ol style="margin:7px 0 11px;padding-left:21px;color:#4c5159">',
                '<li style="margin:0 0 5px;line-height:1.5">',
                '<hr style="margin:12px 0;border:0;border-top:1px solid #dedbd2">',
            ],
            $html,
        );
    }

    public function toPlainText(?string $html): string
    {
        $html = $this->sanitize($html);
        $html = preg_replace('/<li(?:\s[^>]*)?>/i', '- ', $html) ?? $html;
        $html = preg_replace('/<hr(?:\s[^>]*)?>/i', "\n---\n", $html) ?? $html;
        $html = preg_replace('/<br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $html = str_ireplace(['</li>', '</p>', '</ul>', '</ol>'], "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
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

        foreach (iterator_to_array($node->attributes) as $attribute) {
            $node->removeAttribute($attribute->name);
        }
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
