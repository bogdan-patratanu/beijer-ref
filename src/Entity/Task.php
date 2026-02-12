<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'tasks')]
class Task
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $taskId;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 1, max: 10)]
    private int $skillLevel;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $estimation;


    public function __construct(int $taskId, int $skillLevel, int $estimation)
    {
        $this->taskId = $taskId;
        $this->skillLevel = $skillLevel;
        $this->estimation = $estimation;
    }

    public function getTaskId(): int
    {
        return $this->taskId;
    }

    public function getSkillLevel(): int
    {
        return $this->skillLevel;
    }

    public function getEstimation(): int
    {
        return $this->estimation;
    }
}
