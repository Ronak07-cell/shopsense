<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    public const VIEW = 'view';
    public const CART = 'cart';
    public const PURCHASE = 'purchase';

    protected $fillable = ['user_id', 'session_id', 'product_id', 'type'];

    /** Record one shopper action against a product. */
    public static function record(int $productId, string $type): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'product_id' => $productId,
            'type' => $type,
        ]);
    }
}
