<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslips {{ $run->periodLabel() }}</title>
    @include('payslips._style')
    <style>
        @page { margin: 22px 26px; }
        body { margin: 0; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@foreach ($payslips as $p)
    @include('payslips._slip', ['p' => $p, 'run' => $run, 's' => $s, 'cur' => 'Rs.'])
    @unless ($loop->last)<div class="page-break"></div>@endunless
@endforeach
</body>
</html>
