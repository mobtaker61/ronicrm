<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaType;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SocialMediaPlatformsController extends Controller
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
        return Inertia::render('SuperAdmin/SocialMediaPlatforms/Index', [
            'socialMediaTypes' => SocialMediaType::query()->orderBy('sort_order')->get(),
        ]);
    }
}

