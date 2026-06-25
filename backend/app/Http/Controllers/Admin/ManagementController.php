<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PositionManagementDecision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagementController extends Controller
{
    public function index(Request $request): View
    {
        $query = PositionManagementDecision::with('account')->latest();

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        } else {
            $query->whereIn('status', ['PENDING', 'FETCHED']);
        }

        $decisions = $query->paginate(25)->withQueryString();

        return view('admin.management.index', compact('decisions'));
    }

    public function cancel(PositionManagementDecision $decision): RedirectResponse
    {
        if (! in_array($decision->status, ['PENDING', 'FETCHED'], true)) {
            return back()->withErrors(['decision' => 'Only pending actions can be cancelled.']);
        }

        $decision->update(['status' => 'CANCELLED']);

        return back()->with('status', "Management action #{$decision->id} cancelled.");
    }
}
