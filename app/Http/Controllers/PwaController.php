<?php

namespace App\Http\Controllers;

use App\Support\ReceptionApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (PWA Recepție). Manifestul (instalare pe ecranul telefonului) și service worker-ul aplicației de recepție.
 * Service worker-ul stă sub /receptie/ ca să aibă scope-ul /receptie/ și nu preia restul site-ului.
 */
class PwaController extends Controller
{
    public function receptionManifest(): JsonResponse
    {
        $name = ReceptionApp::name();

        return response()->json([
            'id' => '/receptie/',
            'name' => $name,
            'short_name' => Str::limit($name, 12, ''),
            'description' => 'Aplicația de recepție: intrări, tokeni și credite.',
            'lang' => 'ro',
            'start_url' => '/receptie/',
            'scope' => '/receptie/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => ReceptionApp::primary(),
            'background_color' => '#F7F5F4',
            'icons' => [
                ['src' => ReceptionApp::iconUrl(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => ReceptionApp::iconUrl(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /** Iconița aplicației (gradient în culorile temei + logo). Adresa poartă ?v=<versiune>, deci se poate ține în cache mult timp. */
    public function receptionIcon(int $size): Response
    {
        return response(ReceptionApp::iconPng($size), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public function receptionServiceWorker(): Response
    {
        return response(view('reception.sw')->render(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
