<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /**
     * Field yang boleh diisi secara mass-assignment.
     */
    protected $fillable = [
        'order_code',
        'user_id',
        'product_id',
        'quantity',
        'total_price',
        'status',
    ];
}
