<?php

declare(strict_types=1);

return [
    'currency' => [
        'iqd' => 'د.ع',
        'usd' => 'دولار',
    ],

    'account_class' => [
        'asset' => 'أصول',
        'liability' => 'التزامات',
        'equity' => 'حقوق الملكية',
        'revenue' => 'إيرادات',
        'cost_of_sales' => 'تكلفة المبيعات',
        'expense' => 'مصروفات',
        'other_income' => 'إيرادات أخرى',
        'other_expense' => 'مصروفات أخرى',
        'tax' => 'ضرائب',
        'clearing' => 'حسابات وسيطة ومعلقة',
        'statistical' => 'إحصائي',
    ],

    'normal_balance' => [
        'D' => 'مدين',
        'C' => 'دائن',
    ],

    'statement' => [
        'BS' => 'قائمة المركز المالي',
        'PL' => 'قائمة الدخل',
        'SOCE' => 'قائمة التغيرات في حقوق الملكية',
        'NONE' => 'غير مدرج',
    ],

    'journal' => [
        'SDB' => 'دفتر يومية المبيعات',
        'PDB' => 'دفتر يومية المشتريات',
        'RIB' => 'دفتر مردودات المبيعات',
        'ROB' => 'دفتر مردودات المشتريات',
        'CB' => 'دفتر النقدية',
        'PCB' => 'دفتر المصروفات النثرية',
        'GJ' => 'دفتر اليومية العامة',
        'PAY' => 'يومية الرواتب',
        'FA' => 'يومية الأصول الثابتة',
        'INV' => 'يومية المخزون',
        'ALC' => 'يومية توزيع التكاليف',
        'CLO' => 'يومية الإقفال',
        'OPN' => 'يومية الأرصدة الافتتاحية',
    ],

    'entry_status' => [
        'draft' => 'مسودة',
        'pending_approval' => 'بانتظار الاعتماد',
        'approved' => 'معتمد',
        'posted' => 'مُرحَّل',
        'reversed' => 'معكوس',
        'rejected' => 'مرفوض',
    ],

    'period_status' => [
        'open' => 'مفتوحة',
        'soft_closed' => 'مغلقة مبدئيا',
        'closed' => 'مغلقة',
        'permanently_closed' => 'مغلقة نهائيا',
    ],

    'cost_centre_type' => [
        'operating' => 'تشغيلي',
        'support' => 'مساند',
        'project' => 'مشروع',
        'admin' => 'إداري',
        'statistical' => 'إحصائي',
    ],

    'reversal_reason' => [
        'data_entry_error' => 'خطأ في الإدخال',
        'wrong_period' => 'فترة خاطئة',
        'wrong_account' => 'حساب خاطئ',
        'wrong_amount' => 'مبلغ خاطئ',
        'duplicate' => 'قيد مكرر',
        'cancelled_transaction' => 'عملية ملغاة',
        'audit_adjustment' => 'تسوية تدقيق',
        'dimension_correction' => 'تصحيح مركز التكلفة',
    ],

    'validation' => [
        'unbalanced' => 'القيد غير متوازن: المدين :debit والدائن :credit.',
        'minimum_lines' => 'يجب أن يحتوي القيد على سطرين على الأقل.',
        'line_both_sides' => 'السطر :line يحمل مبلغا مدينا ودائنا معا.',
        'line_zero' => 'السطر :line بدون مبلغ.',
        'period_closed' => 'الفترة :period حالتها :status ولا يمكن الترحيل إليها.',
        'date_outside_period' => 'تاريخ القيد :date خارج حدود الفترة :period.',
        'account_inactive' => 'الحساب :account غير نشط.',
        'account_not_postable' => 'الحساب :account حساب رئيسي ولا يقبل الترحيل.',
        'control_account' => 'الحساب :account حساب مراقبة؛ الترحيل يتم عبر الأستاذ المساعد.',
        'cost_centre_required' => 'الحساب :account يتطلب مركز تكلفة.',
        'cost_centre_inactive' => 'مركز التكلفة :cost_centre غير نشط في :date.',
        'cross_entity' => 'يجب أن تنتمي جميع سطور القيد إلى منشأة واحدة.',
        'adjusting_needs_both' => 'قيد التسوية يجب أن يمس حسابا في المركز المالي وحسابا في قائمة الدخل.',
        'adjusting_no_cash' => 'قيد التسوية لا يجوز أن يتضمن حساب نقدية أو بنك.',
        'evidence_required' => 'القيد اليدوي يتطلب رقم مستند ومرفقا واحدا على الأقل.',
        'approval_required' => 'هذا القيد يحتاج اعتمادا قبل الترحيل.',
        'approver_is_creator' => 'لا يجوز اعتماد القيد من نفس الشخص الذي أنشأه.',
        'memo_mixed_with_financial' => 'لا يجوز أن يتضمن قيد الحسابات المتقابلة حسابات مالية.',
        'memo_no_partner' => 'الحساب :account حساب متقابل وليس له حساب مقابل مسجل.',
        'memo_unpaired' => 'الحساب :account يجب أن يقابله :partner في الجانب المعاكس وبنفس المبلغ.',
        'already_posted' => 'تم ترحيل هذا القيد مسبقا.',
        'currency_not_iqd' => 'هذا النظام يرحّل بالدينار العراقي فقط.',
    ],
];
