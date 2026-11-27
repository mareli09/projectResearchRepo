<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Email at every workflow step, plus the in-app copy on the Notifications page (CLAUDE.md §7 M3, §12).
 */
class WorkflowNotice extends TuaNotification
{
    public function __construct(
        private readonly string $subject,
        private readonly string $body,
        private readonly ?string $url = null,
        private readonly ?string $submission = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    protected function subjectLine(): string
    {
        return $this->subject;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject)->line($this->body);
        if ($this->url !== null) {
            $mail->action('Open in the repository', $this->url);
        }

        return $mail->line('You get an email at every step of the workflow.');
    }

    /** @return array<string, string|null> */
    public function toArray(object $notifiable): array
    {
        return ['subject' => $this->subject, 'body' => $this->body, 'url' => $this->url, 'submission' => $this->submission];
    }
}
