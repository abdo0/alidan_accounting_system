<?php

declare(strict_types=1);

return [
    'not_authorised' => 'اعتماد الترحيل من صلاحية المدير المالي فقط.',
    'signoff_required' => 'اعتماد الترحيل يتطلب مرجع المصادقة الخارجية (--signoff).',
    'unknown_account' => ':where: الحساب :code ليس في الشجرة المعتمدة.',
    'fractional' => ':where: المبلغ ليس بالدينار الصحيح؛ لا يُقرَّب شيء.',
    'unbalanced' => ':where: المبلغ المدين والدائن مختلفان أو غير موجودين.',
    'unknown_project' => ':where: المشروع ":project" ليس من المشاريع الأربعة المعتمدة.',
    'cutoff_required' => 'الصفوف دون تاريخ تتطلب ضبط تاريخ قطع الترحيل أولاً؛ لا يُختلق أي تاريخ.',
    'no_period' => 'لا توجد فترة محاسبية تتضمن :date.',
    'missing_cost_center' => 'القيد :jv لا يحمل مركز تكلفة في المصدر (EXC-SYS-03).',
    'chain_mismatch' => 'استثناء مصدر / يتطلب قراراً محاسبياً — القيد :jv مصنّف ":step" وحساباته لا تطابق الخطوة.',
    'unknown_value' => 'استثناء مصدر / يتطلب قراراً محاسبياً — القيد :jv يحمل قيمة غير معروفة ":value".',
    'historic_advance' => 'موقف عهدة تاريخي مرحّل من السجل المعتمد (MIG-17)',
    'control_unknown' => 'ضابط غير معروف.',
    'per_account_difference' => 'الأسطر المرحّلة تترك فرقاً قدره :difference.',
    'exceptions_missing' => ':open من أصل :expected من استثناءات السجل مفتوحة.',
    'no_run' => 'لم يُعتمد أي ترحيل بعد. حمّل الملف المعتمد بالأمر shh:migrate:file1.',
];
