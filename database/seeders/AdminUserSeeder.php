<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Access\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Development users: one per role of Document C tab 19, so the maker-checker
 * workflow can be walked end to end. The System Administrator holds no accounting
 * authority, so nobody here is a superuser.
 *
 * The password is a development credential, not a secret -- `db:seed` prompts
 * before it runs in production, and none of these accounts belongs there.
 */
class AdminUserSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /** @var array<string, array{string, string, string}> role => [email, name, name_ar] */
    private const USERS = [
        'system_admin' => ['admin@example.com', 'System Administrator', 'مدير النظام'],
        'finance_manager' => ['fm@example.com', 'Finance Manager', 'المدير المالي'],
        'senior_accountant' => ['senior@example.com', 'Senior Accountant', 'محاسب أقدم'],
        'accountant' => ['accountant@example.com', 'Accountant', 'محاسب'],
        'data_entry' => ['entry@example.com', 'Data Entry', 'مدخل بيانات'],
        'management_viewer' => ['management@example.com', 'Management', 'الإدارة'],
        'internal_auditor' => ['auditor@example.com', 'Internal Auditor', 'المدقق الداخلي'],
        'board' => ['board@example.com', 'Board Member', 'عضو مجلس الإدارة'],
    ];

    public function run(): void
    {
        foreach (self::USERS as $roleName => [$email, $name, $nameAr]) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'name_ar' => $nameAr,
                    'password' => self::PASSWORD,
                    'locale' => 'ar',
                    'is_active' => true,
                    'is_service_account' => false,
                    'job_title' => $role->label_en,
                ],
            );

            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        $this->command->info('Development users seeded (password: '.self::PASSWORD.'): '.implode(', ', array_column(self::USERS, 0)));
    }
}
