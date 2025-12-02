<?php

namespace App\Repository;

use App\Entity\Story;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Story>
 */
class StoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Story::class);
    }

    public function findStoriesFromFollowing(array $followingUsers, User $currentUser): array
    {
        $limitDate = new \DateTime('-24 hours');

        return $this->createQueryBuilder('s')
            ->andWhere('s.user IN (:following) OR s.user = :currentUser')
            ->setParameter('following', $followingUsers)
            ->setParameter('currentUser', $currentUser)
            ->andWhere('s.createdAt >= :limitDate')
            ->setParameter('limitDate', $limitDate)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Story[] Returns an array of Story objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Story
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
