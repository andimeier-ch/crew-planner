<?php

namespace App\Entity;

use App\Repository\SurveyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SurveyRepository::class)]
class Survey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $deadline = null;

    #[ORM\ManyToMany(targetEntity: Event::class)]
    #[ORM\JoinTable(name: 'survey_event')]
    private Collection $events;

    #[ORM\OneToMany(targetEntity: SurveyParticipant::class, mappedBy: 'survey', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $participants;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getDeadline(): ?\DateTimeInterface { return $this->deadline; }
    public function setDeadline(\DateTimeInterface $deadline): static { $this->deadline = $deadline; return $this; }

    public function getEvents(): Collection { return $this->events; }
    public function addEvent(Event $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
        }
        return $this;
    }
    public function removeEvent(Event $event): static
    {
        $this->events->removeElement($event);
        return $this;
    }

    public function getParticipants(): Collection { return $this->participants; }

    public function isExpired(): bool
    {
        return $this->deadline < new \DateTime();
    }
}