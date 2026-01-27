<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    // ---------------------------
    // QueryBuilder providers (for pagination)
    // ---------------------------

    /**
     * Active (non-deleted) tasks ordered by dueAt ASC, id DESC.
     */
    public function createActiveOrderedQB(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.deletedAt IS NULL')
            ->orderBy('t.isDone', 'ASC')
            ->addOrderBy('t.dueAt', 'ASC')
            ->addOrderBy('t.id', 'DESC');
    }

    /**
     * Trashed (soft-deleted) tasks ordered by deletedAt DESC.
     */
    public function createTrashedQB(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.deletedAt IS NOT NULL')
            ->orderBy('t.deletedAt', 'DESC');
    }

    /**
     * Active tasks for a specific owner.
     */
    public function createActiveForUserQB(User $owner): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('t.dueAt', 'ASC')
            ->addOrderBy('t.id', 'DESC');
    }

    /**
     * Trashed tasks for a specific owner.
     */
    public function createTrashedForUserQB(User $owner): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.deletedAt IS NOT NULL')
            ->andWhere('t.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('t.deletedAt', 'DESC');
    }

    /**
     * Admin: all active tasks.
     */
    public function createActiveAllQB(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.deletedAt IS NULL')
            ->orderBy('t.dueAt', 'ASC')
            ->addOrderBy('t.id', 'DESC');
    }

    /**
     * Admin: all trashed tasks.
     */
    public function createTrashedAllQB(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.deletedAt IS NOT NULL')
            ->orderBy('t.deletedAt', 'DESC');
    }

    // ---------------------------
    // Existing array-returning finders (keep for non-paginated use)
    // ---------------------------

    public function findActiveOrdered(): array
    {
        return $this->createActiveOrderedQB()
            ->getQuery()
            ->getResult();
    }

    public function findTrashed(): array
    {
        return $this->createTrashedQB()
            ->getQuery()
            ->getResult();
    }

    public function findActiveForUser(User $owner): array
    {
        return $this->createActiveForUserQB($owner)
            ->getQuery()
            ->getResult();
    }

    public function findTrashedForUser(User $owner): array
    {
        return $this->createTrashedForUserQB($owner)
            ->getQuery()
            ->getResult();
    }

    /** Admin views */
    public function findActiveAll(): array
    {
        return $this->createActiveAllQB()
            ->getQuery()
            ->getResult();
    }

    public function findTrashedAll(): array
    {
        return $this->createTrashedAllQB()
            ->getQuery()
            ->getResult();
    }
}
