<?php

namespace App\Entity;

use App\Repository\EmployeeRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
#[ORM\Table(name: 'employees')]
class Employee
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $employeeId;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 1, max: 10)]
    private int $skillLevel;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $hourlyRate;

    public function __construct(int $employeeId, int $skillLevel, int $hourlyRate)
    {
        $this->employeeId = $employeeId;
        $this->skillLevel = $skillLevel;
        $this->hourlyRate = $hourlyRate;
    }

    public function getEmployeeId(): int
    {
        return $this->employeeId;
    }

    public function getSkillLevel(): int
    {
        return $this->skillLevel;
    }

    public function getHourlyRate(): int
    {
        return $this->hourlyRate;
    }

    public function canExecuteTask(Task $task): bool
    {
        return $this->skillLevel >= $task->getSkillLevel();
    }

}
