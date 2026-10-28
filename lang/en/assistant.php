<?php

return [
    'title' => 'AI assistant',
    'close' => 'Close the assistant',
    'new' => 'New conversation',
    'placeholder' => 'Ask about balances, vouchers, budgets, or describe an operation to record',
    'send' => 'Send',
    'thinking' => 'Working on it…',
    'intro' => 'Ask about the ledger in plain words, or describe an operation and I will prepare the entry for you to save. I only see what your role allows.',
    'examples' => [
        'How much cash is in each city?',
        'Which of my vouchers are waiting for approval?',
        'Record an expense from the Aleppo USD safe',
    ],

    'errors' => [
        'not_set_up' => 'The assistant is not set up yet: the Anthropic API key is missing. Ask the administrator to add it.',
        'key' => 'The assistant\'s API key was refused. Ask the administrator to check it.',
        'credit' => 'The assistant\'s Anthropic account is out of credit. Ask the administrator to top it up.',
        'busy' =>'The assistant is busy. Try again in a minute.',
        'unreachable' => 'The assistant could not be reached. Try again shortly.',
        'too_many' => 'Too many questions in a minute. Wait a moment and try again.',
    ],

    'notes' => [
        'refusal' => 'The assistant declined to answer this request.',
        'max_tokens' => 'The answer was cut short. Ask again with a narrower question.',
        'too_long' => 'This took too many steps and was stopped. Try a narrower question.',
        'saved' => 'You saved entry #:proposal as voucher :number, now pending approval.',
        'cancelled' => 'You cancelled entry #:proposal; nothing was saved.',
    ],

    'proposal' => [
        'title' => 'Prepared entry',
        'waiting' => 'Not saved',
        'saved' => 'Saved',
        'cancelled' => 'Cancelled',
        'account' => 'Account · project',
        'debit' => 'Debit',
        'credit' => 'Credit',
        'explain' => 'Check the lines. Saving creates the voucher in your name, pending approval.',
        'save' => 'Save entry',
        'cancel' => 'Cancel',
        'saved_notice' => 'Voucher :number saved, pending approval.',
        'open_voucher' => 'Open voucher :number',
        'gone' => 'This entry was already saved or cancelled.',
        'not_allowed' => 'Your role may not save entries.',
    ],
];
