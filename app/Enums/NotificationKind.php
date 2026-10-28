<?php

namespace App\Enums;

use App\Support\Navigation;

/** What a notification in the bell is about, and the screen it opens. */
enum NotificationKind: string
{
    case VoucherPending = 'voucher_pending';
    case VoucherApproved = 'voucher_approved';
    case VoucherRejected = 'voucher_rejected';
    case RequestPending = 'request_pending';
    case RequestApproved = 'request_approved';
    case RequestRejected = 'request_rejected';

    public function isAboutVoucher(): bool
    {
        return in_array($this, [self::VoucherPending, self::VoucherApproved, self::VoucherRejected], true);
    }

    /** The voucher opens in the vouchers list (a rejected one with the rejected vouchers shown), a request on the requests screen. */
    public function url(string $subject): string
    {
        if ($this->isAboutVoucher()) {
            return route(Navigation::routeName('vouchers'), ['voucher' => $subject] + ($this === self::VoucherRejected ? ['rejected' => 1] : []));
        }

        return route(Navigation::routeName('requests'), ['request' => $subject]);
    }
}
