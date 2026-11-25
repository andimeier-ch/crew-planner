<?php

namespace App\Repository;

use App\Entity\Assignment;
use App\Entity\Event;
use App\Entity\Staff;
use App\Entity\Survey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Assignment>
 */
class AssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Assignment::class);
    }

    /**
     * @param Event[] $events
     */
    public function deleteForStaffAndEvents(Staff $staff, array $events): int
    {
        if ($events === []) {
            return 0;
        }

        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.staff = :staff')
            ->andWhere('a.event IN (:events)')
            ->setParameter('staff', $staff)
            ->setParameter('events', $events)
            ->getQuery()
            ->execute();
    }

    /**
     * All assignments within the survey's events, with their staff loaded, ordered by staff name.
     *
     * @return Assignment[]
     */
    public function findForSurveyOrderedByName(Survey $survey): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('s')
            ->join('a.staff', 's')
            ->join('a.event', 'e')
            ->where('e.survey = :survey')
            ->setParameter('survey', $survey)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, int> event ID => number of assignments
     */
    public function countByEventForSurvey(Survey $survey): array
    {
        return $this->countGroupedBy('a.event', $survey);
    }

    /**
     * @return array<int, int> staff ID => number of assignments within the survey's events
     */
    public function countByStaffForSurvey(Survey $survey): array
    {
        return $this->countGroupedBy('a.staff', $survey);
    }

    /**
     * @return array<int, int>
     */
    private function countGroupedBy(string $field, Survey $survey): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select("IDENTITY($field) AS id, COUNT(a.id) AS cnt")
            ->join('a.event', 'e')
            ->where('e.survey = :survey')
            ->setParameter('survey', $survey)
            ->groupBy($field)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['id']] = (int) $row['cnt'];
        }

        return $counts;
    }
}
