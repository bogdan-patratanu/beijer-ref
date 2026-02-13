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

    /**
     * testBasicOptimization
     * Purpose: Tests basic functionality of the cost optimizer with a simple scenario.
     * How it works: Creates 2 employees with different skill levels and rates, and 2 tasks requiring different skills. Calls optimize() and verifies the result is feasible, has 2 assignments, and total cost is 800 (calculated as 100 * 2 + 200 * 3 = 800).
     * Assertions: Feasibility, assignment count, total cost.
     */
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

    /**
     * testInfeasibleNoEligibleEmployee
     * Purpose: Tests handling of infeasible assignments when no employee has the required skill level.
     * How it works: One employee with skill 1, tasks requiring skill 1 and 3. The second task cannot be assigned since no employee has skill 3.
     * Assertions: Result is infeasible, reason contains 'no eligible employees'.
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
     * testSelectsCheapestEmployee
     * Purpose: Ensures the optimizer selects the cheapest eligible employee for a task.
     * How it works: Three employees with same skill level (2) but different rates (500, 100, 300). One task requiring skill 2. Optimizer should choose employee with rate 100.
     * Assertions: Feasibility, assignment to employee 2, total cost 100.
     */
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

    /**
     * testWidelyVaryingHourlyRates
     * Purpose: Tests optimization with large differences in hourly rates.
     * How it works: Three employees with rates 50, 1000, 10, all with skill 1. Three tasks requiring skill 1 with durations 5, 3, 2 hours. All tasks should be assigned to the cheapest employee (rate 10), total cost 10 * (5+3+2) = 100.
     * Assertions: Feasibility, total cost 100, all assignments to employee 3.
     */
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

    /**
     * testHighlySkewedTaskDurations
     * Purpose: Tests handling of tasks with highly varying durations.
     * How it works: One employee, three tasks with durations 1, 100, 1 hours. All assigned to the single employee.
     * Assertions: Feasibility, total cost 100 * 102 = 10200, makespan 102.0.
     */
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

    /**
     * testHighWorkloadSingleEmployee
     * Purpose: Tests assigning multiple tasks to a single employee.
     * How it works: One employee, two tasks of 5 hours each. Total 10 hours assigned.
     * Assertions: Feasibility, total cost 1000, makespan 10.0.
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
        $this->assertEquals(1000, $result->getTotalCost());
        $this->assertEquals(10.0, $result->getMakespan());
    }

    /**
     * testMultipleEmployeesSequentialExecution
     * Purpose: Tests assignment behavior when multiple employees are available.
     * How it works: Two employees with rates 100 and 200, three tasks of 2, 3, 1 hours. Since cost minimization, all tasks go to cheaper employee (100).
     * Assertions: Feasibility, employee summary shows one employee with 6 total hours.
     */
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

    /**
     * testLargeScalePerformance
     * Purpose: Tests performance with large datasets.
     * How it works: 30 employees with random skills and rates, 200 tasks with random skills and durations. Measures execution time.
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
     * Purpose: Tests behavior with no employees.
     * How it works: Empty employee list, one task.
     * Assertions: Infeasible.
     */
    public function testEmptyEmployeesList(): void
    {
        $result = $this->optimizer->optimize([], [new Task(1, 1, 1)]);
        $this->assertFalse($result->isFeasible());
    }

    /**
     * testEmptyTasksList
     * Purpose: Tests behavior with no tasks.
     * How it works: One employee, empty task list.
     * Assertions: Feasible, 0 assignments, 0 total cost.
     */
    public function testEmptyTasksList(): void
    {
        $result = $this->optimizer->optimize([new Employee(1, 1, 100)], []);
        $this->assertTrue($result->isFeasible());
        $this->assertEquals(0, count($result->getAssignments()));
        $this->assertEquals(0, $result->getTotalCost());
    }

    /**
     * testSkillLevelFiltering
     * Purpose: Tests that tasks are assigned only to employees with matching or higher skill levels.
     * How it works: Three employees with skills 1,2,3 and rates 100,200,300. One task requiring skill 3. Should assign to employee 3.
     * Assertions: Feasibility, assignment to employee 3.
     */
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
