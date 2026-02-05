<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DuplicateContactsFound extends Mailable
{
    use Queueable, SerializesModels;

    public string $source;     // e.g. 'freshsales:leela', 'interakt:dayli', 'shopify:leela'
    public array  $rows;       // array of duplicate rows

    public function __construct(string $source, array $rows)
    {
        $this->source = $source;
        $this->rows   = $rows;
    }

    public function build()
    {
        return $this->subject("Duplicate contacts detected from {$this->source}")
            ->view('emails.duplicates-found');
    }
}
