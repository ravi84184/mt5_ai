<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QueueMonitor
{
    /**
     * @return Collection<int, object{
     *     id: int,
     *     queue: string,
     *     job_name: string,
     *     attempts: int,
     *     status: string,
     *     waiting_seconds: int,
     *     created_at: string,
     * }>
     */
    public static function pendingJobs(int $limit = 25): Collection
    {
        return DB::table('jobs')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                $createdAt = Carbon::createFromTimestamp((int) $job->created_at);
                $waitingSeconds = max(0, now()->diffInSeconds($createdAt));
                $isRunning = $job->reserved_at !== null;

                return (object) [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'job_name' => self::resolveJobName($payload),
                    'attempts' => (int) $job->attempts,
                    'status' => $isRunning ? 'running' : 'waiting',
                    'waiting_seconds' => $waitingSeconds,
                    'created_at' => $createdAt->toDateTimeString(),
                ];
            });
    }

    /**
     * @return Collection<int, object{
     *     id: int,
     *     uuid: string,
     *     queue: string,
     *     job_name: string,
     *     failed_at: string,
     *     exception_summary: string,
     * }>
     */
    public static function failedJobs(int $limit = 50): Collection
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);

                return (object) [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'job_name' => self::resolveJobName($payload),
                    'failed_at' => $job->failed_at,
                    'exception_summary' => self::summarizeException($job->exception),
                ];
            });
    }

    public static function failedJob(int $id): ?object
    {
        $job = DB::table('failed_jobs')->where('id', $id)->first();
        if (! $job) {
            return null;
        }

        $payload = json_decode($job->payload, true);

        return (object) [
            'id' => $job->id,
            'uuid' => $job->uuid,
            'queue' => $job->queue,
            'connection' => $job->connection,
            'job_name' => self::resolveJobName($payload),
            'failed_at' => $job->failed_at,
            'payload' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'exception' => $job->exception,
            'exception_summary' => self::summarizeException($job->exception),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private static function resolveJobName(?array $payload): string
    {
        if (! is_array($payload)) {
            return 'unknown';
        }

        if (! empty($payload['displayName'])) {
            return (string) $payload['displayName'];
        }

        $command = $payload['data']['command'] ?? null;
        if (is_string($command) && preg_match('/O:\d+:"([^"]+)"/', $command, $matches)) {
            return class_basename($matches[1]);
        }

        return 'unknown';
    }

    private static function summarizeException(?string $exception): string
    {
        if (! $exception) {
            return 'Unknown error';
        }

        if (preg_match('/^([^\n:]+)/', $exception, $matches)) {
            return trim($matches[1]);
        }

        return \Illuminate\Support\Str::limit($exception, 120);
    }
}
