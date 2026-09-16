<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Access\Permission;
use App\Domain\Access\Role;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    /**
     * Permissions are grouped by module. `*.post` and `*.approve` are the ones that
     * matter: they are what segregation of duties is expressed in terms of.
     *
     * @var array<string, array<string, array{string, string, bool}>>
     */
    private const PERMISSIONS = [
        'ledger' => [
            'ledger.view' => ['View general ledger', 'عرض دفتر الأستاذ العام', false],
            'ledger.create' => ['Create journal entries', 'إنشاء قيود اليومية', false],
            'ledger.post' => ['Post journal entries', 'ترحيل قيود اليومية', true],
            'ledger.approve' => ['Approve journal entries', 'اعتماد قيود اليومية', true],
            'ledger.reverse' => ['Reverse posted entries', 'عكس القيود المرحلة', true],
            'ledger.post_closed_period' => ['Post into a soft-closed period', 'الترحيل في فترة مغلقة مبدئيا', true],
        ],
        'accounts' => [
            'accounts.view' => ['View chart of accounts', 'عرض دليل الحسابات', false],
            'accounts.manage' => ['Maintain chart of accounts', 'إدارة دليل الحسابات', true],
        ],
        'cost_centres' => [
            'cost_centres.view' => ['View cost centres', 'عرض مراكز التكلفة', false],
            'cost_centres.manage' => ['Maintain cost centres', 'إدارة مراكز التكلفة', true],
            'cost_centres.view_all' => ['View all cost centres', 'عرض جميع مراكز التكلفة', false],
        ],
        'receivables' => [
            'ar.view' => ['View receivables', 'عرض الذمم المدينة', false],
            'ar.create' => ['Create sales invoices and receipts', 'إنشاء فواتير المبيعات والمقبوضات', false],
            'ar.approve' => ['Approve credit notes', 'اعتماد إشعارات الدائن', true],
        ],
        'payables' => [
            'ap.view' => ['View payables', 'عرض الذمم الدائنة', false],
            'ap.create' => ['Create vendor bills', 'إنشاء فواتير الموردين', false],
            'ap.approve' => ['Approve vendor bills', 'اعتماد فواتير الموردين', true],
            'ap.pay' => ['Release vendor payments', 'صرف مدفوعات الموردين', true],
            'ap.manage_bank_details' => ['Change vendor bank details', 'تعديل بيانات بنك المورد', true],
        ],
        'cash' => [
            'cash.view' => ['View cash and bank', 'عرض النقدية والبنوك', false],
            'cash.create' => ['Record cash movements', 'تسجيل الحركات النقدية', false],
            'cash.reconcile' => ['Perform bank reconciliation', 'إجراء التسوية البنكية', true],
        ],
        'assets' => [
            'assets.view' => ['View fixed assets', 'عرض الأصول الثابتة', false],
            'assets.manage' => ['Maintain fixed assets', 'إدارة الأصول الثابتة', false],
            'assets.depreciate' => ['Run depreciation', 'احتساب الإهلاك', true],
        ],
        'inventory' => [
            'inventory.view' => ['View inventory', 'عرض المخزون', false],
            'inventory.manage' => ['Record stock movements', 'تسجيل حركات المخزون', false],
            'inventory.adjust' => ['Adjust and write off stock', 'تسوية وإعدام المخزون', true],
        ],
        'payroll' => [
            'payroll.view' => ['View payroll', 'عرض الرواتب', true],
            'payroll.manage' => ['Run payroll', 'تشغيل الرواتب', true],
        ],
        'tax' => [
            'tax.view' => ['View tax', 'عرض الضرائب', false],
            'tax.manage' => ['Maintain tax codes and returns', 'إدارة رموز الضرائب والإقرارات', true],
        ],
        'budget' => [
            'budget.view' => ['View budgets', 'عرض الموازنات', false],
            'budget.manage' => ['Maintain budgets', 'إدارة الموازنات', false],
            'budget.approve' => ['Approve budgets', 'اعتماد الموازنات', true],
            'allocation.run' => ['Run cost allocations', 'تشغيل توزيع التكاليف', true],
        ],
        'period' => [
            'period.view' => ['View period status', 'عرض حالة الفترة', false],
            'period.close' => ['Close accounting periods', 'إقفال الفترات المحاسبية', true],
            'period.reopen' => ['Reopen a closed period', 'إعادة فتح فترة مقفلة', true],
        ],
        'reports' => [
            'reports.view' => ['View financial reports', 'عرض التقارير المالية', false],
            'reports.export' => ['Export financial data', 'تصدير البيانات المالية', true],
            'reports.exceptions' => ['View exception reports', 'عرض تقارير الاستثناءات', true],
        ],
        'admin' => [
            'audit.view' => ['View the audit trail', 'عرض سجل التدقيق', true],
            'users.manage' => ['Manage users and roles', 'إدارة المستخدمين والصلاحيات', true],
            'settings.manage' => ['Manage system settings', 'إدارة إعدادات النظام', true],
        ],
    ];

    /**
     * @var array<string, array{string, string, list<string>|string, bool, bool}>
     *                                                                            name => [label_en, label_ar, permissions or '*', is_read_only, requires_mfa]
     */
    private const ROLES = [
        'viewer' => ['Viewer', 'مطّلع', ['ledger.view', 'accounts.view', 'cost_centres.view', 'reports.view', 'period.view'], true, false],
        'ar_clerk' => ['Receivables Clerk', 'موظف الذمم المدينة', ['ledger.view', 'accounts.view', 'cost_centres.view', 'ar.view', 'ar.create', 'reports.view', 'period.view'], false, false],
        'ap_clerk' => ['Payables Clerk', 'موظف الذمم الدائنة', ['ledger.view', 'accounts.view', 'cost_centres.view', 'ap.view', 'ap.create', 'reports.view', 'period.view'], false, false],
        'cashier' => ['Cashier', 'أمين الصندوق', ['ledger.view', 'accounts.view', 'cash.view', 'cash.create', 'ar.view', 'ar.create', 'reports.view', 'period.view'], false, false],
        'gl_accountant' => ['General Ledger Accountant', 'محاسب الأستاذ العام', ['ledger.view', 'ledger.create', 'ledger.post', 'ledger.reverse', 'accounts.view', 'cost_centres.view', 'cost_centres.view_all', 'ar.view', 'ap.view', 'cash.view', 'cash.reconcile', 'assets.view', 'assets.manage', 'assets.depreciate', 'inventory.view', 'tax.view', 'budget.view', 'reports.view', 'reports.export', 'period.view'], false, true],
        'approver' => ['Approver', 'معتمد', ['ledger.view', 'ledger.approve', 'ap.view', 'ap.approve', 'ar.view', 'ar.approve', 'reports.view', 'period.view'], false, true],
        'financial_controller' => ['Financial Controller', 'المراقب المالي', '*', false, true],
        'auditor' => ['Auditor', 'مدقق', ['ledger.view', 'accounts.view', 'cost_centres.view', 'cost_centres.view_all', 'ar.view', 'ap.view', 'cash.view', 'assets.view', 'inventory.view', 'tax.view', 'budget.view', 'reports.view', 'reports.export', 'reports.exceptions', 'period.view', 'audit.view'], true, true],
        'system_admin' => ['System Administrator', 'مدير النظام', ['users.manage', 'settings.manage', 'audit.view', 'accounts.view', 'cost_centres.view'], false, true],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $group => $permissions) {
            foreach ($permissions as $name => [$labelEn, $labelAr, $sensitive]) {
                Permission::updateOrCreate(
                    ['name' => $name],
                    ['group' => $group, 'label_en' => $labelEn, 'label_ar' => $labelAr, 'is_sensitive' => $sensitive],
                );
            }
        }

        $all = Permission::pluck('id', 'name');

        foreach (self::ROLES as $name => [$labelEn, $labelAr, $permissions, $readOnly, $mfa]) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                [
                    'label_en' => $labelEn,
                    'label_ar' => $labelAr,
                    'is_read_only' => $readOnly,
                    'is_system' => true,
                    'requires_mfa' => $mfa,
                ],
            );

            $ids = $permissions === '*'
                ? $all->values()->all()
                : $all->only($permissions)->values()->all();

            // The controller holds every permission, but never a posting role's
            // approval of their own work -- that is enforced at the document, not here.
            $role->permissions()->sync($ids);
        }
    }
}
