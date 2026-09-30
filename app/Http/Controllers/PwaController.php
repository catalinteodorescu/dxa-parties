<?php

namespace App\Http\Controllers;

use App\Support\BarApp;
use App\Support\ParticipantApp;
use App\Support\PwaApp;
use App\Support\ReceptionApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (PWA Recepție / Bar). Manifestul (instalare pe ecranul telefonului), iconița și service worker-ul
 * aplicațiilor PWA. Service worker-ul stă sub /receptie/ sau /bar/ ca să aibă scope-ul propriu și să nu preia restul site-ului.
 */
class PwaController extends Controller
{
    public function receptionManifest(): JsonResponse
    {
        return $this->manifest(ReceptionApp::class, 'Aplicația de recepție: intrări, tokeni și credite.');
    }

    /** Iconița aplicației (gradient în culorile temei + logo). Adresa poartă ?v=<versiune>, deci se poate ține în cache mult timp. */
    public function receptionIcon(int $size): Response
    {
        return $this->icon(ReceptionApp::class, $size);
    }

    public function receptionServiceWorker(): Response
    {
        return $this->serviceWorker('dxa-receptie-static-v1', 'Recepție');
    }

    public function barManifest(): JsonResponse
    {
        return $this->manifest(BarApp::class, 'Aplicația de bar: vânzare de produse și raportare.');
    }

    public function barIcon(int $size): Response
    {
        return $this->icon(BarApp::class, $size);
    }

    public function barServiceWorker(): Response
    {
        return $this->serviceWorker('dxa-bar-static-v1', 'Bar');
    }

    /** DXA: adaugat (Aplicația participanților). PWA în rădăcina domeniului (scope „/”), cu propriul manifest și service worker. */
    public function participantManifest(): JsonResponse
    {
        return $this->manifest(ParticipantApp::class, 'Petreceri, anunțuri și bilete Dance Xplosion Academy.');
    }

    public function participantIcon(int $size): Response
    {
        return $this->icon(ParticipantApp::class, $size);
    }

    /**
     * Service worker cu scope „/”: NU interceptează /admin, /receptie, /bar (au PWA-uri proprii sau nu au deloc) — vezi
     * participant/sw.blade.php. Pentru siguranță stă la /sw.js (un service worker nu poate controla mai sus de calea lui).
     */
    public function participantServiceWorker(): Response
    {
        return response(view('participant.sw', ['cache' => 'dxa-app-static-v1'])->render(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /** @param  class-string<PwaApp>  $app */
    private function manifest(string $app, string $description): JsonResponse
    {
        $name = $app::name();

        return response()->json([
            'id' => $app::URL_PREFIX,
            'name' => $name,
            'short_name' => Str::limit($name, 12, ''),
            'description' => $description,
            'lang' => 'ro',
            'start_url' => $app::URL_PREFIX,
            'scope' => $app::URL_PREFIX,
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => $app::manifestThemeColor(),
            'background_color' => $app::manifestBackground(),
            'icons' => [
                ['src' => $app::iconUrl(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => $app::iconUrl(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /** @param  class-string<PwaApp>  $app */
    private function icon(string $app, int $size): Response
    {
        return response($app::iconPng($size), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function serviceWorker(string $cache, string $label): Response
    {
        return response(view('reception.sw', ['cache' => $cache, 'label' => $label])->render(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
