<?php

declare(strict_types=1);

namespace Shopper\Cms\Support;

use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Options\DifferOptions;
use Jfcherng\Diff\Options\RendererOptions;
use Shopper\Cms\Models\PostRevision;

/**
 * Side-by-side differences between two revisions. Like WordPress, the HTML *source* is compared as text:
 * the renderer escapes it, so nothing from the content is ever rendered as markup here.
 */
final class RevisionDiff
{
    /**
     * @return array<string, string> field => rendered diff (fields without changes are left out)
     */
    public function compare(?PostRevision $from, PostRevision $to): array
    {
        $diffs = [];

        foreach (['title', 'content', 'excerpt'] as $field) {
            $old = $this->lines($from?->{$field});
            $new = $this->lines($to->{$field});

            if ($old === $new) {
                continue;
            }

            $diffs[$field] = DiffHelper::calculate(
                $old,
                $new,
                'SideBySide',
                new DifferOptions(context: 3),
                new RendererOptions(
                    detailLevel: 'word',
                    lineNumbers: false,
                    showHeader: false,
                    wrapperClasses: ['diff-wrapper', 'cms-revision-diff'],
                ),
            );
        }

        return $diffs;
    }

    /**
     * One block element per line, so changes are shown paragraph by paragraph.
     */
    private function lines(?string $value): string
    {
        return mb_trim((string) preg_replace('/>\s*(<(?:p|h[1-6]|ul|ol|li|table|tr|blockquote|figure|pre|div)\b)/i', ">\n$1", (string) $value));
    }
}
