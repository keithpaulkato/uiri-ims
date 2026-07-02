<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'branch_id',
        'type',
        'title',
        'message',
        'link',
        'is_read',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    /**
     * The user this notification targets. Null means it's a broadcast
     * notification for the branch (e.g. low-stock alerts).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The branch this notification relates to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Create a broadcast low-stock notification for the given item, unless
     * an unread low-stock notification already exists for that item in that
     * branch (dedupe — matches legacy maybeNotifyLowStock() intent).
     *
     * Only fires when the item's current stock has actually fallen to or
     * below its minimum threshold.
     */
    public static function notifyLowStock(InventoryItem $item): void
    {
        if (! $item->isLowStock()) {
            return;
        }

        $link = '/items?item='.$item->id;

        $alreadyNotified = static::query()
            ->where('branch_id', $item->branch_id)
            ->where('type', 'low_stock')
            ->where('is_read', false)
            ->where('link', $link)
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        static::create([
            'user_id' => null,
            'branch_id' => $item->branch_id,
            'type' => 'low_stock',
            'title' => "Low stock: {$item->name}",
            'message' => "{$item->name} ({$item->item_code}) is at {$item->current_stock} units, at or below the minimum of {$item->minimum_stock}.",
            'link' => $link,
            'is_read' => false,
        ]);
    }
}
