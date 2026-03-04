<?php

namespace App\Notifications;

use App\Models\Session;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlayerRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Session $session,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Résultat de la pré-sélection — '.$this->session->name)
            ->greeting('Bonjour '.$notifiable->full_name.',')
            ->line('Merci d\'avoir participé à la pré-sélection de la session **'.$this->session->name.'**.')
            ->line('Malheureusement, vous n\'avez pas été retenu(e) pour cette session.')
            ->line('Ne vous découragez pas ! D\'autres sessions seront bientôt disponibles.')
            ->salutation('À bientôt ! — L\'équipe LOGIK GAME');
    }
}
