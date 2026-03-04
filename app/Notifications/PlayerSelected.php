<?php

namespace App\Notifications;

use App\Models\Session;
use App\Models\SessionPlayer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlayerSelected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Session $session,
        public SessionPlayer $sessionPlayer,
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
        $joinUrl = config('app.frontend_url', config('app.url'))
            .'/join?token='.$this->sessionPlayer->access_token;

        return (new MailMessage)
            ->subject('Félicitations ! Vous êtes sélectionné — '.$this->session->name)
            ->greeting('Bonjour '.$notifiable->full_name.' !')
            ->line('Vous avez été sélectionné pour participer à la session **'.$this->session->name.'** !')
            ->line('Utilisez le lien ci-dessous pour rejoindre la salle de jeu :')
            ->action('Rejoindre la partie', $joinUrl)
            ->line('**Attention :** Ce lien est personnel et unique. Ne le partagez pas.')
            ->line('La session débutera le **'.$this->session->scheduled_at?->format('d/m/Y à H:i').'**.')
            ->salutation('À tout de suite ! — L\'équipe LOGIK GAME');
    }
}
