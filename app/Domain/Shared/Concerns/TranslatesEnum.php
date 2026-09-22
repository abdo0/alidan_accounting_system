<?php

declare(strict_types=1);

namespace App\Domain\Shared\Concerns;

/**
 * Every enum case is sayable in both languages: its label lives in lang/{ar,en}/enums.php
 * under the enum's translation key, never in the class.
 */
trait TranslatesEnum
{
    abstract public static function translationKey(): string;

    public function getLabel(): string
    {
        return __('enums.'.static::translationKey().'.'.$this->value);
    }

    /** @return array<string, string> value => label, for a select field. */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
