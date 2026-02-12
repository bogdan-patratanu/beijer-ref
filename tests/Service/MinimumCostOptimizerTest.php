<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\MinimumCostOptimizer;
use App\Entity\Employee;
use App\Entity\Task;

class MinimumCostOptimizerTest extends TestCase
{
    private MinimumCostOptimizer $optimizer;

    protected function setUp(): void
    {
        $this->optimizer = new MinimumCostOptimizer();
    }

    public function testBasicOptimization(): void
    {
        $employees = [
            new Employee(1, 1, 100),
            new Employee(2, 2, 200),
        ];

        $tasks = [
            new Task(1, 1, 2),
            new Task(2, 2, 3),
        ];

        $result = $this->optimizer->optimize($employees, $tasks);

        $this->assertTrue($result->isFeasible());
        $this->assertEquals(2, count($result->getAssignments()));
        $this->assertEquals(800, $result->getTotalCost());
    }

    public function testInfeasibleNoEligibleEmployee(): void
    {
        $employees = [
            new Employee(1, 1, 100),
        ];

        $tasks = [
            new Task(1, 1, 2),
            new Task(2, 3, 1),
        ];

        $result = $this->optimizer->optimize($employees, $tasks);

        $this->assertFalse($result->isFeasible());
        $this->assertStringContainsString('no eligible employees', $result->getInfeasibilityReason());
    }

    public function testSelectsCheapestEmployee(): void
    {
        $employees = [
            new Employee(1, 2, 500),
            new Employee(2, 2, 100),
            new Employee(3, 2, 300),
        ];

        $tasks = [
            new Task(1, 2, 1),
        ];

        $result = $this->optimizer->optimize($employees, $tasks);

        $this->assertTrue($result->isFeasible());
        $assignments = $result->getAssignments();
        $this->assertEquals(2, $assignments[0]['employeeId']);
        $this->assertEquals(100, $result->getTotalCost());
    }

    public function testWidelyVaryingHourlyRates(): void
    {
        $employees = [
            new Employee(1, 1, 50),
            new Employee(2, 1, 1000),
            new Employee(3, 1, 10),
        ];

        $tasks = [
            new Task(1, 1, 5),
            new Task(2, 1, 3),
            new Task(3, 1, 2),
        ];

        $result = $this->optimizer->optimize($employees, $tasks);

        $this->assertTrue($result->isFeasible());
        // All tasks assigned to cheapest employee (id=3, rate=10): 10*(5+3+2) = 100.
        $this->assertEquals(100, $result->getTotalCost());
        
        foreach ($result->getAssignments() as $assignment) {
            $this->assertEquals(3, $assignment['employeeId']);
        }
    }

    public function testHighlySkewedTaskDurations(): void
    {
        $employees = [
            new Employee(1, 1, 100),
        ];

        $tasks = [
            new Task(1, 1, 1),
            new Task(2, 1, 100),
            new Task(3, 1, 1),
        ];

        $result = $this->optimizer->optimize($employees, $tasks);

        $this->assertTrue($result->isFeasible());
        $this->assertEquals(10200, $result->getTotalCost());
        $this->assertEquals(102.0, $result->getMakespan());
    }

    public function testHighWorkloadSingleEmployee(): void
    {
        $employees = [
            new Employee(1, 1, 100),
        ];

        $tasks = [
            new Task(1, 1, 5),
            new Task(2, 1, 5),
        ];

        // 10h total on 1 employee — feasible (no hour cap).
        $result = $this->optimizer->optimize($employees, $tasks);
        $this->assertTrue($result->isFeasible());
        $this->assertEquals(1000, $result->getTotalCost());
        $this->assertEquals(10.0, $result->getMakespan());
    }

    public function testMultipleEmployeesSequentialExecution(): void
    {
        $employees = [
            new Employee(1, 1, 100),
            new Employee(2, 1, 200),
        ];

        $tasks = [
            new Task(1, 1, 2),
            new Task(2, 1, 3),
            new Task(3, 1, 1),
        ];

        $result = $this->optimizer->optimize($employees, $tasks);

        $this->assertTrue($result->isFeasible());
        
        $employeeSummary = $result->getEmployeeSummary();
        $this->assertCount(1, $employeeSummary);
        $this->assertEquals(1, $employeeSummary[0]['employeeId']);
        $this->assertEquals(6.0, $employeeSummary[0]['totalAssignedHours']);
    }

    public function testLargeScalePerformance(): void
    {
        $employees = [];
        for ($i = 1; $i <= 30; $i++) {
            $employees[] = new Employee($i, rand(1, 3), rand(50, 500));
        }

        $tasks = [];
        for ($i = 1; $i <= 200; $i++) {
            $tasks[] = new Task($i, rand(1, 3), rand(1, 10));
        }

        $startTime = microtime(true);
        $result = $this->optimizer->optimize($employees, $tasks);
        $executionTime = microtime(true) - $startTime;

        $this->assertTrue($result->isFeasible());
        $this->assertEquals(200, count($result->getAssignments()));
        $this->assertLessThan(1.0, $executionTime);
    }

    public function testEmptyEmployeesList(): void
    {
        $result = $this->optimizer->optimize([], [new Task(1, 1, 1)]);
        $this->assertFalse($result->isFeasible());
    }

    public function testEmptyTasksList(): void
    {
        $result = $this->optimizer->optimize([new Employee(1, 1, 100)], []);
        $this->assertTrue($result->isFeasible());
        $this->assertEquals(0, count($result->getAssignments()));
        $this->assertEquals(0, $result->getTotalCost());
    }

    public function testSkillLevelFiltering(): void
    {
        $employees = [
            new Employee(1, 1, 100),
            new Employee(2, 2, 200),
            new Employee(3, 3, 300),
        ];

        $tasks = [
            new Task(1, 3, 1),
        ];

        $result = $this->optimizer->optimize($employees, $tasks);

        $this->assertTrue($result->isFeasible());
        $assignments = $result->getAssignments();
        $this->assertEquals(3, $assignments[0]['employeeId']);
    }
}
