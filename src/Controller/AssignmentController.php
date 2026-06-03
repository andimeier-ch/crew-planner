<?php

namespace App\Controller;

use App\Entity\Assignment;
use App\Repository\AssignmentRepository;
use App\Repository\EventRepository;
use App\Repository\SkillRepository;
use App\Repository\StaffRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/assignments', name: 'api_assignments_')]
class AssignmentController extends AbstractApiController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, AssignmentRepository $repo): JsonResponse
    {
        $criteria = [];
        if ($eventId = $request->query->getInt('eventId')) {
            $criteria['event'] = $eventId;
        }
        if ($staffId = $request->query->getInt('staffId')) {
            $criteria['staff'] = $staffId;
        }

        return $this->json(array_map($this->normalize(...), $repo->findBy($criteria)));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        StaffRepository $staffRepo,
        EventRepository $eventRepo,
        SkillRepository $skillRepo,
        AssignmentRepository $assignmentRepo,
    ): JsonResponse {
        $body = $this->getBody($request);

        $staff = $staffRepo->find($body['staffId'] ?? 0);
        $event = $eventRepo->find($body['eventId'] ?? 0);
        $skill = $skillRepo->find($body['skillId'] ?? 0);

        if (!$staff || !$event || !$skill) {
            return $this->json(['error' => 'Staff, Event oder Skill nicht gefunden'], 422);
        }

        $existing = $assignmentRepo->findOneBy(['staff' => $staff, 'event' => $event, 'skill' => $skill]);
        if ($existing) {
            return $this->json($this->normalize($existing), 200);
        }

        $assignment = (new Assignment())
            ->setStaff($staff)
            ->setEvent($event)
            ->setSkill($skill);

        $em->persist($assignment);
        $em->flush();

        return $this->json($this->normalize($assignment), 201);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Assignment $assignment, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($assignment);
        $em->flush();

        return $this->json(null, 204);
    }

    private function normalize(Assignment $a): array
    {
        return [
            'id' => $a->getId(),
            'staff' => ['id' => $a->getStaff()->getId(), 'name' => $a->getStaff()->getName()],
            'event' => ['id' => $a->getEvent()->getId(), 'title' => $a->getEvent()->getTitle()],
            'skill' => ['id' => $a->getSkill()->getId(), 'name' => $a->getSkill()->getName()],
        ];
    }
}