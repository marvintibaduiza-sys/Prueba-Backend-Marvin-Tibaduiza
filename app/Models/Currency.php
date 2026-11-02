<?php

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'symbol',
        'exchange_rate',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:6',
        ];
    }

    /**
     * The configured base (reference) currency.
     */
    public static function base(): self
    {
        return static::where('code', config('currency.base'))->firstOrFail();
    }

    public function isBase(): bool
    {
        return $this->code === config('currency.base');
    }

    /**
     * Whether any product uses this currency as base or has a price in it.
     */
    public function isInUse(): bool
    {
        return $this->products()->exists() || $this->prices()->exists();
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }
}
