<?php

declare(strict_types=1);

namespace Forte\Parser\OptionalTags;

class OptionalTagConfig
{
    /**
     * Elements that can omit closing tags and their conditions.
     *
     * Rules define when an element's closing tag can be omitted:
     * - 'auto_close_when_sibling': Next sibling element names that trigger auto-close
     * - 'auto_close_at_parent_end': Parent elements where this closes at the parent's end
     *
     * @var array<string, array{auto_close_when_sibling?: array<int, string>, auto_close_at_parent_end?: array<int, string>|bool}>
     */
    private const CLOSING_TAG_OMISSION_RULES = [
        'li' => [
            'auto_close_when_sibling' => ['li'],
            'auto_close_at_parent_end' => ['ul', 'ol', 'menu'],
        ],
        'dt' => [
            'auto_close_when_sibling' => ['dt', 'dd'],
            'auto_close_at_parent_end' => ['dl'],
        ],
        'dd' => [
            'auto_close_when_sibling' => ['dt', 'dd'],
            'auto_close_at_parent_end' => ['dl'],
        ],

        'p' => [
            'auto_close_when_sibling' => [
                'address', 'article', 'aside', 'blockquote', 'div', 'dl',
                'fieldset', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'header', 'hr', 'main', 'nav', 'ol', 'p', 'pre', 'section',
                'table', 'ul',
            ],
            // Note: <p> can technically close at the parent's end, but we omit
            // this for safety to avoid unexpected behavior in edge cases.
        ],
        'option' => [
            'auto_close_when_sibling' => ['option', 'optgroup'],
            'auto_close_at_parent_end' => ['select', 'datalist', 'optgroup'],
        ],
        'optgroup' => [
            'auto_close_when_sibling' => ['optgroup'],
            'auto_close_at_parent_end' => ['select'],
        ],

        'rt' => [
            'auto_close_when_sibling' => ['rt', 'rp'],
            'auto_close_at_parent_end' => ['ruby', 'rtc'],
        ],
        'rp' => [
            'auto_close_when_sibling' => ['rt', 'rp'],
            'auto_close_at_parent_end' => ['ruby', 'rtc'],
        ],
        'rb' => [
            'auto_close_when_sibling' => ['rb', 'rt', 'rp', 'rtc'],
            'auto_close_at_parent_end' => ['ruby'],
        ],

        'caption' => [
            'auto_close_when_sibling' => ['colgroup', 'thead', 'tbody', 'tfoot', 'tr'],
            'auto_close_at_parent_end' => ['table'],
        ],
        'colgroup' => [
            'auto_close_when_sibling' => ['colgroup', 'thead', 'tbody', 'tfoot', 'tr'],
            'auto_close_at_parent_end' => ['table'],
        ],
        'thead' => [
            'auto_close_when_sibling' => ['tbody', 'tfoot'],
            'auto_close_at_parent_end' => ['table'],
        ],
        'tbody' => [
            'auto_close_when_sibling' => ['tbody', 'tfoot'],
            'auto_close_at_parent_end' => ['table'],
        ],
        'tfoot' => [
            'auto_close_at_parent_end' => ['table'],
        ],
        'tr' => [
            'auto_close_when_sibling' => ['tr'],
            'auto_close_at_parent_end' => ['table', 'thead', 'tbody', 'tfoot'],
        ],
        'td' => [
            'auto_close_when_sibling' => ['td', 'th'],
            'auto_close_at_parent_end' => ['tr'],
        ],
        'th' => [
            'auto_close_when_sibling' => ['td', 'th'],
            'auto_close_at_parent_end' => ['tr'],
        ],
    ];

    /**
     * Get all element names that support optional tag omission.
     *
     * @return array<string, true>
     */
    public static function getOptionalTagElements(): array
    {
        $elements = [];
        foreach (array_keys(self::CLOSING_TAG_OMISSION_RULES) as $elementName) {
            $elements[$elementName] = true;
        }

        return $elements;
    }

    /**
     * Check if an element can omit its closing tag.
     */
    public function canOmitClosingTag(string $elementName): bool
    {
        return isset(self::CLOSING_TAG_OMISSION_RULES[$elementName]);
    }

    /**
     * Check if an element should auto-close when followed by a sibling element.
     *
     * Example: <li>First<li>Second - first <li> closes when the second < li > appears
     */
    public function shouldAutoCloseOnSibling(string $element, string $siblingElement): bool
    {
        if (! isset(self::CLOSING_TAG_OMISSION_RULES[$element])) {
            return false;
        }

        $rules = self::CLOSING_TAG_OMISSION_RULES[$element];

        $siblings = $rules['auto_close_when_sibling'] ?? null;

        if ($siblings === null) {
            return false;
        }

        return in_array($siblingElement, $siblings, true);
    }

    /**
     * Check if an element should auto-close when its parent element ends.
     *
     * Example: <ul><li>Item</ul> - <li> closes at </ul>
     */
    public function shouldAutoCloseAtParentEnd(string $element, ?string $parentElement): bool
    {
        if (! isset(self::CLOSING_TAG_OMISSION_RULES[$element])) {
            return false;
        }

        $rules = self::CLOSING_TAG_OMISSION_RULES[$element];
        $validParents = $rules['auto_close_at_parent_end'] ?? null;

        if ($validParents === null) {
            return false;
        }

        return in_array($parentElement, $validParents, true);
    }

    /**
     * Get the closing conditions for an element.
     *
     * @return array{auto_close_when_sibling?: array<int, string>, auto_close_at_parent_end?: array<int, string>|bool}|null
     */
    public function getClosingConditions(string $elementName): ?array
    {
        return self::CLOSING_TAG_OMISSION_RULES[$elementName] ?? null;
    }

    /**
     * Get all closing tag omission rules.
     *
     * @return array<string, array{auto_close_when_sibling?: array<int, string>, auto_close_at_parent_end?: array<int, string>|bool}>
     */
    public static function getAllRules(): array
    {
        return self::CLOSING_TAG_OMISSION_RULES;
    }
}
