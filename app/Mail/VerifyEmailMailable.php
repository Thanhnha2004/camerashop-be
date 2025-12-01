<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $verificationUrl;
    public $userName;

    public function __construct($verificationUrl, $userName)
    {
        $this->verificationUrl = $verificationUrl;
        $this->userName = $userName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('tranthanhnha.28032004@gmail.com', 'Camerashop'),
            subject: 'Xác thực địa chỉ Email của bạn'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'vendor.mail.verify-email',
            with: [
                'verificationUrl' => $this->verificationUrl,
                'userName' => $this->userName
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}