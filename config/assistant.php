<?php

/*
|--------------------------------------------------------------------------
| AI assistant
|--------------------------------------------------------------------------
|
| The chat panel that answers questions about the ledger and prepares entries (see App\Services\Assistant).
| It calls Claude through the Anthropic API; without a key the panel says it is not set up and calls nothing.
| Every tool is limited by the person's own screen permissions (config/permissions.php), and an entry is
| only saved when the person presses Save on the card the assistant shows, as a voucher pending approval.
|
*/

return [

    'api_key' => env('ANTHROPIC_API_KEY'),

    'model' => env('ASSISTANT_MODEL', 'claude-opus-5-5'),

    // low | medium | high | xhigh | max: how much the model thinks before it answers.
    'effort' => env('ASSISTANT_EFFORT', 'medium'),

    'max_tokens' => 16000,

    // Model calls in one answer (each tool round is one call); the answer stops with a notice past this.
    'max_rounds' => 8,

    // Messages a person may send per minute.
    'per_minute' => 10,

    // Rows a tool returns at most, so one answer never carries a whole ledger.
    'max_rows' => 50,

];
