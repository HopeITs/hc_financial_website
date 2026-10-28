<?php

namespace App\Models;

use App\Enums\NotificationKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One notification in a person's bell. The text is built when it is shown, so it follows the person's language.
 *
 * @property int $id
 * @property int $user_id
 * @property NotificationKind $kind
 * @property string $subject
 * @property array<string, string> $data
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
class WebNotification extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'web_notifications';

    protected $fillable = ['user_id', 'kind', 'subject', 'data', 'read_at'];

    protected function casts(): array
    {
        return [
            'kind' => NotificationKind::class,
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFor(Builder $query, User $user): Builder
    {
        return $query->where('user_id', (int) $user->getAuthIdentifier());
    }

    public function message(): string
    {
        return __('notifications.kinds.'.$this->kind->value, [
            'number' => $this->subject,
            'project' => $this->data['project'] ?? '',
            'amount' => $this->data['amount'] ?? '',
            'reason' => $this->data['reason'] ?? '',
        ]);
    }

    public function url(): string
    {
        return $this->kind->url($this->subject);
    }
}
