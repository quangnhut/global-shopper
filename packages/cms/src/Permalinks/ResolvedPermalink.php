<?php

declare(strict_types=1);

namespace Shopper\Cms\Permalinks;

use Illuminate\Database\Eloquent\Model;

/**
 * What a public path points to: content (`post`, `page`, `category`, `tag`) or a `redirect`.
 */
final readonly class ResolvedPermalink
{
    public function __construct(
        public string $type,
        public ?Model $model = null,
        public ?string $redirectTo = null,
        public int $status = 301,
    ) {}

    public static function redirect(string $to, int $status = 301): self
    {
        return new self('redirect', redirectTo: $to, status: $status);
    }

    public function isRedirect(): bool
    {
        return $this->type === 'redirect';
    }
}
