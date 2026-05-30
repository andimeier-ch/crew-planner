<?php

namespace App\Entity;

use App\Repository\SurveyParticipantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SurveyParticipantRepository::class)]
#[ORM\UniqueConstraint(fields: ['survey', 'staff'])]
class SurveyParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Survey::class, inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Survey $survey = null;

    #[ORM\ManyToOne(targetEntity: Staff::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Staff $staff = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $token = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $remark = null;

    #[ORM\OneToMany(targetEntity: SurveyResponse::class, mappedBy: 'participant', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $responses;

    public function __construct()
    {
        $this->responses = new ArrayCollection();
        $this->token = bin2hex(random_bytes(32));
    }

    public function getId(): ?int { return $this->id; }

    public function getSurvey(): ?Survey { return $this->survey; }
    public function setSurvey(?Survey $survey): static { $this->survey = $survey; return $this; }

    public function getStaff(): ?Staff { return $this->staff; }
    public function setStaff(?Staff $staff): static { $this->staff = $staff; return $this; }

    public function getToken(): string { return $this->token; }

    public function getRemark(): ?string { return $this->remark; }
    public function setRemark(?string $remark): static { $this->remark = $remark; return $this; }

    public function getResponses(): Collection { return $this->responses; }
}