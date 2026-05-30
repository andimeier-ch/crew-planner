<?php

namespace App\Entity;

use App\Repository\SkillRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SkillRepository::class)]
class Skill
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: SkillType::class, inversedBy: 'skills')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?SkillType $skillType = null;

    #[ORM\ManyToMany(targetEntity: Staff::class, mappedBy: 'skills')]
    private Collection $staffMembers;

    public function __construct()
    {
        $this->staffMembers = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getSkillType(): ?SkillType { return $this->skillType; }
    public function setSkillType(?SkillType $skillType): static { $this->skillType = $skillType; return $this; }

    public function getStaffMembers(): Collection { return $this->staffMembers; }
}