<?php

return [
    'class' => [
        'assets' => 'الأصول',
        'liabilities' => 'الالتزامات',
        'net_assets' => 'صافي الأصول',
        'income' => 'الإيرادات',
        'expenses' => 'المصروفات',
    ],
    'level' => [
        'header' => 'حساب رئيسي',
        'posting' => 'حساب ترحيل',
    ],
    'side' => [
        'debit' => 'مدين',
        'credit' => 'دائن',
    ],
    'voucher_type' => [
        'CRV' => 'سند قبض',
        'CPV' => 'سند صرف',
        'TRF' => 'تحويل',
        'ALC' => 'تخصيص',
        'JV' => 'قيد يومية',
        'OPN' => 'رصيد افتتاحي',
    ],
    'voucher_status' => [
        'pending_approval' => 'بانتظار الموافقة',
        'approved' => 'معتمد',
        'replaced' => 'مُستبدل',
    ],
    'fund_source' => [
        'donor_grant' => 'منحة مانح',
        'chc_own_funds' => 'أموال المركز الذاتية',
        'treasury' => 'الخزينة',
        'own_income' => 'إيراد ذاتي',
        'recovered_loans' => 'قروض مستردة',
    ],
];
