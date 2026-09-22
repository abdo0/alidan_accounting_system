<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organisation;

use App\Domain\Organisation\Enums\ParameterCode;
use App\Domain\Organisation\Parameter;
use App\Domain\Organisation\ParameterService;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Organisation\ParameterResource\Pages;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * System parameters with full history (M00). Values are never edited: "Change
 * value" closes the value in force and opens a new effective-dated one.
 *
 * @extends BaseResource<Parameter>
 */
class ParameterResource extends BaseResource
{
    protected static ?string $model = Parameter::class;

    protected static ?string $translationKey = 'parameter';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Setup;

    protected static ?int $navigationSort = 10;

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['param_code', 'name', 'name_ar'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('spec_ref')
            ->columns([
                TextColumn::make('spec_ref')->label(__('fields.spec_ref'))->placeholder('—')->sortable(),
                TextColumn::make('name')->label(__('fields.name'))->searchable(['name', 'name_ar', 'param_code'])->wrap()
                    ->formatStateUsing(fn (Parameter $record): string => $record->displayName()),
                TextColumn::make('value')->label(__('fields.value'))->wrap()
                    ->placeholder(__('pages.parameter.not_set')),
                TextColumn::make('effective_from')->label(__('fields.effective_from'))->date(),
                TextColumn::make('effective_to')->label(__('fields.effective_to'))->date()->placeholder('—'),
                TextColumn::make('approval_ref')->label(__('fields.approval_ref'))->placeholder('—')->toggleable(),
                TextColumn::make('source')->label(__('fields.source'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('changedBy.name')->label(__('fields.changed_by'))->placeholder('—')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('current')
                    ->label(__('pages.parameter.in_force'))
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('effective_to'),
                        false: fn (Builder $query) => $query->whereNotNull('effective_to'),
                    ),
            ])
            ->recordActions([self::changeAction()]);
    }

    public static function changeAction(): Action
    {
        return Action::make('changeValue')
            ->label(__('actions.change_value'))
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (Parameter $record): bool => $record->effective_to === null && (auth()->user()?->can('change', Parameter::class) ?? false))
            ->fillForm(fn (Parameter $record): array => ['value' => $record->value])
            ->schema(fn (Parameter $record): array => [
                self::valueField(ParameterCode::from($record->param_code)),
                DatePicker::make('effective_from')->label(__('fields.effective_from'))->required()
                    ->minDate($record->effective_from->addDay()),
                TextInput::make('approval_ref')->label(__('fields.approval_ref'))->required()->maxLength(120)
                    ->helperText(ParameterCode::from($record->param_code)->isHighRisk() ? __('pages.parameter.board_reference_required') : null),
                Textarea::make('reason')->label(__('fields.reason'))->required(),
            ])
            ->action(function (Parameter $record, array $data): void {
                try {
                    app(ParameterService::class)->change(
                        auth()->user(),
                        ParameterCode::from($record->param_code),
                        isset($data['value']) ? (string) $data['value'] : null,
                        CarbonImmutable::parse($data['effective_from']),
                        $data['approval_ref'],
                        $data['reason'],
                        $record->project_id,
                    );
                    Notification::make()->success()->title(__('pages.parameter.changed'))->send();
                } catch (RuleViolation $violation) {
                    Notification::make()->danger()->title($violation->getMessage())->send();
                }
            });
    }

    private static function valueField(ParameterCode $code): TextInput|DatePicker
    {
        return match ($code->dataType()->value) {
            'date' => DatePicker::make('value')->label(__('fields.value')),
            'decimal' => TextInput::make('value')->label(__('fields.value'))->numeric(),
            'integer' => TextInput::make('value')->label(__('fields.value'))->integer(),
            default => TextInput::make('value')->label(__('fields.value')),
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParameters::route('/'),
        ];
    }
}
