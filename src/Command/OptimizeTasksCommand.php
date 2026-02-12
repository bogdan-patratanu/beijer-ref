<?php

/**
 * OptimizeTasksCommand - CLI endpoint for task assignment optimization.
 *
 * Uses the same OptimizationResult::toArray() canonical format as the API controller.
 *
 * Functions defined in this file:
 * - configure(): Defines the 'strategy' argument (cost | makespan).
 * - execute(): Loads data, runs the selected optimizer, and renders the result
 *   from OptimizationResult::toArray() as formatted console tables.
 */

namespace App\Command;

use App\Repository\EmployeeRepository;
use App\Repository\TaskRepository;
use App\Service\MinimumCostOptimizer;
use App\Service\MinimumMakespanOptimizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:optimize-tasks',
    description: 'Optimize task assignments using specified strategy (cost or makespan)',
)]
class OptimizeTasksCommand extends Command
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly TaskRepository $taskRepository,
        private readonly MinimumCostOptimizer $costOptimizer,
        private readonly MinimumMakespanOptimizer $makespanOptimizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('strategy', InputArgument::REQUIRED, 'Optimization strategy: "cost" or "makespan"')
            ->setHelp('This command optimizes task assignments based on the specified strategy.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $strategy = strtolower($input->getArgument('strategy'));

        if (!in_array($strategy, ['cost', 'makespan'])) {
            $io->error('Invalid strategy. Use "cost" or "makespan".');
            return Command::FAILURE;
        }

        $employees = $this->employeeRepository->findAll();
        $tasks = $this->taskRepository->findAll();

        if (empty($employees)) {
            $io->error('No employees found in database.');
            return Command::FAILURE;
        }

        if (empty($tasks)) {
            $io->error('No tasks found in database.');
            return Command::FAILURE;
        }

        $optimizer = $strategy === 'cost' ? $this->costOptimizer : $this->makespanOptimizer;
        
        $io->title('Task Assignment Optimization');
        $io->section('Strategy: ' . $optimizer->getName());
        $io->text(sprintf('Employees: %d | Tasks: %d', count($employees), count($tasks)));
        $io->newLine();

        $startTime = microtime(true);
        $result = $optimizer->optimize($employees, $tasks);
        $executionTime = microtime(true) - $startTime;

        // Use OptimizationResult::toArray() — same canonical format as the API.
        $data = $result->toArray();

        if (!$data['feasible']) {
            $io->error('Optimization Failed: ' . $data['reason']);
            return Command::FAILURE;
        }

        $io->success('Optimization completed successfully!');
        
        $io->section('Results Summary');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Cost', '$' . number_format($data['totalCost'])],
                ['Makespan (hours)', number_format($data['makespan'], 2)],
                ['Execution Time', number_format($executionTime * 1000, 2) . ' ms'],
            ]
        );

        $io->section('Employee Workload Summary');
        $io->table(
            ['Employee ID', 'Total Hours'],
            array_map(
                fn($emp) => [$emp['employeeId'], number_format($emp['totalAssignedHours'], 2)],
                $data['employeeSummary']
            )
        );

        $io->section('Task Assignments');
        $io->table(
            ['Task ID', 'Employee ID', 'Start Time', 'End Time', 'Cost'],
            array_map(
                fn($a) => [
                    $a['taskId'],
                    $a['employeeId'],
                    number_format($a['startTime'], 2),
                    number_format($a['endTime'], 2),
                    '$' . number_format($a['cost'])
                ],
                $data['assignments']
            )
        );

        return Command::SUCCESS;
    }
}
