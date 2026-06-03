<?php

namespace App\Controller;

use App\Entity\SkillType;
use App\Repository\SkillTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/skill-types', name: 'api_skill_types_')]
class SkillTypeController extends AbstractApiController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(SkillTypeRepository $repo): JsonResponse
    {
        return $this->json(array_map($this->normalize(...), $repo->findBy([], ['name' => 'ASC'])));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(SkillType $skillType): JsonResponse
    {
        return $this->json($this->normalize($skillType));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
    {
        $body = $this->getBody($request);
        $skillType = (new SkillType())
            ->setName($body['name'] ?? '')
            ->setColor($body['color'] ?? '#000000');

        $violations = $validator->validate($skillType);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $em->persist($skillType);
        $em->flush();

        return $this->json($this->normalize($skillType), 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(SkillType $skillType, Request $request, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
    {
        $body = $this->getBody($request);
        if (isset($body['name'])) $skillType->setName($body['name']);
        if (isset($body['color'])) $skillType->setColor($body['color']);

        $violations = $validator->validate($skillType);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $em->flush();

        return $this->json($this->normalize($skillType));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(SkillType $skillType, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($skillType);
        $em->flush();

        return $this->json(null, 204);
    }

    private function normalize(SkillType $st): array
    {
        return ['id' => $st->getId(), 'name' => $st->getName(), 'color' => $st->getColor()];
    }
}