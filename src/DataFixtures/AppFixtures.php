<?php

namespace App\DataFixtures;

use App\Entity\Employee;
use App\Entity\Task;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $employeesData = json_decode(file_get_contents(__DIR__ . '/../../data/employees.json'), true);
        $tasksData = json_decode(file_get_contents(__DIR__ . '/../../data/tasks.json'), true);

        foreach ($employeesData as $data) {
            $employee = new Employee(
                $data['employeeId'],
                $data['skillLevel'],
                $data['hourlyRate']
            );
            $manager->persist($employee);
        }

        foreach ($tasksData as $data) {
            $task = new Task(
                $data['taskId'],
                $data['skillLevel'],
                $data['estimation']
            );
            $manager->persist($task);
        }

        $manager->flush();
    }
}
