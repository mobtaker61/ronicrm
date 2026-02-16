<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FrontController extends Controller
{
    public function welcome(): Response|HttpResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Front/Welcome');
    }

    public function privacy(): Response
    {
        return Inertia::render('Front/PrivacyPolicy');
    }

    public function terms(): Response
    {
        return Inertia::render('Front/TermsAndConditions');
    }
}
