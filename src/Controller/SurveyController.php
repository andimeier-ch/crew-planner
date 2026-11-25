<?php

namespace App\Controller;

use App\Entity\Assignment;
use App\Entity\Survey;
use App\Entity\SurveyParticipant;
use App\Enum\SurveyStatus;
use App\Repository\AssignmentRepository;
use App\Repository\StaffRepository;
use App\Repository\SurveyParticipantRepository;
use App\Repository\SurveyRepository;
use App\Repository\SurveyResponseRepository;
use App\Service\SurveyMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/surveys', name: 'api_surveys_')]
class SurveyController extends AbstractApiController
{
    public function __construct(
        private readonly AssignmentRepository $assignmentRepo,
        private readonly SurveyResponseRepository $responseRepo,
        private readonly SurveyParticipantRepository $participantRepo,
        private readonly SurveyMailer $surveyMailer,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(SurveyRepository $repo): JsonResponse
    {
        return $this->json(array_map($this->normalizeSummary(...), $repo->findBy([], ['id' => 'DESC'])));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Survey $survey): JsonResponse
    {
        return $this->json($this->normalizeFull($survey));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        StaffRepository $staffRepo,
    ): JsonResponse {
        $body = $this->getBody($request);
        $survey = (new Survey())->setTitle(trim($body['title'] ?? ''));

        if (!empty($body['deadline'])) {
            try {
                $survey->setDeadline(new \DateTime($body['deadline']));
            } catch (\Exception) {
                return $this->json(['error' => 'Ungültige Deadline'], 422);
            }
        }

        $this->addParticipants($survey, $body['staffIds'] ?? [], $staffRepo, $em);

        $violations = $validator->validate($survey);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $em->persist($survey);
        $em->flush();

        return $this->json($this->normalizeFull($survey), 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Survey $survey, Request $request, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
    {
        $body = $this->getBody($request);
        if (isset($body['title'])) $survey->setTitle(trim($body['title']));
        if (isset($body['invitationSubject'])) $survey->setInvitationSubject(trim($body['invitationSubject']));
        if (isset($body['invitationBody'])) $survey->setInvitationBody(trim($body['invitationBody']));
        if (isset($body['reminderSubject'])) $survey->setReminderSubject(trim($body['reminderSubject']));
        if (isset($body['reminderBody'])) $survey->setReminderBody(trim($body['reminderBody']));
        if (!empty($body['deadline'])) {
            try {
                $survey->setDeadline(new \DateTime($body['deadline']));
            } catch (\Exception) {
                return $this->json(['error' => 'Ungültige Deadline'], 422);
            }
        }

        $violations = $validator->validate($survey);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $em->flush();

        return $this->json($this->normalizeFull($survey));
    }

    #[Route('/{id}/close', name: 'close', methods: ['POST'])]
    public function close(Survey $survey, EntityManagerInterface $em): JsonResponse
    {
        if (!$survey->isOpen()) {
            return $this->json(['error' => 'Nur offene Umfragen können geschlossen werden.'], 409);
        }
        $survey->setStatus(SurveyStatus::Closed);
        $em->flush();

        return $this->json($this->normalizeFull($survey));
    }

    #[Route('/{id}/reopen', name: 'reopen', methods: ['POST'])]
    public function reopen(Survey $survey, EntityManagerInterface $em): JsonResponse
    {
        if (!$survey->isClosed()) {
            return $this->json(['error' => 'Nur geschlossene Umfragen können wieder geöffnet werden.'], 409);
        }
        $survey->setStatus(SurveyStatus::Open);
        $em->flush();

        return $this->json($this->normalizeFull($survey));
    }

    /**
     * Sends the invitation to every participant with an e-mail address and opens the survey.
     * The survey stays a draft only if every single send failed.
     */
    #[Route('/{id}/send-invitations', name: 'send_invitations', methods: ['POST'])]
    public function sendInvitations(Survey $survey, EntityManagerInterface $em): JsonResponse
    {
        if (!$survey->isDraft()) {
            return $this->json(['error' => 'Einladungen können nur für Entwürfe gesendet werden.'], 409);
        }

        [$sent, $mailErrors] = $this->sendEach(
            $this->participantRepo->findBySurveyOrderedByName($survey),
            $this->surveyMailer->sendInvitation(...),
        );

        if ($sent > 0 || $mailErrors === []) {
            $survey->setStatus(SurveyStatus::Open);
        }
        $em->flush();

        return $this->json(array_merge($this->normalizeFull($survey), ['mailErrors' => $mailErrors]));
    }

    /**
     * Sends the reminder to every participant with an e-mail address who has not responded yet.
     */
    #[Route('/{id}/send-reminders', name: 'send_reminders', methods: ['POST'])]
    public function sendReminders(Survey $survey, EntityManagerInterface $em): JsonResponse
    {
        if (!$survey->isOpen()) {
            return $this->json(['error' => 'Erinnerungen können nur für offene Umfragen gesendet werden.'], 409);
        }

        $pending = array_filter(
            $this->participantRepo->findBySurveyOrderedByName($survey),
            fn(SurveyParticipant $p) => $p->getResponses()->isEmpty(),
        );
        [$sent, $mailErrors] = $this->sendEach($pending, $this->surveyMailer->sendReminder(...));
        $em->flush();

        return $this->json(array_merge($this->normalizeFull($survey), [
            'sentCount' => $sent,
            'mailErrors' => $mailErrors,
        ]));
    }

    #[Route('/{id}/participants/{participantId}/send-invitation', name: 'participants_send_invitation', methods: ['POST'])]
    public function sendInvitation(Survey $survey, int $participantId, EntityManagerInterface $em): JsonResponse
    {
        return $this->sendOne($survey, $participantId, $this->surveyMailer->sendInvitation(...), $em);
    }

    #[Route('/{id}/participants/{participantId}/send-reminder', name: 'participants_send_reminder', methods: ['POST'])]
    public function sendReminder(Survey $survey, int $participantId, EntityManagerInterface $em): JsonResponse
    {
        return $this->sendOne($survey, $participantId, $this->surveyMailer->sendReminder(...), $em);
    }

    #[Route('/{id}/participants', name: 'participants_add', methods: ['POST'])]
    public function addParticipantsAction(
        Survey $survey,
        Request $request,
        EntityManagerInterface $em,
        StaffRepository $staffRepo,
    ): JsonResponse {
        $this->addParticipants($survey, $this->getBody($request)['staffIds'] ?? [], $staffRepo, $em);
        $em->flush();

        return $this->json($this->normalizeFull($survey));
    }

    #[Route('/{id}/participants/{participantId}', name: 'participants_remove', methods: ['DELETE'])]
    public function removeParticipant(
        Survey $survey,
        int $participantId,
        SurveyParticipantRepository $participantRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $participant = $participantRepo->find($participantId);
        if (!$participant || $participant->getSurvey() !== $survey) {
            return $this->notFound('Teilnehmer nicht gefunden');
        }

        // Assignments reference staff + event, not the participant, so they are not cascaded.
        $this->assignmentRepo->deleteForStaffAndEvents($participant->getStaff(), $survey->getEvents()->toArray());

        $survey->getParticipants()->removeElement($participant);
        $em->remove($participant);
        $em->flush();

        return $this->json(null, 204);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Survey $survey, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($survey);
        $em->flush();

        return $this->json(null, 204);
    }

    #[Route('/{id}/matrix', name: 'matrix', methods: ['GET'])]
    public function matrix(
        Survey $survey,
        AssignmentRepository $assignmentRepo,
    ): JsonResponse {
        $events = $survey->getEvents()->toArray();
        $participants = $this->participantRepo->findBySurveyOrderedByName($survey);
        $eventIds = array_map(fn($e) => $e->getId(), $events);

        $assignments = $assignmentRepo->findBy(['event' => $events]);

        $staffController = new StaffController();
        $eventController = new EventController();

        $staffRows = [];
        foreach ($participants as $participant) {
            $staff = $participant->getStaff();

            // Build availability map: eventId -> bool
            $availability = array_fill_keys($eventIds, null);
            foreach ($participant->getResponses() as $response) {
                $availability[$response->getEvent()->getId()] = $response->isAvailable();
            }

            // Build assignment list for this staff
            $staffAssignments = array_values(array_filter(
                array_map(fn(Assignment $a) => $a->getStaff()->getId() === $staff->getId()
                    ? ['eventId' => $a->getEvent()->getId(), 'skillId' => $a->getSkill()->getId(), 'assignmentId' => $a->getId()]
                    : null,
                    $assignments
                )
            ));

            $staffRows[] = array_merge($staffController->normalize($staff), [
                'participantId' => $participant->getId(),
                'remark' => $participant->getRemark(),
                'availability' => $availability,
                'assignments' => $staffAssignments,
            ]);
        }

        return $this->json([
            'survey' => $this->normalizeSummary($survey),
            'events' => array_map($eventController->normalize(...), $events),
            'staffs' => $staffRows,
        ]);
    }

    private function normalizeSummary(Survey $survey): array
    {
        return [
            'id' => $survey->getId(),
            'title' => $survey->getTitle(),
            'deadline' => $survey->getDeadline()?->format(\DateTimeInterface::ATOM),
            'status' => $survey->getStatus()->value,
            'isDeadlinePassed' => $survey->isDeadlinePassed(),
            'eventCount' => $survey->getEvents()->count(),
            'participantCount' => $survey->getParticipants()->count(),
        ];
    }

    /**
     * Adds the given staff members as participants, skipping unknown IDs and staff already in the survey.
     */
    private function addParticipants(
        Survey $survey,
        array $staffIds,
        StaffRepository $staffRepo,
        EntityManagerInterface $em,
    ): void {
        $existingStaffIds = array_map(
            fn(SurveyParticipant $p) => $p->getStaff()->getId(),
            $survey->getParticipants()->toArray(),
        );

        foreach (array_unique(array_map('intval', $staffIds)) as $staffId) {
            if (in_array($staffId, $existingStaffIds, true)) {
                continue;
            }
            $staff = $staffRepo->find($staffId);
            if ($staff) {
                $participant = (new SurveyParticipant())
                    ->setSurvey($survey)
                    ->setStaff($staff);
                $survey->getParticipants()->add($participant);
                $em->persist($participant);
            }
        }
    }

    /**
     * Sends a mail to each participant with an e-mail address; transport errors are collected, not thrown.
     *
     * @param SurveyParticipant[] $participants
     * @param callable(SurveyParticipant): void $send
     * @return array{int, list<array{participantId: int, name: string, error: string}>} sent count and errors
     */
    private function sendEach(array $participants, callable $send): array
    {
        $sent = 0;
        $mailErrors = [];
        foreach ($participants as $participant) {
            if (!$participant->getStaff()->getEmail()) {
                continue;
            }
            try {
                $send($participant);
                $sent++;
            } catch (TransportExceptionInterface $e) {
                $mailErrors[] = [
                    'participantId' => $participant->getId(),
                    'name' => $participant->getStaff()->getFullName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [$sent, $mailErrors];
    }

    /**
     * Sends a mail to a single participant of an open survey.
     *
     * @param callable(SurveyParticipant): void $send
     */
    private function sendOne(Survey $survey, int $participantId, callable $send, EntityManagerInterface $em): JsonResponse
    {
        $participant = $this->participantRepo->find($participantId);
        if (!$participant || $participant->getSurvey() !== $survey) {
            return $this->notFound('Teilnehmer nicht gefunden');
        }
        if (!$survey->isOpen()) {
            return $this->json(['error' => 'E-Mails können nur für offene Umfragen gesendet werden.'], 409);
        }
        if (!$participant->getStaff()->getEmail()) {
            return $this->json(['error' => 'Für diese Person ist keine E-Mail-Adresse erfasst.'], 422);
        }

        try {
            $send($participant);
        } catch (TransportExceptionInterface $e) {
            return $this->json(['error' => 'E-Mail konnte nicht gesendet werden: ' . $e->getMessage()], 502);
        }
        $em->flush();

        return $this->json($this->normalizeParticipant($participant));
    }

    private function normalizeFull(Survey $survey): array
    {
        $eventController = new EventController();

        $responsesByEvent = $this->responseRepo->countByEventForSurvey($survey);
        $assignmentsByEvent = $this->assignmentRepo->countByEventForSurvey($survey);
        $assignmentsByStaff = $this->assignmentRepo->countByStaffForSurvey($survey);

        return [
            'id' => $survey->getId(),
            'title' => $survey->getTitle(),
            'deadline' => $survey->getDeadline()?->format(\DateTimeInterface::ATOM),
            'status' => $survey->getStatus()->value,
            'isDeadlinePassed' => $survey->isDeadlinePassed(),
            'invitationSubject' => $survey->getInvitationSubject(),
            'invitationBody' => $survey->getInvitationBody(),
            'reminderSubject' => $survey->getReminderSubject(),
            'reminderBody' => $survey->getReminderBody(),
            'events' => array_map(
                fn($e) => array_merge($eventController->normalize($e), [
                    'responseCount' => $responsesByEvent[$e->getId()] ?? 0,
                    'assignmentCount' => $assignmentsByEvent[$e->getId()] ?? 0,
                ]),
                $survey->getEvents()->toArray()
            ),
            'participants' => array_map(
                fn($p) => $this->normalizeParticipant($p, $assignmentsByStaff[$p->getStaff()->getId()] ?? 0),
                $this->participantRepo->findBySurveyOrderedByName($survey)
            ),
        ];
    }

    private function normalizeParticipant(SurveyParticipant $p, ?int $assignmentCount = null): array
    {
        if ($assignmentCount === null) {
            $assignmentCount = $this->assignmentRepo->countByStaffForSurvey($p->getSurvey())[$p->getStaff()->getId()] ?? 0;
        }

        return [
            'id' => $p->getId(),
            'token' => $p->getToken(),
            'surveyLink' => $this->surveyMailer->surveyLink($p),
            'remark' => $p->getRemark(),
            'responseCount' => $p->getResponses()->count(),
            'assignmentCount' => $assignmentCount,
            'lastEmailedAt' => $p->getLastEmailedAt()?->format(\DateTimeInterface::ATOM),
            'staff' => (new StaffController())->normalize($p->getStaff()),
        ];
    }
}