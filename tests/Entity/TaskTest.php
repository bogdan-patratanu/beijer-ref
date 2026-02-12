<?php

namespace App\Tests\Entity;

use App\Entity\Task;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{
    public function testTaskCreation(): void
    {
        $task = new Task(1, 2, 5);

        $this->assertEquals(1, $task->getTaskId());
        $this->assertEquals(2, $task->getSkillLevel());
        $this->assertEquals(5, $task->getEstimation());
    }

}
