<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $r): View
    {
        return view('notifications.index', ['notifications' => $r->user()->notifications()->paginate(25)]);
    }

    public function lue(Request $r, string $id): RedirectResponse
    {
        $n = $r->user()->notifications()->whereKey($id)->firstOrFail(); // uniquement ses propres notifications
        $n->markAsRead();
        $url = $n->data['url'] ?? null;

        // N'accepte que les URL internes à l'application (pas de redirection ouverte).
        // Uniquement vers l'application elle-même : même schéma, hôte et port (pas de redirection ouverte)
        $base = parse_url(url('/'));
        $cible = $url ? parse_url($url) : false;
        $interne = $cible && ($cible['scheme'] ?? null) === ($base['scheme'] ?? null) && ($cible['host'] ?? null) === ($base['host'] ?? null)
            && ($cible['port'] ?? null) === ($base['port'] ?? null);

        return $interne ? redirect()->to($url) : back();
    }

    public function toutLire(Request $r): RedirectResponse
    {
        $r->user()->unreadNotifications->markAsRead();

        return back()->with('succes', 'Toutes les notifications sont marquées comme lues.');
    }
}
