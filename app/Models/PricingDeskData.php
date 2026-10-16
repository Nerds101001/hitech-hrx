<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single live row backing the Sales Pricing Desk tool. See
 * database/migrations/..._create_pricing_desk_data_table.php.
 */
class PricingDeskData extends Model
{
    protected $table = 'pricing_desk_data';

    protected $fillable = ['data', 'version', 'updated_by_id'];

    protected $casts = [
        'data' => 'array',
    ];

    /** The one row every read/write uses — creates it from the seed file on first touch. */
    public static function current(): self
    {
        $row = static::query()->orderByDesc('id')->first();
        if ($row) {
            return $row;
        }

        $seedPath = database_path('seeders/data/pricing_desk_seed.json');
        $seed = is_file($seedPath) ? json_decode(file_get_contents($seedPath), true) : [];

        return static::create([
            'data' => $seed,
            'version' => $seed['version'] ?? null,
        ]);
    }
}
