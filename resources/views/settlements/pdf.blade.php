<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Settlement {{ $st->employee->name }}</title>
    @include('payslips._style')
    <style>@page { margin: 26px 30px; } body { margin: 0; }</style>
</head>
<body>
    @include('settlements._doc', ['st' => $st, 's' => $s, 'cur' => 'Rs.'])
</body>
</html>
