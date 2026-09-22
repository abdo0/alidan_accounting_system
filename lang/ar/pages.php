<?php

declare(strict_types=1);

return [
    'user' => [
        'identity' => 'الهوية',
        'access' => 'الصلاحيات',
        'password_help' => 'اتركه فارغاً للإبقاء على كلمة المرور الحالية.',
        'service_account_help' => 'حساب الخدمة مخصص للتكاملات ولا يصل إلى لوحة التحكم.',
    ],
    'change_request' => [
        'proposed' => 'تم اقتراح التعديل، ويسري بعد أن يعتمده شخص آخر.',
        'decided' => 'تم تسجيل القرار.',
        'system' => 'النظام (المواصفة المعتمدة)',
    ],
    'parameter' => [
        'not_set' => 'غير محدد',
        'in_force' => 'النافذ',
        'changed' => 'سُجّلت القيمة الجديدة، وتبقى السابقة في السجل.',
        'board_reference_required' => 'معامل عالي الخطورة: أدخل مرجع قرار مجلس الإدارة.',
    ],
    'funding' => [
        'actual_payment_source_help' => 'للتدقيق فقط، ولا يُستخدم في أي قاعدة ترحيل (السياسة 8).',
    ],
    'journal' => [
        'header' => 'القيد',
        'lines' => 'الأسطر',
        'controls' => 'الإثبات والرقابة',
        'workflow' => 'سير العمل',
        'totals' => 'الإجماليات',
        'read_only' => 'لا يُعدَّل القيد المرحّل ولا يُحذف؛ يُصحَّح بالعكس أو بإعادة التبويب.',
        'saved' => 'حُفظت المسودة.',
        'submitted' => 'قُدّم للمراجعة.',
        'reviewed' => 'تمت المراجعة.',
        'approved' => 'تم الاعتماد.',
        'rejected' => 'أُعيد إلى المسودة.',
        'posted' => 'رُحّل برقم :jv.',
        'reversed' => 'عُكس بالقيد :jv.',
        'refused' => 'رُفض القيد',
        'acknowledge' => 'أُقرّ بهذه التنبيهات',
        'simple' => 'قيد مبسط',
        'simple_help' => 'حساب مدين وحساب دائن بمبلغ واحد.',
        'amount' => 'المبلغ',
        'debit_account' => 'الحساب المدين',
        'credit_account' => 'الحساب الدائن',
        'balanced' => 'متوازن',
        'not_balanced' => 'غير متوازن',
        'documents' => 'المستندات',
        'approvals' => 'سجل الاعتماد',
        'upload' => 'إرفاق مستند',
    ],
    'exception' => [
        'resolved' => 'عولج الاستثناء.',
    ],
    'duplicate' => [
        'undecided' => 'بانتظار الحسم',
    ],
    'government_share' => [
        'title' => 'حصة الحكومة',
        'prepare' => 'إعداد قيد الإثبات',
    ],
];
