<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One message of an AI assistant conversation: the person's question, the model's reply (with its thinking and tool
 * calls) or the tool results sent back. `content` holds the blocks as the API had them, so history replays unchanged.
 *
 * @property int $id
 * @property int $user_id
 * @property string $conversation_id
 * @property string $role
 * @property string $content
 * @property Carbon|null $created_at
 */
class AssistantMessage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'web_assistant_messages';

    protected $fillable = ['user_id', 'conversation_id', 'role', 'content'];

    protected function casts(): array
    {
        return ['user_id' => 'integer'];
    }

    /** @return string|list<array<string, mixed>> */
    public function blocks(): string|array
    {
        $decoded = json_decode($this->content, true);

        return match (true) {
            is_array($decoded) => array_values($decoded),
            is_string($decoded) => $decoded,
            default => $this->content,
        };
    }
}
