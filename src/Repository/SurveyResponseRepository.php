<?php

namespace App\Repository;

use App\Entity\Survey;
use App\Entity\SurveyResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SurveyResponse>
 */
class SurveyResponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurveyResponse::class);
    }

    /**
     * @return array<int, int> event ID => number of responses
     */
    public function countByEventForSurvey(Survey $survey): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.event) AS id, COUNT(r.id) AS cnt')
            ->join('r.event', 'e')
            ->where('e.survey = :survey')
            ->setParameter('survey', $survey)
            ->groupBy('r.event')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['id']] = (int) $row['cnt'];
        }

        return $counts;
    }
}
