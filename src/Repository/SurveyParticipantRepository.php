<?php

namespace App\Repository;

use App\Entity\Survey;
use App\Entity\SurveyParticipant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SurveyParticipant>
 */
class SurveyParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurveyParticipant::class);
    }

    /**
     * @return SurveyParticipant[] sorted by the staff member's last name, then first name
     */
    public function findBySurveyOrderedByName(Survey $survey): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.staff', 's')
            ->addSelect('s')
            ->where('p.survey = :survey')
            ->setParameter('survey', $survey)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
