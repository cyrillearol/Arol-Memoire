<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use RuntimeException;

class BrevoResetPasswordNotification extends ResetPassword
{
    public function via($notifiable): array
    {
        return [config('services.brevo.key') ? 'brevo' : 'mail'];
    }

    public function toBrevo($notifiable): void
    {
        $apiKey = config('services.brevo.key');

        if (! $apiKey) {
            throw new RuntimeException('La cle API Brevo est absente.');
        }

        $resetUrl = $this->resetUrl($notifiable);

        $response = Http::timeout((int) config('services.brevo.timeout', 10))
            ->withHeaders([
                'Accept' => 'application/json',
                'Api-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => [
                    'name' => config('mail.from.name'),
                    'email' => config('mail.from.address'),
                ],
                'to' => [[
                    'email' => $notifiable->getEmailForPasswordReset(),
                    'name' => $notifiable->name,
                ]],
                'subject' => 'Reinitialisation de votre mot de passe',
                'htmlContent' => $this->htmlContent($notifiable->name, $resetUrl),
                'textContent' => $this->textContent($resetUrl),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Brevo a refuse l\'envoi du lien de recuperation : '.$response->body());
        }
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Reinitialisation de votre mot de passe')
            ->line('Nous avons recu une demande de reinitialisation du mot de passe de votre compte.')
            ->action('Reinitialiser le mot de passe', $url)
            ->line(Lang::get('Ce lien expire dans :count minutes.', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))
            ->line('Si vous n\'etes pas a l\'origine de cette demande, ignorez simplement cet e-mail.');
    }

    private function htmlContent(?string $name, string $resetUrl): string
    {
        $safeName = e($name ?: 'Utilisateur');
        $safeUrl = e($resetUrl);
        $appName = e(config('app.name', 'TutorLink'));

        return <<<HTML
        <!doctype html>
        <html lang="fr">
        <body style="margin:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#102033;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:28px 12px;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e4e9f2;">
                            <tr>
                                <td style="padding:28px 30px;border-bottom:1px solid #eef2f7;">
                                    <strong style="font-size:20px;color:#0f2744;">{$appName}</strong>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:30px;">
                                    <h1 style="margin:0 0 14px;font-size:24px;line-height:1.25;color:#0f2744;">Reinitialisation du mot de passe</h1>
                                    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">Bonjour {$safeName},</p>
                                    <p style="margin:0 0 22px;font-size:15px;line-height:1.6;">Nous avons recu une demande de reinitialisation du mot de passe de votre compte.</p>
                                    <p style="margin:0 0 26px;"><a href="{$safeUrl}" style="display:inline-block;background:#0f2744;color:#ffffff;text-decoration:none;border-radius:10px;padding:13px 18px;font-weight:700;">Reinitialiser le mot de passe</a></p>
                                    <p style="margin:0 0 12px;font-size:14px;line-height:1.6;color:#526173;">Ce lien expire dans {$this->expireMinutes()} minutes.</p>
                                    <p style="margin:0;font-size:14px;line-height:1.6;color:#526173;">Si vous n'etes pas a l'origine de cette demande, ignorez simplement cet e-mail.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }

    private function textContent(string $resetUrl): string
    {
        return Str::of("Reinitialisation du mot de passe\n\n")
            ->append('Nous avons recu une demande de reinitialisation du mot de passe de votre compte.', "\n\n")
            ->append('Lien : '.$resetUrl, "\n\n")
            ->append('Ce lien expire dans '.$this->expireMinutes().' minutes.', "\n")
            ->append('Si vous n\'etes pas a l\'origine de cette demande, ignorez simplement cet e-mail.')
            ->toString();
    }

    private function expireMinutes(): int
    {
        return (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
    }
}
