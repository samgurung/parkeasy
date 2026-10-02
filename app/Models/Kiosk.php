<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Kiosk extends Model
{
    /** The kiosk admits vehicles. Scans here always open a visit. */
    public const TYPE_ENTRY = 'entry';

    /** The kiosk releases vehicles. Scans here always close a visit. */
    public const TYPE_EXIT = 'exit';

    public static function types(): array
    {
        return [self::TYPE_ENTRY, self::TYPE_EXIT];
    }

    protected $fillable = [
        'name',
        'key',
        'type',
        'parking_lot_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => 'string',
        ];
    }

    public function parkingLot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'parking_lot_id');
    }

    public function isEntry(): bool
    {
        return $this->type === self::TYPE_ENTRY;
    }

    public function isExit(): bool
    {
        return $this->type === self::TYPE_EXIT;
    }

    /**
     * Generate a unique, human-friendly identifier for the kiosk URL (?kiosk=<key>).
     */
    public static function makeKey(string $name): string
    {
        return Str::lower(Str::slug($name)).'-'.Str::lower(Str::random(4));
    }
}
