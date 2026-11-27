<?php

namespace App\Http\Controllers;

use App\Support\Navigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** /dashboard sends each role to its own home page (CLAUDE.md §15). */
class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $key = Navigation::roleKey($request->user());

        return redirect()->route($key ? Navigation::HOME[$key] : 'account');
    }
}
