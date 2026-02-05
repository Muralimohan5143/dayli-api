<?php 

namespace app\Mail;

// Mail::to($this->contact)->send(new SendOtpMail($otp));

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    public function build()
    {
        return $this->subject('Your OTP')
                    ->view('emails.otp');
    }
}
