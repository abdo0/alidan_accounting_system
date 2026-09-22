<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Shared\Contracts\HasDisplayName;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared conventions for every resource in this panel.
 *
 * The panel is bilingual and opens in Arabic, so no label is ever written in a class
 * -- each resource names a translation key and both languages come from the lang
 * directory.
 *
 * Authorization is deliberately absent here: Filament already routes canViewAny(),
 * canCreate(), canEdit() and canDelete() through the Gate, and the policies are
 * registered in AuthServiceProvider. A resource overrides those only to express
 * something the policy cannot know.
 *
 * @template TModel of Model
 *
 * @extends resource<TModel>
 */
abstract class BaseResource extends Resource
{
    /** Key in the resources translation file holding label, plural_label, navigation. */
    protected static ?string $translationKey = null;

    public static function getModelLabel(): string
    {
        return __(static::translationPath('label'));
    }

    public static function getPluralModelLabel(): string
    {
        return __(static::translationPath('plural_label'));
    }

    public static function getNavigationLabel(): string
    {
        return __(static::translationPath('navigation'));
    }

    /**
     * Code first: people here look an account or a centre up by its number, and the
     * Arabic name has to be searchable even when the panel is in English.
     *
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name', 'name_ar'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record instanceof HasDisplayName
            ? $record->displayName()
            : (string) $record->getKey();
    }

    protected static function translationPath(string $suffix): string
    {
        $key = static::$translationKey ?? throw new \LogicException(
            static::class.' must declare $translationKey.'
        );

        return "resources.{$key}.{$suffix}";
    }
}
