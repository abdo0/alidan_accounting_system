<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;

class EditProfile extends BaseEditProfile
{
    protected function getRedirectUrl(): ?string
    {
        return Filament::getUrl();
    }

    public function getMultiFactorAuthenticationContentComponent(): ?Component
    {
        $component = parent::getMultiFactorAuthenticationContentComponent();

        $user = $this->getUser();

        if ($component === null || ! ($user instanceof User) || $user->hasEnrolledMfa()) {
            return $component;
        }

        return $component
            ->label(__('auth.mfa_required_heading'))
            ->description(__('auth.mfa_required'));
    }
}
