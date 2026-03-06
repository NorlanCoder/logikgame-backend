<?php

namespace App\Notifications;

use App\Models\Registration;
use App\Models\Session;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public Session $session;

    public function __construct(
        public Registration $registration,
    ) {
        $this->session = $registration->session;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $quizUrl = rtrim(config('app.frontend_url'), '/').'/preselection/'
            .$this->session->id
            .'?token='.$this->registration->preselection_token;

        return (new MailMessage)
            ->subject('Inscription confirmée — '.$this->session->name)
            ->greeting('Bonjour '.$notifiable->full_name.' !')
            ->line('Votre inscription à la session **'.$this->session->name.'** a bien été enregistrée.')
            ->line('La date de la session est prévue le **'.$this->session->scheduled_at?->format('d/m/Y à H:i').'**.')
            ->line('Passez dès maintenant le test de pré-sélection en cliquant sur le bouton ci-dessous.')
            ->action('Passer le test de pré-sélection', $quizUrl)
            ->line('Ce lien vous donnera accès directement à votre test avec votre identifiant d\'inscription.')
            ->salutation('Bonne chance ! — L\'équipe LOGIK GAME');
    }
}
