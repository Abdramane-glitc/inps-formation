<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Notification interne (stockée en base, consultable dans l'application).
 * L'envoi éventuel par e-mail est géré séparément par NotificationService, avec
 * enregistrement du résultat réel du transport.
 */
class NotificationMetier extends Notification
{
    public function __construct(
        public string $categorie,
        public string $titre,
        public string $message,
        public ?string $url = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['categorie' => $this->categorie, 'titre' => $this->titre, 'message' => $this->message, 'url' => $this->url];
    }
}
