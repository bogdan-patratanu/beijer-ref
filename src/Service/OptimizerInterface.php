<?php

namespace App\Service;

use App\Service\OptimizationResult;

interface OptimizerInterface
{
    public function optimize(array $employees, array $tasks): OptimizationResult;
    
    public function getName(): string;
}
