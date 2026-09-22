<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\Access\Enums\PermissionScope;
use App\Domain\Access\Permission;
use App\Domain\Access\Role;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;

/**
 * The eight roles and their permissions, read from the matrix in Document C tab 19.
 *
 * Each cell of the matrix ("Y", "Own", "Soft close", "Propose", "Approve" ...) is
 * translated to permissions by CELL_GRANTS below. No role holds create, approve,
 * post and administer together, and the System Administrator holds no accounting
 * permission at all -- AccessMatrixTest proves both against this seeder's output.
 */
class AccessControlSeeder extends Seeder
{
    /**
     * @var array<string, array<string, array{string, string, bool}>>
     *                                                                group => [name => [label_en, label_ar, sensitive]]
     */
    public const PERMISSIONS = [
        'journal' => [
            'journal.view' => ['View journal entries', 'عرض القيود', false],
            'journal.create' => ['Create journal entries', 'إنشاء القيود', false],
            'journal.edit_draft' => ['Edit draft entries', 'تعديل القيود المسودة', false],
            'journal.submit' => ['Submit entries for review', 'تقديم القيود للمراجعة', false],
            'journal.review' => ['Review entries', 'مراجعة القيود', true],
            'journal.approve' => ['Approve entries', 'اعتماد القيود', true],
            'journal.post' => ['Post entries', 'ترحيل القيود', true],
            'journal.reverse' => ['Reverse posted entries', 'عكس القيود المرحّلة', true],
            'duplicates.disposition' => ['Disposition duplicate flags', 'حسم تأشيرات الازدواجية', true],
            'board.record_decision' => ['Record board decisions', 'تسجيل قرارات مجلس الإدارة', true],
        ],
        'period' => [
            'period.view' => ['View periods', 'عرض الفترات', false],
            'period.soft_close' => ['Soft-close a period', 'الإقفال المبدئي للفترة', true],
            'period.final_close' => ['Final-close a period', 'الإقفال النهائي للفترة', true],
            'period.lock' => ['Lock a period', 'قفل الفترة', true],
            'period.reopen' => ['Reopen a period', 'إعادة فتح الفترة', true],
            'closing.manage' => ['Maintain the closing checklist', 'إدارة قائمة الإقفال', false],
        ],
        'masterdata' => [
            'masterdata.view' => ['View master data', 'عرض البيانات الرئيسية', false],
            'masterdata.request' => ['Request master data changes', 'طلب تعديل البيانات الرئيسية', false],
            'masterdata.modify' => ['Modify master data', 'تعديل البيانات الرئيسية', true],
            'coa.view' => ['View chart of accounts', 'عرض شجرة الحسابات', false],
            'coa.propose' => ['Propose chart of accounts changes', 'اقتراح تعديلات شجرة الحسابات', false],
            'coa.approve' => ['Approve chart of accounts changes', 'اعتماد تعديلات شجرة الحسابات', true],
            'parameters.view' => ['View parameters', 'عرض المعاملات', false],
            'parameters.manage' => ['Manage parameters', 'إدارة المعاملات', true],
        ],
        'cash' => [
            'cash.view' => ['View cash position', 'عرض الموقف النقدي', false],
            'cash.reconcile_prepare' => ['Prepare cash reconciliations', 'إعداد المطابقات النقدية', false],
            'cash.reconcile_review' => ['Review cash reconciliations', 'مراجعة المطابقات النقدية', true],
            'cash.reconcile_approve' => ['Approve cash reconciliations', 'اعتماد المطابقات النقدية', true],
        ],
        'controls' => [
            'exceptions.view' => ['View exceptions', 'عرض الاستثناءات', false],
            'exceptions.propose' => ['Propose exception resolutions', 'اقتراح معالجة الاستثناءات', false],
            'exceptions.resolve' => ['Resolve exceptions', 'حسم الاستثناءات', true],
            'exceptions.comment' => ['Comment on exceptions', 'التعليق على الاستثناءات', false],
            'documents.view' => ['View supporting documents', 'عرض المستندات المؤيدة', false],
        ],
        'reports' => [
            'reports.view' => ['View reports and statements', 'عرض التقارير والقوائم', false],
            'dashboard.view' => ['View the executive dashboard', 'عرض لوحة المؤشرات', false],
            'reports.export' => ['Export reports', 'تصدير التقارير', true],
            'audit.view' => ['View the audit trail', 'عرض سجل التدقيق', true],
        ],
        'admin' => [
            'users.manage' => ['Manage users and roles', 'إدارة المستخدمين والأدوار', true],
            'migration.run' => ['Run the data migration', 'تشغيل ترحيل البيانات', true],
        ],
    ];

    /** Everything "View = Y" grants. */
    private const VIEW_ALL = [
        'journal.view', 'period.view', 'masterdata.view', 'coa.view', 'parameters.view', 'cash.view',
        'exceptions.view', 'documents.view', 'reports.view', 'dashboard.view',
    ];

    /**
     * Matrix column => cell value => permissions granted (scope 'own' marked with a
     * trailing '@own').
     *
     * @var array<string, array<string, list<string>>>
     */
    private const CELL_GRANTS = [
        'View' => [
            'Y' => self::VIEW_ALL,
            'Own + posted' => ['journal.view@own', 'period.view', 'masterdata.view', 'coa.view', 'reports.view'],
            'Reports only' => ['reports.view', 'dashboard.view'],
            'Y incl. audit log' => [...self::VIEW_ALL, 'audit.view'],
            'Dashboard + statements' => ['reports.view', 'dashboard.view'],
        ],
        'Create' => ['Y' => ['journal.create']],
        'Edit Draft' => ['Y' => ['journal.edit_draft'], 'Own' => ['journal.edit_draft@own']],
        'Submit' => ['Y' => ['journal.submit']],
        // Document B §2.5: a duplicate flag is dispositioned by "at least Review".
        'Review' => ['Y' => ['journal.review', 'duplicates.disposition']],
        'Approve' => ['Y' => ['journal.approve'], 'Board decisions' => ['board.record_decision']],
        'Post' => ['Y' => ['journal.post', 'migration.run']],
        'Reverse' => ['Y' => ['journal.reverse']],
        'Close Period' => [
            'Y' => ['period.soft_close', 'period.final_close', 'period.lock', 'closing.manage'],
            'Soft close' => ['period.soft_close', 'closing.manage'],
        ],
        'Reopen Period' => ['Y' => ['period.reopen']],
        'Modify Master Data' => ['Y' => ['masterdata.request', 'masterdata.modify'], 'Request' => ['masterdata.request']],
        'Modify Chart of Accounts' => ['Approve' => ['coa.propose', 'coa.approve'], 'Propose' => ['coa.propose']],
        'Manage Parameters' => ['Y' => ['parameters.manage']],
        'Reconcile Cash' => [
            'Prepare' => ['cash.reconcile_prepare'],
            'Y' => ['cash.reconcile_prepare', 'cash.reconcile_review'],
            'Approve' => ['cash.reconcile_approve'],
        ],
        'Resolve Exceptions' => [
            'Y' => ['exceptions.propose', 'exceptions.resolve', 'exceptions.comment'],
            'Propose' => ['exceptions.propose', 'exceptions.comment'],
            'Comment' => ['exceptions.comment'],
        ],
        'Manage Users' => ['Y' => ['users.manage']],
        'Export' => ['Y' => ['reports.export']],
    ];

    /** @var array<string, array{name: string, read_only: bool, mfa: bool}> */
    private const ROLES = [
        'ROLE-01' => ['name' => 'system_admin', 'read_only' => false, 'mfa' => true],
        'ROLE-02' => ['name' => 'data_entry', 'read_only' => false, 'mfa' => false],
        'ROLE-03' => ['name' => 'accountant', 'read_only' => false, 'mfa' => false],
        'ROLE-04' => ['name' => 'senior_accountant', 'read_only' => false, 'mfa' => true],
        'ROLE-05' => ['name' => 'finance_manager', 'read_only' => false, 'mfa' => true],
        'ROLE-06' => ['name' => 'management_viewer', 'read_only' => true, 'mfa' => false],
        'ROLE-07' => ['name' => 'internal_auditor', 'read_only' => true, 'mfa' => false],
        'ROLE-08' => ['name' => 'board', 'read_only' => false, 'mfa' => true],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $group => $permissions) {
            foreach ($permissions as $name => [$labelEn, $labelAr, $sensitive]) {
                Permission::query()->updateOrCreate(
                    ['name' => $name],
                    ['group' => $group, 'label_en' => $labelEn, 'label_ar' => $labelAr, 'is_sensitive' => $sensitive],
                );
            }
        }

        $permissionIds = Permission::query()->pluck('id', 'name');

        foreach (SpecCsv::rows('19_User_Roles', ['Role ID', 'Role (EN)', 'الدور (عربي)', 'Note']) as $row) {
            $config = self::ROLES[$row['Role ID']] ?? throw new \RuntimeException("Unknown role {$row['Role ID']}.");

            $role = Role::query()->updateOrCreate(
                ['code' => $row['Role ID']],
                [
                    'name' => $config['name'],
                    'label_en' => $row['Role (EN)'],
                    'label_ar' => $row['الدور (عربي)'],
                    'description' => $row['Note'],
                    'is_read_only' => $config['read_only'],
                    'is_system' => true,
                    'requires_mfa' => $config['mfa'],
                ],
            );

            $grants = [];
            foreach (self::CELL_GRANTS as $column => $cells) {
                $cell = trim($row[$column] ?? '');

                if ($cell === '' || $cell === 'N') {
                    continue;
                }

                $granted = $cells[$cell] ?? throw new \RuntimeException("No grant defined for {$column} = \"{$cell}\" ({$row['Role ID']}).");

                foreach ($granted as $grant) {
                    [$permission, $scope] = str_contains($grant, '@') ? explode('@', $grant) : [$grant, 'all'];

                    // A broader grant already held wins over an Own one.
                    if (($grants[$permission] ?? null) === PermissionScope::All->value) {
                        continue;
                    }

                    $grants[$permission] = $scope;
                }
            }

            $role->permissions()->sync(collect($grants)->mapWithKeys(fn (string $scope, string $permission): array => [
                $permissionIds[$permission] => ['scope' => $scope],
            ])->all());
        }
    }
}
