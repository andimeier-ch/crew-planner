<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/events', name: 'api_events_')]
class EventController extends AbstractApiController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EventRepository $repo): JsonResponse
    {
        return $this->json(array_map($this->normalize(...), $repo->findBy([], ['date' => 'ASC'])));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Event $event): JsonResponse
    {
        return $this->json($this->normalize($event));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
    {
        $body = $this->getBody($request);
        $event = (new Event())
            ->setTitle($body['title'] ?? '')
            ->setDescription($body['description'] ?? null);

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

        $em->persist($event);
        $em->flush();

        return $this->json($this->normalize($event), 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Event $event, Request $request, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
    {
        $body = $this->getBody($request);
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

        $em->flush();

        return $this->json($this->normalize($event));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
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
        ];
    }
}