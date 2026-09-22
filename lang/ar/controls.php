<?php

declare(strict_types=1);

return [
    'duplicate' => [
        'exact' => 'المبلغ والطرف المقابل والفترة نفسها في القيد :jv',
        'near_date' => 'المبلغ والطرف المقابل نفسهما في القيد :jv بتاريخ متقارب (أو دون تاريخ)',
        'source_reference' => 'مرجع المصدر :ref سبق ترحيله في القيد :jv',
        'not_authorised' => 'حسم تأشيرة الازدواج يتطلب صلاحية المراجعة.',
        'note_required' => 'الحسم يتطلب ملاحظة.',
    ],
    'exception' => [
        'cash_variance' => 'الحساب النقدي :account لا يطابق الجرد أو كشف الحساب بتاريخ :date',
        'overdue_advance' => 'السلفة :ref تجاوزت موعد تسويتها (:deadline) وما زال عليها رصيد',
        'pending_evidence' => 'مطالبة تسوية على السلفة :ref بانتظار الإثبات',
        'not_authorised' => 'لا تملك صلاحية تنفيذ هذا الإجراء على الاستثناء.',
        'already_resolved' => 'تمت معالجة هذا الاستثناء مسبقاً.',
        'document' => 'رُحّل القيد :jv بحالة مستند :status',
        'suspense' => 'رُحّل القيد :jv على الحساب الوسيط :account',
        'amount_difference' => 'السطر :line من القيد :jv يحمل فرق مبلغ عن مصدره',
        'suspense_cleared' => 'صُفّي بالقيد :jv (مرجع المعالجة :ref)',
    ],
];
