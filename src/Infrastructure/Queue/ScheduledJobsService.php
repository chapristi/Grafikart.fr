<?php

namespace App\Infrastructure\Queue;

use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class ScheduledJobsService
{
    public function __construct(private readonly string $dsn)
    {
    }

    public function getConnection(): \Redis
    {
        /** @var array $url */
        $url = parse_url($this->dsn);
        $redis = (new \Redis());
        if (!$redis->connect($url['host'], $url['port'])) {
            throw new \RuntimeException('Impossible de se connecter à redis');
        }

        return $redis;
    }

    /**
     * @return ScheduledJob[]
     */
    public function getJobs(): array
    {
        if (!str_starts_with($this->dsn, 'redis://')) {
            return [];
        }
        $messages = $this->getConnection()->zRange('messages__queue', 0, 10);
        if (empty($messages)) {
            return [];
        }
        $serializer = new PhpSerializer();
        $index = 0;
        $jobs = array_map(function (string $message) use ($serializer, &$index) {
            $data = json_decode(unserialize($message), true, 512, JSON_THROW_ON_ERROR);

            return new ScheduledJob($serializer->decode($data), $index++);
        }, $messages);

        return $jobs;
    }

    public function deleteJob(int $jobId): void
    {
        $this->getConnection()->zRemRangeByRank('messages__queue', $jobId, $jobId);
    }
}
