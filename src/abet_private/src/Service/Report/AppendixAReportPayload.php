<?php

namespace App\Service\Report;

final class AppendixAReportPayload implements \JsonSerializable
{
    public const SCHEMA_VERSION = '1.0';

    /** @param list<array<string, mixed>> $courses */
    public function __construct(private readonly array $courses)
    {
        if ($courses === []) {
            throw new \InvalidArgumentException('An Appendix A export requires at least one course.');
        }
    }

    /** @return array{schema_version: string, courses: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'courses' => $this->courses,
        ];
    }

    /** @return array{schema_version: string, courses: list<array<string, mixed>>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
