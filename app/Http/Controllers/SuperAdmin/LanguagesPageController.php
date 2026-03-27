<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LanguagesPageController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check() || ! Auth::user()->isSuperAdmin()) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Languages/Index', [
            'languages' => Language::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'sort_order', 'is_active', 'is_default', 'direction', 'font_family']),
        ]);
    }
}

