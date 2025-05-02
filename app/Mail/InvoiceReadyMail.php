<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class InvoiceReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $data;

    /**
     * Create a new message instance.
     */
    public function __construct(array $data)
    {
        $this->data = [
            'partner_address' => $data['partner_address'] ?? '',
            'inv_no'          => $data['inv_no'] ?? '',
            'bp_code'         => $data['bp_code'] ?? '',
            'status'          => $data['status'] ?? '',
            'total_amount'    => $data['total_amount'] ?? 0,
            'plan_date'       => $data['plan_date'] ?? '',
            'filepath'        => $data['filepath'] ?? '',
            'tax_amount'      => $data['tax_amount'] ?? 0,
            'pph_amount'      => $data['pph_amount'] ?? 0,
            'url'             => '',
        ];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->data['inv_no']} Ready For Payment",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.sent-receipt-email',
            with: [
                'data'=> $this->data,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->data['filepath'])
                ->as("receipts/RECEIPT_{$this->data['inv_no']}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
