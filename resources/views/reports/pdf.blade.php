@php
    use App\Domain\Reporting\Result\Column;
    use App\Domain\Reporting\Result\Row;
    use App\Support\Format\IqdFormatter;
@endphp
@extends('pdf.layout')

@section('title', $result->title)

@section('meta')
    {{ $company->displayName() }} — {{ $company->code }} · {{ __('reports.generated', ['at' => now()->toDateTimeString(), 'user' => $user]) }}
    @foreach ($result->filters as $label => $value)
        <br>{{ $label }}: {{ $value }}
    @endforeach
@endsection

@section('content')
    <table>
        <thead>
            <tr>
                @foreach ($result->columns as $column)
                    <th class="{{ $column->type === Column::AMOUNT ? 'num' : 'txt' }}">{{ $column->label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($result->rows as $row)
                <tr class="{{ in_array($row->style, [Row::TOTAL, Row::SUBTOTAL], true) ? 'total' : ($row->style === Row::GROUP ? 'section' : '') }}">
                    @foreach ($result->columns as $column)
                        @php($value = $row->cells[$column->key] ?? null)
                        <td class="{{ $column->type === Column::AMOUNT ? 'num' : 'txt' }}">
                            {{ $column->type === Column::AMOUNT && is_numeric($value) ? IqdFormatter::format($value, $numerals) : $value }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($result->columns) }}">{{ __('reports.no_rows') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($result->controls !== [])
        <p><strong>{{ __('reports.controls_title') }}</strong></p>
        <table>
            @foreach ($result->controls as $control)
                <tr>
                    <td class="txt">{{ $control->label }}</td>
                    <td class="num">{{ IqdFormatter::format($control->left, $numerals) }}</td>
                    <td class="num">{{ IqdFormatter::format($control->right, $numerals) }}</td>
                    <td class="txt">{{ $control->passes() ? __('reports.passes') : __('reports.difference').' '.IqdFormatter::format($control->difference(), $numerals) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @foreach ($result->notes as $note)
        <div class="notice">{{ $note }}</div>
    @endforeach
@endsection
