<?php

declare(strict_types=1);

namespace Shopper\Seo\Support;

/**
 * Renders title/description templates such as `%title% %sep% %sitename%` (Yoast's snippet variables).
 * Empty variables disappear without leaving dangling separators or double spaces.
 */
final class TemplateRenderer
{
    public const array VARIABLES = [
        '%title%', '%sitename%', '%tagline%', '%sep%', '%excerpt%', '%description%', '%category%', '%tag%',
        '%author%', '%date%', '%modified%', '%currentyear%', '%page%', '%price%', '%brand%', '%search%', '%notfound%',
    ];

    /**
     * Shorten a description at a word boundary (search engines show ~155 characters).
     */
    public static function excerpt(?string $html, int $length = 155): string
    {
        $text = mb_trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5)));

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length + 1);
        $space = mb_strrpos($cut, ' ');

        return mb_rtrim(mb_substr($cut, 0, $space !== false && $space > $length * 0.6 ? $space : $length), ' ,.;:-').'…';
    }

    /**
     * @param  array<string, string|int|float|null>  $values  variable name without % => value
     */
    public function render(?string $template, array $values, string $separator = '-'): string
    {
        if ($template === null || mb_trim($template) === '') {
            return '';
        }

        $replacements = ['%sep%' => "\u{1F}"];

        foreach ($values as $name => $value) {
            $replacements["%{$name}%"] = mb_trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');
        }

        $text = strtr($template, $replacements);
        // Unknown or empty variables vanish.
        $text = (string) preg_replace('/%[a-z_]+%/', '', $text);
        // Collapse separators left next to each other or at the ends ("Title -  - Site" → "Title - Site").
        $text = (string) preg_replace("/(\\s*\u{1F}\\s*)+/u", " \u{1F} ", $text);
        $text = mb_trim($text, " \u{1F}");
        $text = str_replace("\u{1F}", $separator, $text);

        return mb_trim((string) preg_replace('/\s{2,}/u', ' ', $text));
    }
}
