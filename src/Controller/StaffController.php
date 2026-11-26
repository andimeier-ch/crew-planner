<?php

namespace App\Controller;

use App\Entity\Staff;
use App\Repository\EventRepository;
use App\Repository\SkillRepository;
use App\Repository\StaffRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/staffs', name: 'api_staffs_')]
class StaffController extends AbstractApiController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(StaffRepository $repo): JsonResponse
    {
        return $this->json(array_map($this->normalize(...), $repo->findBy([], ['lastName' => 'ASC', 'firstName' => 'ASC'])));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Staff $staff): JsonResponse
    {
        return $this->json($this->normalize($staff));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, ValidatorInterface $validator, SkillRepository $skillRepo): JsonResponse
    {
        $body = $this->getBody($request);
        $staff = (new Staff())
            ->setFirstName(trim($body['firstName'] ?? ''))
            ->setLastName(trim($body['lastName'] ?? ''))
            ->setEmail($body['email'] ?? null)
            ->setIsLeader($body['isLeader'] ?? false);

        foreach ($body['skillIds'] ?? [] as $skillId) {
            $skill = $skillRepo->find($skillId);
            if ($skill) $staff->addSkill($skill);
        }

        $violations = $validator->validate($staff);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $em->persist($staff);
        $em->flush();

        return $this->json($this->normalize($staff), 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        Staff $staff,
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        SkillRepository $skillRepo,
        EventRepository $eventRepo,
    ): JsonResponse
    {
        $body = $this->getBody($request);
        if (isset($body['firstName'])) $staff->setFirstName(trim($body['firstName']));
        if (isset($body['lastName'])) $staff->setLastName(trim($body['lastName']));
        if (array_key_exists('email', $body)) $staff->setEmail($body['email'] ?: null);
        if (isset($body['isLeader'])) {
            $staff->setIsLeader($body['isLeader']);
            // Without the leader flag, the staff member can no longer lead any event.
            if (!$staff->isLeader()) {
                foreach ($eventRepo->findBy(['leader' => $staff]) as $event) {
                    $event->setLeader(null);
                }
            }
        }

        if (array_key_exists('skillIds', $body)) {
            foreach ($staff->getSkills()->toArray() as $skill) {
                $staff->removeSkill($skill);
            }
            foreach ($body['skillIds'] as $skillId) {
                $skill = $skillRepo->find($skillId);
                if ($skill) $staff->addSkill($skill);
            }
        }

        $violations = $validator->validate($staff);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $em->flush();

        return $this->json($this->normalize($staff));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Staff $staff, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($staff);
        $em->flush();

        return $this->json(null, 204);
    }

    public function normalize(Staff $staff): array
    {
        $skillController = new SkillController();
        return [
            'id' => $staff->getId(),
            'firstName' => $staff->getFirstName(),
            'lastName' => $staff->getLastName(),
            'fullName' => $staff->getFullName(),
            'email' => $staff->getEmail(),
            'isLeader' => $staff->isLeader(),
            'skills' => array_map($skillController->normalize(...), $staff->getSkills()->toArray()),
        ];
    }
}