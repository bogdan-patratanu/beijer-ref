<?php

/**
 * MinimumMakespanOptimizer - Assigns tasks to employees minimizing overall completion time.
 *
 * Functions defined in this file:
 * - getName(): Returns the optimizer variant name.
 * - optimize(): Main entry point — validates feasibility, sorts tasks by duration
 *   DESC (LPT heuristic), then greedily assigns each to the least-loaded eligible employee.
 * - buildEligibleBySkill(): Groups employees by skill tier, pre-sorted by skill
 *   level descending for deterministic tie-breaking. Computed once.
 * - checkFeasibility(): Verifies every task has at least one eligible employee.
 * - selectLeastLoaded(): Picks the eligible employee with the lowest current
 *   workload using O(1) assigned-employee lookup for tie-breaking.
 * - buildResult(): Assembles the OptimizationResult from assignments and workloads.
 */

namespace App\Service;

use App\Entity\Employee;
use App\Entity\Task;
use App\Service\Assignment;
use App\Service\OptimizationResult;

class MinimumMakespanOptimizer implements OptimizerInterface
{
    public function getName(): string
    {
        return 'Minimum Completion Time (Makespan)';
    }

    /**
     * Assign every task to minimise the maximum employee workload (makespan).
     *
     * Strategy (Longest-Processing-Time-first heuristic):
     *  1. Pre-compute eligible employees per skill level.
     *  2. Check feasibility (skill coverage).
     *  3. Sort tasks by duration DESC, then skill DESC (deterministic).
     *  4. For each task, assign to the eligible employee with the lowest current
     *     workload. An employee may receive multiple tasks executed sequentially.
     *
     * @param Employee[] $employees
     * @param Task[]     $tasks
     */
    public function optimize(array $employees, array $tasks): OptimizationResult
    {
        $eligibleBySkill = $this->buildEligibleBySkill($employees, $tasks);

        $feasibilityError = $this->checkFeasibility($eligibleBySkill, $tasks);
        if ($feasibilityError !== null) {
            return new OptimizationResult(
                feasible: false,
                infeasibilityReason: $feasibilityError
            );
        }

        // LPT: sort tasks by duration descending, then skill descending for determinism.
        usort($tasks, function (Task $a, Task $b) {
            if ($b->getEstimation() !== $a->getEstimation()) {
                return $b->getEstimation() <=> $a->getEstimation();
            }
            return $b->getSkillLevel() <=> $a->getSkillLevel();
        });

        // Initialise workload tracker and assigned-employee set.
        $employeeWorkload = [];
        foreach ($employees as $employee) {
            $employeeWorkload[$employee->getEmployeeId()] = 0.0;
        }
        /** @var array<int, true> O(1) lookup for employees that already have work. */
        $assignedEmployees = [];

        $assignments = [];

        foreach ($tasks as $task) {
            $candidates = $eligibleBySkill[$task->getSkillLevel()];

            $selected = $this->selectLeastLoaded(
                $candidates,
                $employeeWorkload,
                $assignedEmployees
            );

            $startTime = $employeeWorkload[$selected->getEmployeeId()];
            $assignments[] = new Assignment($task, $selected, $startTime);
            $employeeWorkload[$selected->getEmployeeId()] += $task->getEstimation();
            $assignedEmployees[$selected->getEmployeeId()] = true;
        }

        return $this->buildResult($assignments, $employeeWorkload);
    }

    /**
     * Build a map of skillLevel => Employee[] sorted by skill level descending
     * (higher-skilled first for deterministic tie-breaking).
     *
     * @return array<int, Employee[]>
     */
    private function buildEligibleBySkill(array $employees, array $tasks): array
    {
        $requiredSkills = [];
        foreach ($tasks as $task) {
            $requiredSkills[$task->getSkillLevel()] = true;
        }

        $eligibleBySkill = [];
        foreach ($requiredSkills as $skill => $_) {
            $eligible = [];
            foreach ($employees as $employee) {
                if ($employee->getSkillLevel() >= $skill) {
                    $eligible[] = $employee;
                }
            }
            // Sort by skill descending for deterministic tie-breaking when loads are equal.
            usort($eligible, fn(Employee $a, Employee $b) => $b->getSkillLevel() <=> $a->getSkillLevel());
            $eligibleBySkill[$skill] = $eligible;
        }

        return $eligibleBySkill;
    }

    /**
     * Verify that every required skill tier has at least one eligible employee.
     *
     * @return string|null  null when feasible, error message otherwise.
     */
    private function checkFeasibility(array $eligibleBySkill, array $tasks): ?string
    {
        foreach ($tasks as $task) {
            $skill = $task->getSkillLevel();
            if (!isset($eligibleBySkill[$skill]) || empty($eligibleBySkill[$skill])) {
                return 'One or more tasks have no eligible employees';
            }
        }

        return null;
    }

    /**
     * Select the eligible employee with the lowest current workload.
     *
     * Tie-breaking (deterministic):
     *  1. Lowest workload first.
     *  2. Prefer employees that already have assignments (consolidate work).
     *  3. Higher skill level first (candidates are pre-sorted by skill DESC).
     */
    private function selectLeastLoaded(
        array $candidates,
        array $employeeWorkload,
        array $assignedEmployees
    ): Employee {
        $bestEmployee = $candidates[0];
        $bestLoad = $employeeWorkload[$bestEmployee->getEmployeeId()];
        $bestHasWork = isset($assignedEmployees[$bestEmployee->getEmployeeId()]);
        $bestSkill = $bestEmployee->getSkillLevel();

        for ($i = 1, $count = count($candidates); $i < $count; $i++) {
            $employee = $candidates[$i];
            $eid = $employee->getEmployeeId();
            $load = $employeeWorkload[$eid];
            $hasWork = isset($assignedEmployees[$eid]);
            $skill = $employee->getSkillLevel();

            // Compare: lowest load → already-assigned preferred → highest skill.
            if ($load < $bestLoad
                || ($load === $bestLoad && $hasWork && !$bestHasWork)
                || ($load === $bestLoad && $hasWork === $bestHasWork && $skill > $bestSkill)
            ) {
                $bestEmployee = $employee;
                $bestLoad = $load;
                $bestHasWork = $hasWork;
                $bestSkill = $skill;
            }
        }

        return $bestEmployee;
    }

    /**
     * Assemble the final OptimizationResult from raw assignments and workload map.
     */
    private function buildResult(array $assignments, array $employeeWorkload): OptimizationResult
    {
        $totalCost = 0;
        $assignmentData = [];

        foreach ($assignments as $a) {
            $totalCost += $a->getCost();
            $assignmentData[] = [
                'taskId' => $a->getTask()->getTaskId(),
                'employeeId' => $a->getEmployee()->getEmployeeId(),
                'startTime' => $a->getStartTime(),
                'endTime' => $a->getEndTime(),
                'cost' => $a->getCost(),
            ];
        }

        $makespan = empty($employeeWorkload) ? 0.0 : max($employeeWorkload);

        $employeeSummary = [];
        foreach ($employeeWorkload as $employeeId => $hours) {
            if ($hours > 0) {
                $employeeSummary[] = [
                    'employeeId' => $employeeId,
                    'totalAssignedHours' => $hours,
                ];
            }
        }

        return new OptimizationResult(
            feasible: true,
            assignments: $assignmentData,
            totalCost: $totalCost,
            makespan: $makespan,
            employeeSummary: $employeeSummary
        );
    }
}
