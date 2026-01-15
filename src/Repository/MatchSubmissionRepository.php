<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MatchSubmission;
use App\Entity\TournamentMatch;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MatchSubmission>
 *
 * @method MatchSubmission|null find($id, $lockMode = null, $lockVersion = null)
 * @method MatchSubmission|null findOneBy(array $criteria, array $orderBy = null)
 * @method MatchSubmission[]    findAll()
 * @method MatchSubmission[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MatchSubmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchSubmission::class);
    }

    /**
     * Find submission by user for a specific match.
     */
    public function findByMatchAndUser(TournamentMatch $match, User $user): ?MatchSubmission
    {
        return $this->findOneBy([
            'match' => $match,
            'submittedBy' => $user,
        ]);
    }

    /**
     * Find all submissions for a match.
     *
     * @return MatchSubmission[]
     */
    public function findByMatch(TournamentMatch $match): array
    {
        return $this->findBy(['match' => $match], ['submittedAt' => 'ASC']);
    }

    /**
     * Count submissions for a match.
     */
    public function countByMatch(TournamentMatch $match): int
    {
        return $this->count(['match' => $match]);
    }

    /**
     * Check if user has already submitted result for this match.
     */
    public function hasUserSubmitted(TournamentMatch $match, User $user): bool
    {
        return $this->findByMatchAndUser($match, $user) !== null;
    }
}
