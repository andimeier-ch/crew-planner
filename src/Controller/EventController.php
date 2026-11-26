<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Survey;
use App\Repository\AssignmentRepository;
use App\Repository\StaffRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_events_')]
class EventController extends AbstractApiController
{
    #[Route('/events/{id}', name: 'show', methods: ['GET'])]
    public function show(Event $event): JsonResponse
    {
        return $this->json($this->normalize($event));
    }

    #[Route('/surveys/{id}/events', name: 'create', methods: ['POST'])]
    public function create(Survey $survey, Request $request, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
    {
        $event = new Event();
        $error = $this->applyBody($event, $this->getBody($request), $validator);
        if ($error) {
            return $error;
        }

        $survey->addEvent($event);
        $em->persist($event);
        $em->flush();

        return $this->json($this->normalize($event), 201);
    }

    #[Route('/events/{id}', name: 'update', methods: ['PUT'])]
    public function update(Event $event, Request $request, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
    {
        $error = $this->applyBody($event, $this->getBody($request), $validator);
        if ($error) {
            return $error;
        }

        $em->flush();

        return $this->json($this->normalize($event));
    }

    /**
     * Sets or clears the event's leader. Only an assigned staff member with the leader flag can lead an event.
     */
    #[Route('/events/{id}/leader', name: 'leader', methods: ['PUT'])]
    public function setLeader(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        StaffRepository $staffRepo,
        AssignmentRepository $assignmentRepo,
    ): JsonResponse {
        $staffId = $this->getBody($request)['staffId'] ?? null;
        $leader = null;

        if ($staffId !== null) {
            $leader = $staffRepo->find((int) $staffId);
            if (!$leader) {
                return $this->notFound('Staff nicht gefunden');
            }
            if (!$leader->isLeader()) {
                return $this->json(['error' => 'Nur Staffs mit Leader-Flag können die Leitung übernehmen.'], 422);
            }
            if (!$assignmentRepo->findOneBy(['staff' => $leader, 'event' => $event])) {
                return $this->json(['error' => 'Die Leitung muss für dieses Event eingeteilt sein.'], 422);
            }
        }

        $event->setLeader($leader);
        $em->flush();

        return $this->json($this->normalize($event));
    }

    #[Route('/events/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Event $event, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($event);
        $em->flush();

        return $this->json(null, 204);
    }

    public function normalize(Event $event): array
    {
        return [
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'date' => $event->getDate()?->format('Y-m-d'),
            'description' => $event->getDescription(),
            'leaderId' => $event->getLeader()?->getId(),
        ];
    }

    /**
     * Applies title/date/description from the request body and validates the event.
     * Returns an error response, or null if the event is valid.
     */
    private function applyBody(Event $event, array $body, ValidatorInterface $validator): ?JsonResponse
    {
        if (isset($body['title'])) $event->setTitle($body['title']);
        if (array_key_exists('description', $body)) $event->setDescription($body['description']);
        if (!empty($body['date'])) {
            try {
                $event->setDate(new \DateTime($body['date']));
            } catch (\Exception) {
                return $this->json(['error' => 'Ungültiges Datum'], 422);
            }
        }

        $violations = $validator->validate($event);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        return null;
    }
}
