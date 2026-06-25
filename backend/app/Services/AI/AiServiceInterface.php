<?php

namespace App\Services\AI;

interface AiServiceInterface
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function analyzeEntry(array $context): array;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function analyzePosition(array $context): array;
}
