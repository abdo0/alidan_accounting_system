<?php

declare(strict_types=1);

namespace App\Filament\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Filament 5 types $navigationGroup as string|UnitEnum|null. An enum gives typed,
 * translatable group names instead of strings repeated across every resource.
 */
enum NavigationGroup: string implements HasIcon, HasLabel
{
    case Journal = 'journal';
    case Funding = 'funding';
    case Cash = 'cash';
    case Controls = 'controls';
    case Reporting = 'reporting';
    case Closing = 'closing';
    case MasterData = 'master_data';
    case Setup = 'setup';
    case Administration = 'administration';

    public function getLabel(): string
    {
        return __('navigation.groups.'.$this->value);
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Journal => Heroicon::OutlinedBookOpen,
            self::Funding => Heroicon::OutlinedArrowsRightLeft,
            self::Cash => Heroicon::OutlinedBanknotes,
            self::Controls => Heroicon::OutlinedShieldExclamation,
            self::Reporting => Heroicon::OutlinedChartBar,
            self::Closing => Heroicon::OutlinedLockClosed,
            self::MasterData => Heroicon::OutlinedRectangleStack,
            self::Setup => Heroicon::OutlinedCog6Tooth,
            self::Administration => Heroicon::OutlinedShieldCheck,
        };
    }
}
