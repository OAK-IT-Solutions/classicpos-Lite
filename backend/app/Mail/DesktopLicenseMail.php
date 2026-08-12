<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class DesktopLicenseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $businessName,
        public string $licenseKey,
        public string $plan,
        public ?string $expiresAt,
    ) {}

    public function build(): Content
    {
        return $this->subject('Your ClassicPOS Desktop License Key')
            ->view('emails.desktop-license', [
                'businessName' => $this->businessName,
                'licenseKey' => $this->licenseKey,
                'plan' => ucfirst($this->plan),
                'expiresAt' => $this->expiresAt
                    ? \Carbon\Carbon::parse($this->expiresAt)->format('F j, Y')
                    : 'Never',
            ]);
    }
}
