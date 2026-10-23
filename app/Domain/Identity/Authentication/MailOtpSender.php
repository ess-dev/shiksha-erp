<?php

namespace App\Domain\Identity\Authentication;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Students\Queries\GuardianPortalQuery;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Development stand-in "SMS": emails the code to the guardian's address on
 * file (MailHog locally) and also logs it, so the sign-in flow can be tried
 * without an SMS provider. Refuses to run in production.
 */
class MailOtpSender implements OtpSender
{
    public function __construct(
        private readonly LogOtpSender $log,
        private readonly GuardianPortalQuery $guardians,
    ) {}

    public function send(string $phoneE164, string $code, string $schoolName): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The mail OTP driver cannot be used in production. Configure an SMS provider.');
        }

        $this->log->send($phoneE164, $code, $schoolName);

        $emails = $this->guardians->emailsForPhone($phoneE164);

        foreach ($emails as $email) {
            Mail::raw(
                "Your {$schoolName} sign-in code is {$code}. It expires in ".(int) config('shiksha.otp.ttl_minutes').' minutes.',
                fn (Message $message) => $message->to($email)->subject("{$schoolName} sign-in code"),
            );
        }
    }
}
