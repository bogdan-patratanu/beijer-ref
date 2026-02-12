<?php

/**
 * ApiController - HTTP endpoints for task assignment optimization.
 *
 * Functions defined in this file:
 * - optimizeCost(): POST/GET /api/optimize/cost — run the minimum-cost optimizer.
 * - optimizeMakespan(): POST/GET /api/optimize/makespan — run the minimum-makespan optimizer.
 * - getEmployees(): GET /api/employees — list all employees.
 * - getTasks(): GET /api/tasks — list all tasks.
 * - health(): GET /api/health — service health check.
 * - runOptimizer(): Shared helper that executes an optimizer and returns a JSON
 *   response built from OptimizationResult::toArray() (single source of truth).
 */

namespace App\Controller;

use App\Repository\EmployeeRepository;
use App\Repository\TaskRepository;
use App\Service\OptimizerInterface;
use App\Service\MinimumCostOptimizer;
use App\Service\MinimumMakespanOptimizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly TaskRepository $taskRepository,
        private readonly MinimumCostOptimizer $costOptimizer,
        private readonly MinimumMakespanOptimizer $makespanOptimizer,
    ) {
    }

    #[Route('/optimize/cost', name: 'api_optimize_cost', methods: ['GET', 'POST'])]
    public function optimizeCost(Request $request): JsonResponse
    {
        return $this->runOptimizer($this->costOptimizer);
    }

    #[Route('/optimize/makespan', name: 'api_optimize_makespan', methods: ['GET', 'POST'])]
    public function optimizeMakespan(Request $request): JsonResponse
    {
        return $this->runOptimizer($this->makespanOptimizer);
    }

    /**
     * Shared helper: load data, run the given optimizer, and return a JSON
     * response built from OptimizationResult::toArray().
     */
    private function runOptimizer(OptimizerInterface $optimizer): JsonResponse
    {
        $employees = $this->employeeRepository->findAll();
        $tasks = $this->taskRepository->findAll();

        if (empty($employees)) {
            return $this->json([
                'error' => 'No employees found in database',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty($tasks)) {
            return $this->json([
                'error' => 'No tasks found in database',
            ], Response::HTTP_BAD_REQUEST);
        }

        $startTime = microtime(true);
        $result = $optimizer->optimize($employees, $tasks);
        $executionTime = microtime(true) - $startTime;

        // Use OptimizationResult::toArray() as the canonical output format.
        $payload = $result->toArray();
        $payload['strategy'] = $optimizer->getName();
        $payload['executionTime'] = round($executionTime * 1000, 2) . ' ms';
        $payload['statistics'] = [
            'totalEmployees' => count($employees),
            'totalTasks' => count($tasks),
            'assignedTasks' => count($result->getAssignments()),
        ];

        return $this->json($payload);
    }

    #[Route('/employees', name: 'api_employees', methods: ['GET'])]
    public function getEmployees(): JsonResponse
    {
        $employees = $this->employeeRepository->findAll();
        
        $data = array_map(fn($emp) => [
            'employeeId' => $emp->getEmployeeId(),
            'skillLevel' => $emp->getSkillLevel(),
            'hourlyRate' => $emp->getHourlyRate(),
        ], $employees);

        return $this->json([
            'count' => count($data),
            'employees' => $data,
        ]);
    }

    #[Route('/tasks', name: 'api_tasks', methods: ['GET'])]
    public function getTasks(): JsonResponse
    {
        $tasks = $this->taskRepository->findAll();
        
        $data = array_map(fn($task) => [
            'taskId' => $task->getTaskId(),
            'skillLevel' => $task->getSkillLevel(),
            'estimation' => $task->getEstimation(),
        ], $tasks);

        return $this->json([
            'count' => count($data),
            'tasks' => $data,
        ]);
    }

    #[Route('/health', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'service' => 'Task Assignment Optimization API',
        ]);
    }
}
