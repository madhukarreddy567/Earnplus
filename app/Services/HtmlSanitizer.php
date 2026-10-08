<?php

namespace App\Services;

use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Allow-list HTML sanitizer for admin-edited rich text.
 *
 * Strips everything except a safe set of formatting tags and
 * attributes (no scripts, no event handlers, no javascript: URLs).
 * The admin WYSIWYG editor only emits tags in this allow-list,
 * so legitimate content passes through untouched.
 */
class HtmlSanitizer
{
    /** @var list<string> */
    protected const ALLOWED_TAGS = [
        'p', 'br', 'h1', 'h2', 'h3', 'h4',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'blockquote',
        'a', 'span', 'div', 'hr', 'pre', 'code',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'img',
    ];

    /** @var array<string, list<string>> */
    protected const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'span' => ['style'],
        'div' => ['style'],
        'p' => ['style'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    /**
     * Tags whose entire subtree is dropped (not just unwrapped).
     *
     * @var list<string>
     */
    protected const DROP_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input'];

    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // Standard flags (no NOIMPLIED): libxml builds the full
        // html > body tree so we can read back just the body content.
        $doc->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>');
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        // Clean the children of <body> — the html/body wrappers are
        // containers, not content, so they are never unwrapped.
        $bodies = $doc->getElementsByTagName('body');
        $scope = $bodies->length > 0 ? $bodies->item(0) : $doc;

        $this->cleanNode($scope, $xpath);

        $out = '';

        if ($bodies->length > 0) {
            foreach ($bodies->item(0)->childNodes as $child) {
                $out .= $doc->saveHTML($child);
            }
        } else {
            foreach ($doc->childNodes as $child) {
                $out .= $doc->saveHTML($child);
            }
        }

        return $out;
    }

    protected function cleanNode(DOMNode $node, DOMXPath $xpath): void
    {
        // Snapshot children first — removing nodes invalidates the live list.
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($child->nodeName);

                if (in_array($tag, self::DROP_TAGS, true)) {
                    $node->removeChild($child);

                    continue;
                }

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    // Unwrap: keep the text/inner content, drop the tag.
                    $grandchildren = [];
                    foreach ($child->childNodes as $gc) {
                        $grandchildren[] = $gc;
                    }

                    foreach ($grandchildren as $gc) {
                        $node->insertBefore($gc, $child);
                    }

                    $node->removeChild($child);

                    foreach ($grandchildren as $gc) {
                        if ($gc->nodeType === XML_ELEMENT_NODE) {
                            $this->cleanNode($gc, $xpath);
                        }
                    }

                    continue;
                }

                $this->cleanAttributes($child);
                $this->cleanNode($child, $xpath);
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    protected function cleanAttributes(DOMNode $el): void
    {
        $tag = strtolower($el->nodeName);
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        $toRemove = [];

        /** @var \DOMAttr $attr */
        foreach ($el->attributes ?? [] as $attr) {
            $name = strtolower($attr->name);

            if (! in_array($name, $allowed, true)) {
                $toRemove[] = $attr->name;

                continue;
            }

            $value = trim($attr->value);

            // Block javascript:/data: URLs and CSS expressions.
            if (in_array($name, ['href', 'src'], true)) {
                if (preg_match('/^\s*(javascript|data|vbscript)\s*:/i', $value)) {
                    $toRemove[] = $attr->name;
                }
            }

            if ($name === 'style') {
                $safe = $this->cleanStyle($value);

                if ($safe === '') {
                    $toRemove[] = $attr->name;
                } else {
                    $el->setAttribute($attr->name, $safe);
                }
            }
        }

        foreach ($toRemove as $name) {
            $el->removeAttribute($name);
        }

        // Links that open a new tab should not leak the opener.
        if ($tag === 'a' && $el->hasAttribute('target') && $el->getAttribute('target') === '_blank') {
            $rel = strtolower($el->getAttribute('rel'));
            if (! str_contains($rel, 'noopener')) {
                $el->setAttribute('rel', trim($rel . ' noopener'));
            }
        }
    }

    /**
     * Keep only a safe subset of inline CSS declarations.
     */
    protected function cleanStyle(string $style): string
    {
        $allowedProps = [
            'color', 'background-color', 'font-size', 'font-weight',
            'font-style', 'text-decoration', 'text-align',
            'margin', 'margin-top', 'margin-bottom', 'padding',
        ];

        $kept = [];

        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $prop = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if (! in_array($prop, $allowedProps, true)) {
                continue;
            }

            // No url()/expression() in values — ever.
            if (preg_match('/url\s*\(|expression\s*\(/i', $value)) {
                continue;
            }

            $kept[] = "{$prop}: {$value}";
        }

        return implode('; ', $kept);
    }
}
