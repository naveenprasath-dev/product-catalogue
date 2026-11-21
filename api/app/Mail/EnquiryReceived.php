<?php

declare(strict_types=1);

namespace App\Mail;

use App\Catalogue\Data\EnquiryData;
use App\Catalogue\Data\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to the sales inbox for each customer enquiry. The provider is chosen by MAIL_MAILER only.
 * Sent synchronously for the PoC (ShouldQueue is the next step).
 */
final class EnquiryReceived extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly int $enquiryId,
        public readonly EnquiryData $enquiry,
        public readonly Product $product,
    ) {}

    public function envelope(): Envelope
    {
        // Newlines are stripped by the mailer, and the subject is plain text, so the title is safe here.
        return new Envelope(
            replyTo: [new Address($this->enquiry->email, $this->enquiry->name)],
            subject: 'New enquiry: '.$this->product->title,
        );
    }

    public function content(): Content
    {
        // Views use {{ }} only: every user-supplied value is HTML-escaped.
        return new Content(
            view: 'mail.enquiry-received',
            text: 'mail.enquiry-received-text',
        );
    }
}
