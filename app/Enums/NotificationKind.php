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
    case RequestReturned = 'request_returned';

    public function isAboutVoucher(): bool
    {
        return in_array($this, self::aboutVouchers(), true);
    }

    /** @return list<self> */
    public static function aboutVouchers(): array
    {
        return [self::VoucherPending, self::VoucherApproved, self::VoucherRejected];
    }

    /** @return list<self> */
    public static function aboutRequests(): array
    {
        return [self::RequestPending, self::RequestApproved, self::RequestRejected, self::RequestReturned];
    }

    /** The status colour of its icon: waiting is amber, approved green, rejected red (the text says the same). */
    public function tone(): string
    {
        return match ($this) {
            self::VoucherPending, self::RequestPending, self::RequestReturned => 'warn',
            self::VoucherApproved, self::RequestApproved => 'good',
            self::VoucherRejected, self::RequestRejected => 'critical',
        };
    }

    public function icon(): string
    {
        return match ($this->tone()) {
            'warn' => 'clock',
            'good' => 'check',
            default => 'x-mark',
        };
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
