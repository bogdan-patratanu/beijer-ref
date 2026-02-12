<?php

namespace App\Tests\Entity;

use App\Entity\Employee;
use App\Entity\Task;
use PHPUnit\Framework\TestCase;

class EmployeeTest extends TestCase
{
    public function testEmployeeCreation(): void
    {
        $employee = new Employee(1, 2, 150);

        $this->assertEquals(1, $employee->getEmployeeId());
        $this->assertEquals(2, $employee->getSkillLevel());
        $this->assertEquals(150, $employee->getHourlyRate());
    }

    public function testCanExecuteTaskWithSufficientSkill(): void
    {
        $employee = new Employee(1, 3, 100);
        $task = new Task(1, 2, 5);

        $this->assertTrue($employee->canExecuteTask($task));
    }

    public function testCannotExecuteTaskWithInsufficientSkill(): void
    {
        $employee = new Employee(1, 1, 100);
        $task = new Task(1, 3, 5);

        $this->assertFalse($employee->canExecuteTask($task));
    }

    public function testCanExecuteTaskWithEqualSkill(): void
    {
        $employee = new Employee(1, 2, 100);
        $task = new Task(1, 2, 5);

        $this->assertTrue($employee->canExecuteTask($task));
    }

}
