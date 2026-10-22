<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file attached to an operation (`action_attachments`). Read only.
 */
class ActionAttachment extends LegacyModel
{
    protected $table = 'action_attachments';

    protected $primaryKey = 'id';

    /**
     * @return BelongsTo<Action, $this>
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class, 'action_id');
    }
}
