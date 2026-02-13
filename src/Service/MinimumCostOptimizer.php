<?php

/**
 * MinimumCostOptimizer - Assigns tasks to employees minimizing total cost.
 *
 * Functions defined in this file:
 * - getName(): Returns the optimizer variant name.
 * - optimize(): Main entry point — validates feasibility, then greedily assigns
 *   each task to an idle eligible employee (cheapest among idle), falling back
 *   to the cheapest eligible employee when all candidates are already busy.
 * - buildEligibleBySkill(): Groups employees by the minimum skill level they can
 *   serve, pre-sorted by hourly rate ascending (cheapest first). Computed once.
 * - selectEmployee(): Picks the cheapest idle employee from candidates; if all
 *   candidates are busy, falls back to the cheapest one overall.
 * - checkFeasibility(): Verifies every task has at least one eligible employee.
 * - buildResult(): Assembles the OptimizationResult from assignments and workloads.
 */

namespace App\Service;

use App\Entity\Employee;
use App\Entity\Task;
use App\Service\Assignment;
use App\Service\OptimizationResult;

class MinimumCostOptimizer implements OptimizerInterface
{
    public function getName(): string
    {
        return 'Minimum Total Cost';
    }

    /**
     * Assign every task to an eligible employee, preferring idle workers.
     *
     * Strategy:
     *  1. Pre-compute eligible employees per skill level, sorted by hourly rate.
     *  2. Check feasibility (skill coverage).
     *  3. For each task, among eligible employees:
     *     a. Prefer an idle employee (zero workload), cheapest among idle.
     *     b. If all eligible employees are busy, fall back to the cheapest one.
     *
     * @param Employee[] $employees
     * @param Task[]     $tasks
     */
    public function optimize(array $employees, array $tasks): OptimizationResult
    {
        // Pre-compute eligible employees per skill level, sorted cheapest-first (once).
        $eligibleBySkill = $this->buildEligibleBySkill($employees, $tasks);

        $feasibilityError = $this->checkFeasibility($eligibleBySkill, $tasks);
        if ($feasibilityError !== null) {
            return new OptimizationResult(
                feasible: false,
                infeasibilityReason: $feasibilityError
            );
        }

        // Initialise workload tracker keyed by employeeId.
        $employeeWorkload = [];
        foreach ($employees as $employee) {
            $employeeWorkload[$employee->getEmployeeId()] = 0.0;
        }

        $assignments = [];

        foreach ($tasks as $task) {
            $skillNeeded = $task->getSkillLevel();
            $candidates = $eligibleBySkill[$skillNeeded]; // already sorted by rate ASC

            // Prefer an idle employee (cheapest among idle); fall back to cheapest overall.
            $selected = $this->selectEmployee($candidates, $employeeWorkload);

            $startTime = $employeeWorkload[$selected->getEmployeeId()];
            $assignments[] = new Assignment($task, $selected, $startTime);
            $employeeWorkload[$selected->getEmployeeId()] += $task->getEstimation();
        }

        return $this->buildResult($assignments, $employeeWorkload);
    }

    /**
     * Select the best employee from candidates: cheapest idle first, cheapest overall as fallback.
     *
     * Candidates are already sorted by hourly rate ascending.
     * An employee is considered idle when their current workload is zero.
     *
     * @param Employee[]         $candidates       Eligible employees sorted by rate ASC.
     * @param array<int, float>  $employeeWorkload Current workload keyed by employeeId.
     */
    private function selectEmployee(array $candidates, array $employeeWorkload): Employee
    {
        // First pass: find the cheapest idle employee (zero workload).
        foreach ($candidates as $candidate) {
            if ($employeeWorkload[$candidate->getEmployeeId()] === 0.0) {
                return $candidate;
            }
        }

        // All candidates are busy — fall back to cheapest overall (first in sorted list).
        return $candidates[0];
    }

    /**
     * Build a map of skillLevel => Employee[] sorted by hourly rate ascending.
     * An employee with skillLevel N is eligible for tasks requiring skill <= N,
     * so they appear in every tier from 1..N.
     *
     * @return array<int, Employee[]>
     */
    private function buildEligibleBySkill(array $employees, array $tasks): array
    {
        // Collect distinct skill levels required by the task set.
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
            // Sort once by hourly rate ascending — cheapest first.
            usort($eligible, fn(Employee $a, Employee $b) => $a->getHourlyRate() <=> $b->getHourlyRate());
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
