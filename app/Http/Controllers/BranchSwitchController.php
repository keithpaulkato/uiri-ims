<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchSwitchController extends Controller
{
    /**
     * Switch the active branch stored in the session.
     *
     * Restricted to Administrators via the `role.min:Administrator`
     * route middleware. Stores the chosen branch id under
     * `active_branch_id` in the session and redirects back.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $request->session()->put('active_branch_id', $validated['branch_id']);

        return back();
    }
}
