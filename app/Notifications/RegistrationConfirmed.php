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
        return (new MailMessage)
            ->subject('Inscription confirmée — '.$this->session->name)
            ->greeting('Bonjour '.$notifiable->full_name.' !')
            ->line('Votre inscription à la session **'.$this->session->name.'** a bien été enregistrée.')
            ->line('La date de la session est prévue le **'.$this->session->scheduled_at?->format('d/m/Y à H:i').'**.')
            ->line('Vous recevrez un e-mail lorsque la pré-sélection sera ouverte.')
            ->salutation('Bonne chance ! — L\'équipe LOGIK GAME');
    }
}
