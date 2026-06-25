<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketSnapshot;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SnapshotController extends Controller
{
    public function index(Request $request): View
    {
        $query = MarketSnapshot::with('account')->latest();

        if ($symbol = $request->query('symbol')) {
            $query->where('symbol', strtoupper($symbol));
        }

        if ($accountId = $request->query('account_id')) {
            $query->where('account_id', $accountId);
        }

        $snapshots = $query->paginate(25)->withQueryString();

        return view('admin.snapshots.index', compact('snapshots'));
    }
}
