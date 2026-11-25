<?php

namespace App\Entity;

use App\Enum\SurveyStatus;
use App\Repository\SurveyRepository;
use App\Service\SurveyMailer;
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

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $title = '';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $deadline = null;

    #[ORM\Column(length: 20, enumType: SurveyStatus::class)]
    private SurveyStatus $status = SurveyStatus::Draft;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $invitationSubject = SurveyMailer::INVITATION_SUBJECT;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $invitationBody = SurveyMailer::INVITATION_BODY;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $reminderSubject = SurveyMailer::REMINDER_SUBJECT;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $reminderBody = SurveyMailer::REMINDER_BODY;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $assignmentSubject = SurveyMailer::ASSIGNMENT_SUBJECT;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $assignmentBody = SurveyMailer::ASSIGNMENT_BODY;

    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'survey', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['date' => 'ASC'])]
    private Collection $events;

    #[ORM\OneToMany(targetEntity: SurveyParticipant::class, mappedBy: 'survey', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $participants;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getDeadline(): ?\DateTimeInterface { return $this->deadline; }
    public function setDeadline(\DateTimeInterface $deadline): static { $this->deadline = $deadline; return $this; }

    public function getStatus(): SurveyStatus { return $this->status; }
    public function setStatus(SurveyStatus $status): static { $this->status = $status; return $this; }
    public function isDraft(): bool { return $this->status === SurveyStatus::Draft; }
    public function isOpen(): bool { return $this->status === SurveyStatus::Open; }
    public function isClosed(): bool { return $this->status === SurveyStatus::Closed; }

    public function getInvitationSubject(): string { return $this->invitationSubject; }
    public function setInvitationSubject(string $subject): static { $this->invitationSubject = $subject; return $this; }

    public function getInvitationBody(): string { return $this->invitationBody; }
    public function setInvitationBody(string $body): static { $this->invitationBody = $body; return $this; }

    public function getReminderSubject(): string { return $this->reminderSubject; }
    public function setReminderSubject(string $subject): static { $this->reminderSubject = $subject; return $this; }

    public function getReminderBody(): string { return $this->reminderBody; }
    public function setReminderBody(string $body): static { $this->reminderBody = $body; return $this; }

    public function getAssignmentSubject(): string { return $this->assignmentSubject; }
    public function setAssignmentSubject(string $subject): static { $this->assignmentSubject = $subject; return $this; }

    public function getAssignmentBody(): string { return $this->assignmentBody; }
    public function setAssignmentBody(string $body): static { $this->assignmentBody = $body; return $this; }

    public function getEvents(): Collection { return $this->events; }
    public function addEvent(Event $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setSurvey($this);
        }
        return $this;
    }
    public function removeEvent(Event $event): static
    {
        $this->events->removeElement($event);
        return $this;
    }

    public function getParticipants(): Collection { return $this->participants; }

    public function isDeadlinePassed(): bool
    {
        return $this->deadline < new \DateTime();
    }
}