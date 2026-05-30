<?php

namespace App\Entity;

use App\Repository\SurveyResponseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SurveyResponseRepository::class)]
#[ORM\UniqueConstraint(fields: ['participant', 'event'])]
class SurveyResponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SurveyParticipant::class, inversedBy: 'responses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SurveyParticipant $participant = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

    #[ORM\Column]
    private bool $available = false;

    public function getId(): ?int { return $this->id; }

    public function getParticipant(): ?SurveyParticipant { return $this->participant; }
    public function setParticipant(?SurveyParticipant $participant): static { $this->participant = $participant; return $this; }

    public function getEvent(): ?Event { return $this->event; }
    public function setEvent(?Event $event): static { $this->event = $event; return $this; }

    public function isAvailable(): bool { return $this->available; }
    public function setAvailable(bool $available): static { $this->available = $available; return $this; }
}