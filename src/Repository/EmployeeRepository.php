<?php

namespace App\Repository;

use App\Entity\Employee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EmployeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    public function save(Employee $employee, bool $flush = false): void
    {
        $this->getEntityManager()->persist($employee);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllIndexedById(): array
    {
        $employees = $this->findAll();
        $indexed = [];
        
        foreach ($employees as $employee) {
            $indexed[$employee->getEmployeeId()] = $employee;
        }
        
        return $indexed;
    }
}
