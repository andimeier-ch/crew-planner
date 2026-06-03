<?php

namespace App\Controller;

use App\Entity\SurveyResponse;
use App\Repository\SurveyParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/survey', name: 'api_survey_public_')]
class SurveyPublicController extends AbstractApiController
{
    #[Route('/{token}', name: 'show', methods: ['GET'])]
    public function show(string $token, SurveyParticipantRepository $repo): JsonResponse
    {
        $participant = $repo->findOneBy(['token' => $token]);
        if (!$participant) {
            return $this->notFound('Umfrage nicht gefunden');
        }

        $survey = $participant->getSurvey();
        $eventController = new EventController();

        $responseMap = [];
        foreach ($participant->getResponses() as $response) {
            $responseMap[$response->getEvent()->getId()] = $response->isAvailable();
        }

        return $this->json([
            'isExpired' => $survey->isExpired(),
            'deadline' => $survey->getDeadline()?->format(\DateTimeInterface::ATOM),
            'staff' => ['id' => $participant->getStaff()->getId(), 'name' => $participant->getStaff()->getName()],
            'remark' => $participant->getRemark(),
            'events' => array_map($eventController->normalize(...), $survey->getEvents()->toArray()),
            'responses' => $responseMap,
        ]);
    }

    #[Route('/{token}', name: 'respond', methods: ['POST'])]
    public function respond(string $token, Request $request, SurveyParticipantRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $participant = $repo->findOneBy(['token' => $token]);
        if (!$participant) {
            return $this->notFound('Umfrage nicht gefunden');
        }

        if ($participant->getSurvey()->isExpired()) {
            return $this->json(['error' => 'Die Deadline ist abgelaufen'], 403);
        }

        $body = $this->getBody($request);
        $survey = $participant->getSurvey();

        // Index existing responses by event ID for easy lookup
        $existingResponses = [];
        foreach ($participant->getResponses() as $response) {
            $existingResponses[$response->getEvent()->getId()] = $response;
        }

        // responses: {eventId: bool}
        foreach ($body['responses'] ?? [] as $eventId => $available) {
            $eventId = (int) $eventId;
            $event = null;
            foreach ($survey->getEvents() as $e) {
                if ($e->getId() === $eventId) { $event = $e; break; }
            }
            if (!$event) continue;

            if (isset($existingResponses[$eventId])) {
                $existingResponses[$eventId]->setAvailable((bool) $available);
            } else {
                $response = (new SurveyResponse())
                    ->setParticipant($participant)
                    ->setEvent($event)
                    ->setAvailable((bool) $available);
                $em->persist($response);
            }
        }

        if (array_key_exists('remark', $body)) {
            $participant->setRemark($body['remark']);
        }

        $em->flush();

        return $this->json(['success' => true]);
    }
}