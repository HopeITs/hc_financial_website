<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An entry the AI assistant prepared. It is saved as a voucher only when its person presses Save (status `saved`
 * with the voucher number), or dropped (`cancelled`).
 *
 * @property int $id
 * @property int $user_id
 * @property string $conversation_id
 * @property array<string, string> $operation
 * @property string $status
 * @property string|null $voucher_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AssistantProposal extends Model
{
    public const PENDING = 'pending';

    public const SAVED = 'saved';

    public const CANCELLED = 'cancelled';

    protected $table = 'web_assistant_proposals';

    protected $fillable = ['user_id', 'conversation_id', 'operation', 'status', 'voucher_id'];

    protected function casts(): array
    {
        return ['operation' => 'array', 'user_id' => 'integer'];
    }
}
