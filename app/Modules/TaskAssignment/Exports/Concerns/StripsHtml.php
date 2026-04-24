<?php

namespace App\Modules\TaskAssignment\Exports\Concerns;

trait StripsHtml
{
    /**
     * Biến richtext HTML (từ FE editor) → plain text cho Excel.
     *
     * - strip_tags: gỡ <p>, <strong>, <br>...
     * - html_entity_decode: &amp; → &, &nbsp; → space...
     * - Thu gọn whitespace (nhiều space/newline liên tiếp → 1 space).
     */
    protected function stripHtml(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}
