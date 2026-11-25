<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SurveyParticipant;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Sends the survey invitation to a participant, using the survey's own subject and body.
 * Sender and reply address come from MAILER_FROM (config/packages/mailer.yaml).
 */
class InvitationMailer
{
    public const DEFAULT_SUBJECT = 'Umfrage: {surveyTitle}';

    public const DEFAULT_BODY = <<<'TEXT'
        Hallo {firstName}

        Bitte trage bis am {deadline} ein, an welchen Daten du für «{surveyTitle}» verfügbar bist:

        {surveyLink}

        Der Link ist persönlich – bitte nicht weiterleiten.

        Vielen Dank!
        Musik FEG Langenthal
        TEXT;

    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'DEFAULT_URI')]
        private readonly string $appUrl,
    ) {
    }

    /**
     * @throws TransportExceptionInterface when the mail server rejects or cannot be reached
     */
    public function send(SurveyParticipant $participant): void
    {
        $survey = $participant->getSurvey();
        $staff = $participant->getStaff();

        $placeholders = [
            '{firstName}' => $staff->getFirstName(),
            '{lastName}' => $staff->getLastName(),
            '{surveyTitle}' => $survey->getTitle(),
            '{surveyLink}' => $this->surveyLink($participant),
            '{deadline}' => $survey->getDeadline()?->format('d.m.Y') ?? '',
        ];

        $email = (new Email())
            ->to($staff->getEmail())
            ->subject(strtr($survey->getInvitationSubject(), $placeholders))
            ->text(strtr($survey->getInvitationBody(), $placeholders));

        $this->mailer->send($email);
        $participant->setLastEmailedAt(new \DateTimeImmutable());
    }

    public function surveyLink(SurveyParticipant $participant): string
    {
        return rtrim($this->appUrl, '/') . '/survey/' . $participant->getToken();
    }
}
