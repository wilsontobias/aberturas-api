<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminActionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_action_id',
        'admin_user_id',
        'target_user_id',
        'detail',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function adminAction(): BelongsTo
    {
        return $this->belongsTo(AdminAction::class);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public static function record(string $action, int $adminUserId, int $targetUserId, ?string $detail = null): void
    {
        self::create([
            'admin_action_id' => AdminAction::where('name', $action)->value('id'),
            'admin_user_id' => $adminUserId,
            'target_user_id' => $targetUserId,
            'detail' => $detail,
        ]);
    }
}