<?php

namespace App\Http\Controllers;

use App\Support\SiteAuditAdminStats;
use Illuminate\View\View;

class SiteAuditAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index(): View
    {
        $registry = SiteAuditAdminStats::snapshot();
        $stats = $registry['summary'];

        return view('pages.site-audit-admin', compact('registry', 'stats'));
    }
}
