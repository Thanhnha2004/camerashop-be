<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $messageText;

    public function __construct($message)
    {
        $this->messageText = $message;
    }

    /**
     * Envelope (from, to, subject)
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('tranthanhnha.28032004@gmail.com', 'Camerashop'),
            subject: 'Send test email'
        );
    }

    /**
     * Content (view + variables)
     */
    public function content(): Content
    {
        return new Content(
            view: 'vendor.mail.test-mail',
            with: [
                'mailMessage' => $this->messageText
            ]
        );
    }

    /**
     * Attachments
     */
    public function attachments(): array
    {
        return [];
    }
}
