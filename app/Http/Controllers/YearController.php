<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Switches the year used by every page (top bar selector). */
class YearController extends Controller
{
    public function switch(Request $request)
    {
        $data = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1)]]);
        session(['active_year' => (int) $data['year']]);

        // go back to the same page, dropping any year / month filter that belonged to the old year
        $back = url()->previous();
        $parts = parse_url($back);
        parse_str($parts['query'] ?? '', $query);
        unset($query['year'], $query['period'], $query['month'], $query['from'], $query['to'], $query['page']);
        $path = ($parts['path'] ?? '/').($query ? '?'.http_build_query($query) : '');

        return redirect($path)->with('success', 'Showing data for '.$data['year'].'.');
    }
}
