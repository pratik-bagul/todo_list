<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('t')
        
        ->andWhere('t.deletedAt IS NULL')
        ->orderBy('t.dueAt', 'ASC')
        ->addOrderBy('t.id', 'DESC')
        ->getQuery()->getResult();

    }
    
    public function findTrashed(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.deletedAt IS NOT NULL')
            ->orderBy('t.deletedAt', 'DESC')
            ->getQuery()->getResult();
    }

    

public function findActiveForUser(User $owner): array
{
    return $this->createQueryBuilder('t')
        ->andWhere('t.deletedAt IS NULL')
        ->andWhere('t.owner = :owner')
        ->setParameter('owner', $owner)
        ->orderBy('t.dueAt', 'ASC')
        ->addOrderBy('t.id', 'DESC')
        ->getQuery()->getResult();
}

        public function findTrashedForUser(User $owner): array
        {
            return $this->createQueryBuilder('t')
                ->andWhere('t.deletedAt IS NOT NULL')
                ->andWhere('t.owner = :owner')
                ->setParameter('owner', $owner)
                ->orderBy('t.deletedAt', 'DESC')
                ->getQuery()->getResult();
        }

        /** Admin views */
        public function findActiveAll(): array
        {
            return $this->createQueryBuilder('t')
                ->andWhere('t.deletedAt IS NULL')
                ->orderBy('t.dueAt', 'ASC')
                ->addOrderBy('t.id', 'DESC')
                ->getQuery()->getResult();
        }

        public function findTrashedAll(): array
        {
            return $this->createQueryBuilder('t')
                ->andWhere('t.deletedAt IS NOT NULL')
                ->orderBy('t.deletedAt', 'DESC')
                ->getQuery()->getResult();
        }

        



    //    /**
    //     * @return Task[] Returns an array of Task objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Task
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
