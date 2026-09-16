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
    case Ledger = 'ledger';
    case Receivables = 'receivables';
    case Payables = 'payables';
    case Cash = 'cash';
    case Assets = 'assets';
    case Inventory = 'inventory';
    case Payroll = 'payroll';
    case Reporting = 'reporting';
    case Budgeting = 'budgeting';
    case Setup = 'setup';
    case Administration = 'administration';

    public function getLabel(): string
    {
        return __('navigation.groups.'.$this->value);
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Ledger => Heroicon::OutlinedBookOpen,
            self::Receivables => Heroicon::OutlinedArrowDownTray,
            self::Payables => Heroicon::OutlinedArrowUpTray,
            self::Cash => Heroicon::OutlinedBanknotes,
            self::Assets => Heroicon::OutlinedBuildingOffice2,
            self::Inventory => Heroicon::OutlinedCube,
            self::Payroll => Heroicon::OutlinedUsers,
            self::Reporting => Heroicon::OutlinedChartBar,
            self::Budgeting => Heroicon::OutlinedCalculator,
            self::Setup => Heroicon::OutlinedCog6Tooth,
            self::Administration => Heroicon::OutlinedShieldCheck,
        };
    }
}
