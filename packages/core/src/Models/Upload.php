<?php

declare(strict_types=1);

namespace Shopper\Core\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $file_name
 * @property-read string $path
 * @property-read string $disk
 * @property-read string $mime_type
 * @property-read string $extension
 * @property-read int $size
 * @property-read ?int $user_id
 * @property-read string $url
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
class Upload extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return shopper_table('uploads');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    protected static function booted(): void
    {
        static::deleted(fn (Upload $upload): bool => Storage::disk($upload->disk)->delete($upload->path));
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk($this->disk)->url($this->path));
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
