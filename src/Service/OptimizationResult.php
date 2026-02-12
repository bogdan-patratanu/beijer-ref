<?php

/**
 * OptimizationResult - Immutable value object holding the output of an optimization run.
 *
 * Shared by both the CLI command and the HTTP API controller.
 *
 * Required output per the specification:
 *  1. Assignment list: pairs taskId + employeeId (plus startTime, endTime, cost).
 *  2. Total price: sum of hourlyRate * estimation over all assigned tasks.
 *  3. Overall completion time (makespan): max employee load.
 *  4. Per-employee summary: employeeId, totalAssignedHours.
 *
 * Functions defined in this file:
 * - isFeasible(): Whether the optimisation produced a valid assignment.
 * - getAssignments(): The assignment list (spec item 1).
 * - getTotalCost(): The total price (spec item 2).
 * - getMakespan(): The overall completion time (spec item 3).
 * - getEmployeeSummary(): Per-employee hours summary (spec item 4).
 * - getInfeasibilityReason(): Human-readable reason when infeasible.
 * - toArray(): Canonical array representation used by both CLI and API.
 */

namespace App\Service;

class OptimizationResult
{
    private bool $feasible;
    private array $assignments;
    private int $totalCost;
    private float $makespan;
    private array $employeeSummary;
    private ?string $infeasibilityReason;

    public function __construct(
        bool $feasible,
        array $assignments = [],
        int $totalCost = 0,
        float $makespan = 0.0,
        array $employeeSummary = [],
        ?string $infeasibilityReason = null
    ) {
        $this->feasible = $feasible;
        $this->assignments = $assignments;
        $this->totalCost = $totalCost;
        $this->makespan = $makespan;
        $this->employeeSummary = $employeeSummary;
        $this->infeasibilityReason = $infeasibilityReason;
    }

    public function isFeasible(): bool
    {
        return $this->feasible;
    }

    public function getAssignments(): array
    {
        return $this->assignments;
    }

    public function getTotalCost(): int
    {
        return $this->totalCost;
    }

    public function getMakespan(): float
    {
        return $this->makespan;
    }

    public function getEmployeeSummary(): array
    {
        return $this->employeeSummary;
    }

    public function getInfeasibilityReason(): ?string
    {
        return $this->infeasibilityReason;
    }

    /**
     * Canonical array representation shared by CLI and API.
     *
     * Infeasible result:
     *   { feasible: false, reason: string }
     *
     * Feasible result (maps 1-to-1 with the spec's required output):
     *   { feasible: true, assignments: [...], totalCost: int, makespan: float, employeeSummary: [...] }
     */
    public function toArray(): array
    {
        if (!$this->feasible) {
            return [
                'feasible' => false,
                'reason' => $this->infeasibilityReason,
            ];
        }

        return [
            'feasible' => true,
            'assignments' => $this->assignments,
            'totalCost' => $this->totalCost,
            'makespan' => $this->makespan,
            'employeeSummary' => $this->employeeSummary,
        ];
    }
}
