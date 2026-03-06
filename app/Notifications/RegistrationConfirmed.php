<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Registration $registration,
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
        $session = $this->registration->session;

        $quizUrl = rtrim(config('app.frontend_url'), '/').'/preselection/'
            .$session->id
            .'?token='.$this->registration->preselection_token;

        return (new MailMessage)
            ->subject('Inscription confirmée — '.$session->name)
            ->greeting('Bonjour '.$notifiable->full_name.' !')
            ->line('Votre inscription à la session **'.$session->name.'** a bien été enregistrée.')
            ->line('La date de la session est prévue le **'.$session->scheduled_at?->format('d/m/Y à H:i').'**.')
            ->line('Passez dès maintenant le test de pré-sélection en cliquant sur le bouton ci-dessous.')
            ->action('Passer le test de pré-sélection', $quizUrl)
            ->line('Ce lien est personnel et sécurisé — ne le partagez pas.')
            ->salutation('Bonne chance ! — L\'équipe LOGIK GAME');
    }
}
