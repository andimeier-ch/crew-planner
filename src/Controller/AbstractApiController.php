<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;

abstract class AbstractApiController extends AbstractController
{
    protected function getBody(Request $request): array
    {
        return json_decode($request->getContent(), true) ?? [];
    }

    protected function validationErrorResponse(ConstraintViolationListInterface $violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $v) {
            $errors[$v->getPropertyPath()] = $v->getMessage();
        }
        return $this->json(['errors' => $errors], 422);
    }

    protected function notFound(string $message = 'Nicht gefunden'): JsonResponse
    {
        return $this->json(['error' => $message], 404);
    }
}