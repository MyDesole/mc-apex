<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Белый список HTML для текстов из визуального редактора (новости).
 *
 * Оставляет только разметку текста: абзацы, заголовки, списки, цитаты,
 * ссылки, кнопки-ссылки, картинки и разделитель. Всё остальное —
 * теги разворачиваются (текст остаётся), опасные выкидываются целиком,
 * атрибуты — только нужные, ссылки — только http(s), mailto и свои.
 */
final class HtmlSanitizer
{
    /** Тег => разрешённые атрибуты. */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [],
        'h2' => [], 'h3' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'blockquote' => [],
        'a' => ['href', 'class'],
        'img' => ['src', 'alt'],
    ];

    /** Вырезаются вместе с содержимым. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'template', 'noscript'];

    /** Единственный разрешённый класс ссылки — кнопка. */
    private const LINK_CLASSES = ['news-btn'];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementById('root');
        if (! $root) {
            return '';
        }

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= self::node($child);
        }

        return trim($out);
    }

    private static function node(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, self::DROP, true)) {
            return '';
        }

        $inner = '';
        foreach (iterator_to_array($node->childNodes) as $child) {
            $inner .= self::node($child);
        }

        // Незнакомый тег (div, span…) — разворачиваем: текст оставляем
        if (! array_key_exists($tag, self::ALLOWED)) {
            return in_array($tag, ['div', 'section', 'article'], true) && $inner !== '' ? "<p>{$inner}</p>" : $inner;
        }

        $attrs = '';
        foreach (self::ALLOWED[$tag] as $name) {
            $value = $node->getAttribute($name);
            if ($value === '') {
                continue;
            }

            $value = match (true) {
                $tag === 'a' && $name === 'href' => self::safeUrl($value, allowRelative: true),
                $tag === 'img' && $name === 'src' => self::safeUrl($value, allowRelative: false, httpsOnly: true),
                $tag === 'a' && $name === 'class' => in_array($value, self::LINK_CLASSES, true) ? $value : null,
                default => mb_substr($value, 0, 200),
            };

            if ($value !== null) {
                $attrs .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"';
            }
        }

        // Картинка без адреса и ссылка без адреса не нужны
        if ($tag === 'img') {
            return str_contains($attrs, 'src=') ? "<img{$attrs} loading=\"lazy\">" : '';
        }

        if ($tag === 'a') {
            if (! str_contains($attrs, 'href=')) {
                return $inner;
            }

            return "<a{$attrs} target=\"_blank\" rel=\"noopener noreferrer nofollow\">{$inner}</a>";
        }

        if (in_array($tag, ['br', 'hr'], true)) {
            return "<{$tag}>";
        }

        return "<{$tag}{$attrs}>{$inner}</{$tag}>";
    }

    private static function safeUrl(string $url, bool $allowRelative, bool $httpsOnly = false): ?string
    {
        $url = trim($url);

        if ($allowRelative && preg_match('#^/(?!/)#', $url)) {
            return mb_substr($url, 0, 500);
        }

        $pattern = $httpsOnly ? '#^https://[^\s<>"]+$#i' : '#^(https?://|mailto:)[^\s<>"]+$#i';

        return preg_match($pattern, $url) ? mb_substr($url, 0, 500) : null;
    }
}
