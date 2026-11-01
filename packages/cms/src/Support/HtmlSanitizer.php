<?php

declare(strict_types=1);

namespace Shopper\Cms\Support;

use HTMLPurifier;
use HTMLPurifier_Config;
use HTMLPurifier_HTMLDefinition;
use Illuminate\Support\Facades\File;

/**
 * Cleans rich text (TinyMCE content, excerpts) before it is stored, so it can later be rendered
 * as HTML safely. Allows what the editor produces: formatting, headings, lists, tables, links,
 * images, figures and YouTube/Vimeo embeds. Scripts, event handlers and unsafe URLs are removed.
 */
final class HtmlSanitizer
{
    private ?HTMLPurifier $purifier = null;

    public function clean(?string $html): ?string
    {
        if ($html === null || mb_trim($html) === '') {
            return $html;
        }

        return mb_trim($this->purifier()->purify($html));
    }

    private function purifier(): HTMLPurifier
    {
        return $this->purifier ??= new HTMLPurifier($this->config());
    }

    private function config(): HTMLPurifier_Config
    {
        $config = HTMLPurifier_Config::createDefault();

        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'XHTML 1.0 Transitional');
        $config->set('HTML.Allowed', implode(',', [
            'p[style]', 'br', 'hr', 'span[style]', 'div[style]',
            'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins', 'sub', 'sup', 'small',
            'h2[style]', 'h3[style]', 'h4[style]', 'h5[style]', 'h6[style]',
            'blockquote[cite]', 'q[cite]', 'cite', 'code', 'pre', 'abbr[title]',
            'ul[style]', 'ol[style|start]', 'li[style]', 'dl', 'dt', 'dd',
            'a[href|title|target|rel]',
            'img[src|alt|title|width|height|class|style]',
            'figure[class|style]', 'figcaption',
            'table[style|border|cellpadding|cellspacing]', 'caption', 'thead', 'tbody', 'tfoot', 'tr',
            'th[style|colspan|rowspan|scope]', 'td[style|colspan|rowspan]',
            'iframe[src|width|height|title|allowfullscreen|frameborder]',
        ]));
        $config->set('CSS.AllowedProperties', [
            'text-align', 'color', 'background-color', 'text-decoration', 'padding-left',
            'float', 'margin', 'margin-left', 'margin-right', 'width', 'height',
            'border', 'border-collapse', 'border-width', 'border-style', 'border-color',
        ]);
        $config->set('Attr.AllowedClasses', ['alignleft', 'aligncenter', 'alignright', 'alignnone', 'image']);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^https://(www\.youtube(-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', false);

        $cachePath = storage_path('framework/cache/htmlpurifier');
        File::ensureDirectoryExists($cachePath);
        $config->set('Cache.SerializerPath', $cachePath);

        // HTMLPurifier predates HTML5: teach it <figure> and <figcaption> (used for image captions).
        $config->set('HTML.DefinitionID', 'shopper-cms');
        $config->set('HTML.DefinitionRev', 1);

        if ($definition = $config->maybeGetRawHTMLDefinition()) {
            $this->addHtml5Elements($definition);
        }

        return $config;
    }

    private function addHtml5Elements(HTMLPurifier_HTMLDefinition $definition): void
    {
        $definition->addElement('figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common');
        $definition->addElement('figcaption', 'Inline', 'Flow', 'Common');
        $definition->addAttribute('iframe', 'allowfullscreen', 'Bool');
    }
}
