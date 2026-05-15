<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('faq/Index', [
            'userRole' => $request->user()->role->value,
        ]);
    }
}
