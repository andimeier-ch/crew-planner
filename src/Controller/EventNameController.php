<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EventName;
use App\Repository\EventNameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/event-names', name: 'api_event_names_')]
class EventNameController extends AbstractApiController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EventNameRepository $repo): JsonResponse
    {
        return $this->json(array_map($this->normalize(...), $repo->findBy([], ['name' => 'ASC'])));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
    {
        $body = $this->getBody($request);
        $eventName = (new EventName())->setName(trim((string) ($body['name'] ?? '')));

        $violations = $validator->validate($eventName);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $em->persist($eventName);
        $em->flush();

        return $this->json($this->normalize($eventName), 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        EventName $eventName,
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        $body = $this->getBody($request);
        if (isset($body['name'])) {
            $eventName->setName(trim((string) $body['name']));
        }

        $violations = $validator->validate($eventName);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $em->flush();

        return $this->json($this->normalize($eventName));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(EventName $eventName, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($eventName);
        $em->flush();

        return $this->json(null, 204);
    }

    private function normalize(EventName $eventName): array
    {
        return ['id' => $eventName->getId(), 'name' => $eventName->getName()];
    }
}
