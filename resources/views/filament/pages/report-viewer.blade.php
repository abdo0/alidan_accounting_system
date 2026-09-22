@php
    use App\Domain\Reporting\Result\Column;
    use App\Domain\Reporting\Result\Row;
    use App\Support\Format\IqdFormatter;

    $result = $this->result();
    $numerals = $this->numerals();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="applyFilters">
            {{ $this->form }}
        </form>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="description">
            @foreach ($result->filters as $label => $value)
                <span class="me-4">{{ $label }}: <strong>{{ $value }}</strong></span>
            @endforeach
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        @foreach ($result->columns as $column)
                            <th @class(['px-3 py-2 font-medium whitespace-nowrap', 'text-end' => $column->type === Column::AMOUNT, 'text-start' => $column->type !== Column::AMOUNT])>
                                {{ $column->label }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($result->rows as $row)
                        <tr @class([
                            'border-b border-gray-100 dark:border-white/5',
                            'font-semibold bg-gray-50 dark:bg-white/5' => in_array($row->style, [Row::GROUP, Row::SUBTOTAL, Row::TOTAL], true),
                            'border-t-2 border-gray-400' => $row->style === Row::TOTAL,
                        ])>
                            @foreach ($result->columns as $column)
                                @php($value = $row->cells[$column->key] ?? null)
                                @php($url = $this->drillUrl($row->drillFor($column->key)))
                                <td @class(['px-3 py-1.5', 'text-end font-mono whitespace-nowrap' => $column->type === Column::AMOUNT])
                                    @if ($loop->index === 1 && $row->indent > 0) style="padding-inline-start: {{ 0.75 + $row->indent * 1.25 }}rem" @endif>
                                    @php($display = $column->type === Column::AMOUNT && is_numeric($value) ? IqdFormatter::format($value, $numerals) : $value)
                                    @if ($url !== null && $display !== null && $display !== '')
                                        <a href="{{ $url }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $display }}</a>
                                    @else
                                        {{ $display }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($result->columns) }}" class="px-3 py-4 text-center text-gray-500">{{ __('reports.no_rows') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    @if ($result->controls !== [])
        <x-filament::section :heading="__('reports.controls_title')">
            <table class="w-full text-sm">
                @foreach ($result->controls as $control)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="px-3 py-1.5">{{ $control->label }}</td>
                        <td class="px-3 py-1.5 text-end font-mono">{{ IqdFormatter::format($control->left, $numerals) }}</td>
                        <td class="px-3 py-1.5 text-end font-mono">{{ IqdFormatter::format($control->right, $numerals) }}</td>
                        <td class="px-3 py-1.5">
                            @if ($control->passes())
                                <x-filament::badge color="success">{{ __('reports.passes') }}</x-filament::badge>
                            @else
                                <x-filament::badge color="danger">{{ __('reports.difference') }} {{ IqdFormatter::format($control->difference(), $numerals) }}</x-filament::badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </x-filament::section>
    @endif

    @foreach ($result->notes as $note)
        <x-filament::section>{{ $note }}</x-filament::section>
    @endforeach
</x-filament-panels::page>
