<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SurveyParticipant;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Sends survey invitations, reminders and assignments to a participant, using the survey's own subject and body.
 * Sender and reply address come from MAILER_FROM (config/packages/mailer.yaml).
 */
class SurveyMailer
{
    public const INVITATION_SUBJECT = 'Umfrage: {surveyTitle}';

    public const INVITATION_BODY = <<<'TEXT'
        Hallo {firstName}

        Bitte trage bis am {deadline} ein, an welchen Daten du für «{surveyTitle}» verfügbar bist:

        {surveyLink}

        Der Link ist persönlich – bitte nicht weiterleiten.

        Vielen Dank!
        Musik FEG Langenthal
        TEXT;

    public const REMINDER_SUBJECT = 'Erinnerung: {surveyTitle}';

    public const REMINDER_BODY = <<<'TEXT'
        Hallo {firstName}

        Von dir fehlt noch die Antwort zur Umfrage «{surveyTitle}». Bitte trage so bald wie möglich ein, an welchen Daten du verfügbar bist (Deadline: {deadline}):

        {surveyLink}

        Vielen Dank!
        Musik FEG Langenthal
        TEXT;

    public const ASSIGNMENT_SUBJECT = 'Einteilung: {surveyTitle}';

    public const ASSIGNMENT_BODY = <<<'TEXT'
        Hallo {firstName}

        Die Einteilung für «{surveyTitle}» ist fertig. Hier siehst du, wann du eingeteilt bist, und den gesamten Plan:

        {plan}

        Vielen Dank für deinen Einsatz!
        Musik FEG Langenthal
        TEXT;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly AssignmentPlan $assignmentPlan,
        #[Autowire(env: 'DEFAULT_URI')]
        private readonly string $appUrl,
    ) {
    }

    /**
     * @throws TransportExceptionInterface when the mail server rejects or cannot be reached
     */
    public function sendInvitation(SurveyParticipant $participant): void
    {
        $survey = $participant->getSurvey();
        $this->send($participant, $survey->getInvitationSubject(), $survey->getInvitationBody());
    }

    /**
     * @throws TransportExceptionInterface when the mail server rejects or cannot be reached
     */
    public function sendReminder(SurveyParticipant $participant): void
    {
        $survey = $participant->getSurvey();
        $this->send($participant, $survey->getReminderSubject(), $survey->getReminderBody());
    }

    /**
     * Sends the assignment plan as HTML with a plain-text alternative. {plan} marks where the plan goes;
     * if the admin removed it from the body, the plan is appended.
     *
     * @param array $plan from AssignmentPlan::build(), built once per survey
     * @throws TransportExceptionInterface when the mail server rejects or cannot be reached
     */
    public function sendAssignment(SurveyParticipant $participant, array $plan): void
    {
        $survey = $participant->getSurvey();
        $staff = $participant->getStaff();
        $placeholders = $this->placeholders($participant);

        $body = $survey->getAssignmentBody();
        if (!str_contains($body, '{plan}')) {
            $body .= "\n\n{plan}";
        }
        $body = strtr($body, $placeholders);

        $text = str_replace('{plan}', $this->assignmentPlan->text($plan, $staff), $body);
        $html = str_replace(
            '{plan}',
            $this->assignmentPlan->html($plan, $staff),
            nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        );

        $email = (new Email())
            ->to($staff->getEmail())
            ->subject(strtr($survey->getAssignmentSubject(), $placeholders))
            ->text($text)
            ->html('<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1f2937">'
                . $html . '</div>');

        $this->mailer->send($email);
        $participant->setLastAssignmentSentAt(new \DateTimeImmutable());
    }

    public function surveyLink(SurveyParticipant $participant): string
    {
        return rtrim($this->appUrl, '/') . '/survey/' . $participant->getToken();
    }

    private function send(SurveyParticipant $participant, string $subject, string $body): void
    {
        $placeholders = $this->placeholders($participant);

        $email = (new Email())
            ->to($participant->getStaff()->getEmail())
            ->subject(strtr($subject, $placeholders))
            ->text(strtr($body, $placeholders));

        $this->mailer->send($email);
        $participant->setLastEmailedAt(new \DateTimeImmutable());
    }

    /**
     * @return array<string, string>
     */
    private function placeholders(SurveyParticipant $participant): array
    {
        $survey = $participant->getSurvey();
        $staff = $participant->getStaff();

        return [
            '{firstName}' => $staff->getFirstName(),
            '{lastName}' => $staff->getLastName(),
            '{surveyTitle}' => $survey->getTitle(),
            '{surveyLink}' => $this->surveyLink($participant),
            '{deadline}' => $survey->getDeadline()?->format('d.m.Y') ?? '',
        ];
    }
}
