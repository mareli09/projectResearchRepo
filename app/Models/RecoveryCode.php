<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property string $channel tua_email | recovery_email | sms
 * @property string $purpose password_reset | verify_contact
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property int $attempts
 * @property Carbon|null $used_at
 */
class RecoveryCode extends Model
{
    protected $fillable = ['user_id', 'channel', 'purpose', 'code_hash', 'expires_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'attempts' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
