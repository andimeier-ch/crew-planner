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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/surveys', name: 'api_surveys_')]
class SurveyController extends AbstractApiController
{
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

        foreach ($body['staffIds'] ?? [] as $staffId) {
            $staff = $staffRepo->find($staffId);
            if ($staff) {
                $participant = (new SurveyParticipant())
                    ->setSurvey($survey)
                    ->setStaff($staff);
                $survey->getParticipants()->add($participant);
                $em->persist($participant);
            }
        }

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
        $survey->setStatus(SurveyStatus::Closed);
        $em->flush();

        return $this->json($this->normalizeFull($survey));
    }

    #[Route('/{id}/reopen', name: 'reopen', methods: ['POST'])]
    public function reopen(Survey $survey, EntityManagerInterface $em): JsonResponse
    {
        $survey->setStatus(SurveyStatus::Open);
        $em->flush();

        return $this->json($this->normalizeFull($survey));
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
        SurveyParticipantRepository $participantRepo,
    ): JsonResponse {
        $events = $survey->getEvents()->toArray();
        $participants = $participantRepo->findBy(['survey' => $survey]);
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

    private function normalizeFull(Survey $survey): array
    {
        $eventController = new EventController();
        $staffController = new StaffController();

        return [
            'id' => $survey->getId(),
            'title' => $survey->getTitle(),
            'deadline' => $survey->getDeadline()?->format(\DateTimeInterface::ATOM),
            'status' => $survey->getStatus()->value,
            'isDeadlinePassed' => $survey->isDeadlinePassed(),
            'events' => array_map($eventController->normalize(...), $survey->getEvents()->toArray()),
            'participants' => array_map(
                fn($p) => [
                    'id' => $p->getId(),
                    'token' => $p->getToken(),
                    'remark' => $p->getRemark(),
                    'staff' => $staffController->normalize($p->getStaff()),
                ],
                $survey->getParticipants()->toArray()
            ),
        ];
    }
}