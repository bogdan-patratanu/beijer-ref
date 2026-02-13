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

    /**
     * testBasicOptimization
     * Purpose: Tests basic functionality of the makespan optimizer.
     * How it works: Two employees with same skill, rates 100/200. Two tasks of 4 hours each. Tasks distributed to minimize makespan (each employee gets one task, makespan 4).
     * Assertions: Feasibility, 2 assignments, makespan 4.0.
     */
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

    /**
     * testInfeasibleNoEligibleEmployee
     * Purpose: Tests infeasible assignments due to skill mismatch.
     * How it works: One employee skill 1, tasks requiring skill 1 and 3.
     * Assertions: Infeasible, reason 'no eligible employees'.
     */
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

    /**
     * testBalancedWorkloadDistribution
     * Purpose: Tests distributing tasks to balance workload and minimize makespan.
     * How it works: Three employees, tasks of 6, 3, 3 hours. Should distribute to minimize max load.
     * Assertions: Feasibility, makespan 6.0, all three employees used.
     */
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

    /**
     * testLongestTasksFirst
     * Purpose: Tests that longer tasks are assigned first in the optimization.
     * How it works: Two employees, tasks of 1, 10, 2 hours. Longest task (10) assigned first.
     * Assertions: Feasibility, makespan <= 10.0.
     */
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

    /**
     * testHighlySkewedTaskDurations
     * Purpose: Tests handling of highly varying task durations.
     * How it works: Two employees, tasks of 1, 100, 1 hours. The 100-hour task will dominate makespan.
     * Assertions: Feasibility, makespan 100.0.
     */
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

    /**
     * testHighWorkloadSingleEmployee
     * Purpose: Tests high workload on single employee.
     * How it works: One employee, two 5-hour tasks.
     * Assertions: Feasibility, makespan 10.0.
     */
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

    /**
     * testLargeScalePerformance
     * Purpose: Tests performance with large scale data.
     * How it works: 30 employees, 200 tasks, random parameters. Measures execution time.
     * Assertions: Feasibility, 200 assignments, execution time < 1 second.
     */
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

    /**
     * testEmptyEmployeesList
     * Purpose: Tests with no employees.
     * How it works: Empty employees, one task.
     * Assertions: Infeasible.
     */
    public function testEmptyEmployeesList(): void
    {
        $result = $this->optimizer->optimize([], [new Task(1, 1, 1)]);
        $this->assertFalse($result->isFeasible());
    }

    /**
     * testEmptyTasksList
     * Purpose: Tests with no tasks.
     * How it works: One employee, no tasks.
     * Assertions: Feasible, 0 assignments, makespan 0.0.
     */
    public function testEmptyTasksList(): void
    {
        $result = $this->optimizer->optimize([new Employee(1, 1, 100)], []);
        $this->assertTrue($result->isFeasible());
        $this->assertEquals(0, count($result->getAssignments()));
        $this->assertEquals(0.0, $result->getMakespan());
    }

    /**
     * testSkillLevelPrioritization
     * Purpose: Tests prioritizing higher skill level employees.
     * How it works: Employees with skills 3,2,1 all same rate. Two 5-hour tasks. Higher skill employees prioritized.
     * Assertions: Feasibility, makespan 5.0.
     */
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

    /**
     * testMixedSkillLevels
     * Purpose: Tests assigning tasks based on required skill levels.
     * How it works: Employees skills 1,2,3. Tasks requiring skills 1,2,3 respectively.
     * Assertions: Feasibility, 3 assignments, makespan 2.0.
     */
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

    /**
     * testDeterministicOutput
     * Purpose: Tests that optimization results are deterministic.
     * How it works: Runs optimization twice with same inputs, compares results.
     * Assertions: Total cost, makespan, and assignment count are identical between runs.
     */
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
