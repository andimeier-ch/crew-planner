<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EventNameRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A frequently used event title, suggested when the admin types an event title (CR 6).
 */
#[ORM\Entity(repositoryClass: EventNameRepository::class)]
#[UniqueEntity(fields: ['name'], message: 'Dieser Name ist bereits vorhanden.')]
class EventName
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $name = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
