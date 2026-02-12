<?php

namespace App\Service;

use App\Entity\Employee;
use App\Entity\Task;

class Assignment
{
    private Task $task;
    private Employee $employee;
    private float $startTime;
    private float $endTime;
    private int $cost;

    public function __construct(Task $task, Employee $employee, float $startTime)
    {
        $this->task = $task;
        $this->employee = $employee;
        $this->startTime = $startTime;
        $this->endTime = $startTime + $task->getEstimation();
        $this->cost = $task->getEstimation() * $employee->getHourlyRate();
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getEmployee(): Employee
    {
        return $this->employee;
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    public function getEndTime(): float
    {
        return $this->endTime;
    }

    public function getCost(): int
    {
        return $this->cost;
    }
}
