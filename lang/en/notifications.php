<?php

return [
    'title' => 'Notifications',
    'bell_label' => 'Notifications, :count unread',
    'mark_all' => 'Mark all as read',
    'empty' => 'Nothing new. Approvals and decisions on your vouchers and requests show up here.',

    'kinds' => [
        'voucher_pending' => 'Voucher :number is waiting for your approval (USD :amount).',
        'voucher_approved' => 'Your voucher :number was approved.',
        'voucher_rejected' => 'Your voucher :number was rejected: :reason',
        'request_pending' => 'Request #:number for :project is waiting for your decision (USD :amount).',
        'request_approved' => 'Your request #:number for :project was approved.',
        'request_rejected' => 'Your request #:number for :project was rejected: :reason',
    ],
];
