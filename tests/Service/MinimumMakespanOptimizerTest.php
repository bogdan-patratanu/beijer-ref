<?php

namespace App\Tests\Service;

use App\Service\MinimumMakespanOptimizer;
use App\Entity\Employee;
use App\Entity\Task;
use PHPUnit\Framework\TestCase;

class MinimumMakespanOptimizerTest extends TestCase
{
    private MinimumMakespanOptimizer $optimizer;

    protected function setUp(): void
    {
        $this->optimizer = new MinimumMakespanOptimizer();
    }

    public function testBasicOptimization(): void
    {
        $employees = [
            new Employee(1, 1, 100),
            new Employee(2, 1, 200),
        ];

        $tasks = [
            new Task(1, 1, 4),
            new Task(2, 1, 4),
        ];

        $result = $this->optimizer->optimize($employees, $tasks);

        $this->assertTrue($result->isFeasible());
        $this->assertEquals(2, count($result->getAssignments()));
        $this->assertEquals(4.0, $result->getMakespan());
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

    public function testBalancedWorkloadDistribution(): void
    {
        $employees = [
            new Employee(1, 1, 100),
            new Employee(2, 1, 200),
            new Employee(3, 1, 300),
        ];

        $tasks = [
            new Task(1, 1, 6),
            new Task(2, 1, 3),
            new Task(3, 1, 3),
        ];

        $result = $this->optimizer->optimize($employees, $tasks);

        $this->assertTrue($result->isFeasible());
        $this->assertEquals(6.0, $result->getMakespan());
        
        $summary = $result->getEmployeeSummary();
        $this->assertCount(3, $summary);
    }

    public function testLongestTasksFirst(): void
    {
        $employees = [
            new Employee(1, 1, 100),
            new Employee(2, 1, 100),
        ];

        $tasks = [
            new Task(1, 1, 1),
            new Task(2, 1, 10),
            new Task(3, 1, 2),
        ];

        $result = $this->optimizer->optimize($employees, $tasks);

        $this->assertTrue($result->isFeasible());
        $this->assertLessThanOrEqual(10.0, $result->getMakespan());
    }

    public function testHighlySkewedTaskDurations(): void
    {
        $employees = [
            new Employee(1, 1, 100),
            new Employee(2, 1, 100),
        ];

        $tasks = [
            new Task(1, 1, 1),
            new Task(2, 1, 100),
            new Task(3, 1, 1),
        ];

        $result = $this->optimizer->optimize($employees, $tasks);

        $this->assertTrue($result->isFeasible());
        $this->assertEquals(100.0, $result->getMakespan());
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
        $this->assertEquals(10.0, $result->getMakespan());
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
        $this->assertEquals(0.0, $result->getMakespan());
    }

    public function testSkillLevelPrioritization(): void
    {
        $employees = [
            new Employee(1, 3, 100),
            new Employee(2, 2, 100),
            new Employee(3, 1, 100),
        ];

        $tasks = [
            new Task(1, 1, 5),
            new Task(2, 1, 5),
        ];

        $result = $this->optimizer->optimize($employees, $tasks);

        $this->assertTrue($result->isFeasible());
        $this->assertEquals(5.0, $result->getMakespan());
    }

    public function testMixedSkillLevels(): void
    {
        $employees = [
            new Employee(1, 1, 100),
            new Employee(2, 2, 200),
            new Employee(3, 3, 300),
        ];

        $tasks = [
            new Task(1, 1, 2),
            new Task(2, 2, 2),
            new Task(3, 3, 2),
        ];

        $result = $this->optimizer->optimize($employees, $tasks);

        $this->assertTrue($result->isFeasible());
        $this->assertEquals(3, count($result->getAssignments()));
        $this->assertEquals(2.0, $result->getMakespan());
    }

    public function testDeterministicOutput(): void
    {
        $employees = [
            new Employee(1, 1, 100),
            new Employee(2, 1, 200),
        ];

        $tasks = [
            new Task(1, 1, 2),
            new Task(2, 1, 3),
        ];

        $result1 = $this->optimizer->optimize($employees, $tasks);
        $result2 = $this->optimizer->optimize($employees, $tasks);

        $this->assertEquals($result1->getTotalCost(), $result2->getTotalCost());
        $this->assertEquals($result1->getMakespan(), $result2->getMakespan());
        $this->assertEquals(
            count($result1->getAssignments()),
            count($result2->getAssignments())
        );
    }
}
