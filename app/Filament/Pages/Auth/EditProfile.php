<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

/**
 * First sign-in for a posting or approving role lands here so two-factor can be
 * enrolled. The password form sits above the MFA section on Filament's default
 * profile, so people change the password, click Home, and bounce straight back
 * with no explanation. MFA is put first while enrolment is still outstanding,
 * and a notice says why the rest of the panel is closed.
 */
class EditProfile extends BaseEditProfile
{
    public function mount(): void
    {
        parent::mount();

        $user = $this->getUser();

        if ($user instanceof User && ! $user->hasSatisfiedMfaRequirement()) {
            Notification::make()
                ->title(__('auth.mfa_required'))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    public function content(Schema $schema): Schema
    {
        $form = $this->getFormContentComponent();
        $mfa = $this->getMultiFactorAuthenticationContentComponent();

        $user = $this->getUser();
        $mfaOutstanding = $user instanceof User && ! $user->hasSatisfiedMfaRequirement();

        $components = $mfaOutstanding
            ? array_values(array_filter([$mfa, $form]))
            : [$form, ...Arr::wrap($mfa)];

        return $schema->components($components);
    }

    protected function getRedirectUrl(): ?string
    {
        $user = $this->getUser();

        if ($user instanceof User && ! $user->hasSatisfiedMfaRequirement()) {
            return null;
        }

        return Filament::getUrl();
    }

    public function getMultiFactorAuthenticationContentComponent(): ?Component
    {
        $component = parent::getMultiFactorAuthenticationContentComponent();

        $user = $this->getUser();

        if ($component === null || ! ($user instanceof User) || $user->hasSatisfiedMfaRequirement()) {
            return $component;
        }

        return $component
            ->label(__('auth.mfa_required_heading'))
            ->description(__('auth.mfa_required'));
    }
}
