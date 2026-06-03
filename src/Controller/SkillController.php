<?php

namespace App\Controller;

use App\Entity\Skill;
use App\Repository\SkillRepository;
use App\Repository\SkillTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/skills', name: 'api_skills_')]
class SkillController extends AbstractApiController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(SkillRepository $repo): JsonResponse
    {
        return $this->json(array_map($this->normalize(...), $repo->findBy([], ['name' => 'ASC'])));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Skill $skill): JsonResponse
    {
        return $this->json($this->normalize($skill));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, ValidatorInterface $validator, SkillTypeRepository $skillTypeRepo): JsonResponse
    {
        $body = $this->getBody($request);
        $skillType = $skillTypeRepo->find($body['skillTypeId'] ?? 0);
        if (!$skillType) {
            return $this->json(['error' => 'Skill-Typ nicht gefunden'], 422);
        }

        $skill = (new Skill())
            ->setName($body['name'] ?? '')
            ->setSkillType($skillType);

        $violations = $validator->validate($skill);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $em->persist($skill);
        $em->flush();

        return $this->json($this->normalize($skill), 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Skill $skill, Request $request, EntityManagerInterface $em, ValidatorInterface $validator, SkillTypeRepository $skillTypeRepo): JsonResponse
    {
        $body = $this->getBody($request);
        if (isset($body['name'])) $skill->setName($body['name']);
        if (isset($body['skillTypeId'])) {
            $skillType = $skillTypeRepo->find($body['skillTypeId']);
            if (!$skillType) {
                return $this->json(['error' => 'Skill-Typ nicht gefunden'], 422);
            }
            $skill->setSkillType($skillType);
        }

        $violations = $validator->validate($skill);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $em->flush();

        return $this->json($this->normalize($skill));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Skill $skill, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($skill);
        $em->flush();

        return $this->json(null, 204);
    }

    public function normalize(Skill $skill): array
    {
        $st = $skill->getSkillType();
        return [
            'id' => $skill->getId(),
            'name' => $skill->getName(),
            'skillType' => $st ? ['id' => $st->getId(), 'name' => $st->getName(), 'color' => $st->getColor()] : null,
        ];
    }
}