<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'location',
        'address',
        'phone',
        'email',
        'is_headquarters',
    ];

    protected function casts(): array
    {
        return [
            'is_headquarters' => 'boolean',
        ];
    }
}
