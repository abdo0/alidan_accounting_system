@extends('pdf.layout')

@section('title', $statement->definition->displayName())
@section('meta', $entityName . ' — ' . $statement->periodLabel)

@section('content')
@if ($statement->isAwaitingData())
    <div class="notice">{{ __('accounting.statement.awaiting_data', ['module' => $statement->awaitingModule]) }}</div>
@endif

<table>
    <thead>
        <tr>
            <th class="ref">{{ __('accounting.statement.statement_ref') }}</th>
            <th class="code">{{ __('accounting.statement.code_column') }}</th>
            <th class="txt">{{ __('accounting.statement.account_column') }}</th>
            <th class="num">{{ __('accounting.statement.current_year') }}</th>
            <th class="num">{{ __('accounting.statement.prior_year') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($statement->lines as $line)
            @continue($line->lineType === 'spacer')
            <tr class="{{ $line->isBold ? 'total' : '' }} {{ $line->lineType === 'header' ? 'section' : '' }}">
                <td class="ref">{{ $line->analyticalRef ? '(' . $line->analyticalRef . ')' : '' }}</td>
                <td class="code">{{ $line->accountCodeLabel }}</td>
                <td class="txt" style="padding-{{ $isRtl ? 'right' : 'left' }}: {{ 2 + ($line->indentLevel * 4) }}mm">{{ $line->label }}</td>
                <td class="num">{{ $line->carriesFigure() ? $format($line->current) : '' }}</td>
                <td class="num">{{ $line->prior !== null ? $format($line->prior) : '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endsection
