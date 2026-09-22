<?php

declare(strict_types=1);

return [
    'parameters' => [
        'not_authorised' => 'تعديل المعاملات من صلاحية المدير المالي فقط.',
        'reason_required' => 'يتطلب تعديل المعامل سبباً ومرجع اعتماد.',
        'not_after_current' => 'يجب أن تسري القيمة الجديدة بعد القيمة النافذة (من :from).',
        'bad_value' => 'القيمة ليست :type صالحة.',
    ],
    'change_requests' => [
        'not_authorised' => 'لا تملك صلاحية تعديل هذا السجل.',
        'reason_required' => 'السبب مطلوب.',
        'field_not_changeable' => 'لا يمكن تعديل هذه الحقول: :fields.',
        'not_open' => 'تم البت في طلب التعديل هذا مسبقاً.',
        'maker_checker' => 'يجب أن يعتمد التعديل شخص غير الذي اقترحه.',
        'balance_not_nil' => 'لا يجوز إيقاف الحساب إلا إذا كان رصيده صفراً.',
    ],
    'journal' => [
        'not_authorised' => 'لا تملك صلاحية تنفيذ هذا الإجراء على هذا القيد.',
        'wrong_status' => 'حالة القيد :status؛ هذه الخطوة غير متاحة.',
        'rejection_comment' => 'الرفض يتطلب تعليقاً.',
        'reversal_description' => 'عكس القيد :jv',
        'reclassification_description' => 'إعادة تبويب القيد :jv',
    ],
    'documents' => [
        'status_only_improves' => 'حالة المستند تتحسن فقط: مفقود ثم جزئي ثم مكتمل.',
        'evidence_recorded' => 'تسجيل مستند مؤيد',
        'received' => 'وصل المستند (:ref)',
        'type_not_allowed' => 'نوع الملف غير مقبول. الأنواع المسموحة: :types.',
        'too_large' => 'حجم الملف أكبر من المسموح.',
        'bad_type' => 'نوع مستند غير معروف.',
        'infected' => 'لم يجتز الملف فحص الفيروسات ولم يُحفظ.',
    ],
    'advances' => [
        'claim_amount' => 'المطالبة تتطلب مبلغاً موجباً.',
    ],
    'cash' => [
        'not_authorised' => 'لا تملك صلاحية تنفيذ خطوة المطابقة هذه.',
        'not_submitted' => 'لا تُعتمد إلا المطابقة المقدّمة.',
    ],
    'revenue' => [
        'nothing_to_recognise' => 'لا توجد حصة حكومية متبقية للإثبات في هذه الفترة.',
        'recognition_description' => 'حصة الإيراد الحكومية للفترة :period بنسبة :rate',
    ],
];
