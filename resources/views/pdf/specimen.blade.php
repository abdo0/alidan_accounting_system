@extends('pdf.layout')

@section('title', $title)
@section('meta', $meta)

@section('content')
<table>
    <thead>
        <tr>
            <th class="txt">{{ $headings['code'] }}</th>
            <th class="txt">{{ $headings['account'] }}</th>
            <th class="num">{{ $headings['debit'] }}</th>
            <th class="num">{{ $headings['credit'] }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td class="txt">{{ $row['code'] }}</td>
                <td class="txt">{{ $row['account'] }}</td>
                <td class="num">{{ $row['debit'] }}</td>
                <td class="num">{{ $row['credit'] }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td class="txt" colspan="2">{{ $headings['total'] }}</td>
            <td class="num">{{ $totals['debit'] }}</td>
            <td class="num">{{ $totals['credit'] }}</td>
        </tr>
    </tbody>
</table>
@endsection
