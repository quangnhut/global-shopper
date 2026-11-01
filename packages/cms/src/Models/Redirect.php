<?php

declare(strict_types=1);

namespace Shopper\Cms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A redirect from an old public path (301 / 302) or a removed one (410 Gone).
 *
 * @property-read int $id
 * @property-read string $from_path
 * @property-read ?string $to_path
 * @property-read int $status_code
 * @property-read int $hits
 * @property-read ?CarbonInterface $last_hit_at
 * @property-read string $source
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
class Redirect extends Model
{
    public const string SOURCE_AUTO = 'auto';

    public const string SOURCE_MANUAL = 'manual';

    protected $guarded = [];

    /**
     * `/About/Team/?x=1` → `/about/team`. Absolute URLs are kept as they are (external targets).
     */
    public static function normalize(?string $path): ?string
    {
        if ($path === null || mb_trim($path) === '') {
            return null;
        }

        $path = mb_trim($path);

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $path = (string) parse_url($path, PHP_URL_PATH);
        $path = '/'.mb_trim(mb_strtolower(rawurldecode($path)), '/');

        return $path;
    }

    /**
     * Targets keep their exact form (trailing slash included), only a leading slash is ensured.
     */
    public static function target(?string $path): ?string
    {
        if ($path === null || mb_trim($path) === '') {
            return null;
        }

        $path = mb_trim($path);

        return preg_match('#^https?://#i', $path) === 1 ? $path : '/'.mb_ltrim($path, '/');
    }

    public function getTable(): string
    {
        return shopper_table('cms_redirects');
    }

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }
}
