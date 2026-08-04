<?php

namespace App\Entity\SyllabusTemplate;

final class SyllabusProvenanceV1
{
    public const VERSION = '1.0';

    /** @param array<string, mixed> $data */
    private function __construct(private readonly array $data)
    {
    }

    public static function manualEntry(): self
    {
        return new self(self::base(SyllabusRevisionSource::ManualEntry));
    }

    public static function sharedTemplatePrefill(TemplateRevision $source): self
    {
        return new self(array_replace(self::base(SyllabusRevisionSource::SharedTemplatePrefill), [
            'source_revision' => [
                'id' => $source->getId(),
                'revision_number' => $source->getRevisionNumber(),
                'schema_version' => $source->getSchemaVersion(),
            ],
        ]));
    }

    public static function manualEdit(TemplateRevision $source): self
    {
        return new self(array_replace(self::base(SyllabusRevisionSource::ManualEdit), [
            'source_revision' => [
                'id' => $source->getId(),
                'revision_number' => $source->getRevisionNumber(),
                'schema_version' => $source->getSchemaVersion(),
            ],
        ]));
    }

    /**
     * @param array<string, array{page?: int|null, confidence?: float|null, method?: string|null}> $fields
     */
    public static function pdfExtraction(
        string $originalFilename,
        string $sha256,
        int $sizeBytes,
        array $fields = [],
        ?string $extractor = null,
        ?string $extractorVersion = null,
        ?\DateTimeImmutable $extractedAt = null,
    ): self {
        $filename = trim($originalFilename);
        $hash = strtolower(trim($sha256));
        if ($filename === '' || !preg_match('/^[a-f0-9]{64}$/', $hash) || $sizeBytes <= 0) {
            throw new \InvalidArgumentException('PDF provenance requires a filename, SHA-256 hash, and positive byte size.');
        }

        $normalizedFields = [];
        foreach ($fields as $field => $details) {
            $canonicalField = SyllabusContentV1::canonicalFieldName((string)$field);
            $confidence = isset($details['confidence']) ? (float)$details['confidence'] : null;
            if ($confidence !== null && ($confidence < 0 || $confidence > 1)) {
                throw new \InvalidArgumentException('Field confidence must be between 0 and 1.');
            }

            $normalizedFields[$canonicalField] = [
                'page' => isset($details['page']) ? (int)$details['page'] : null,
                'confidence' => $confidence,
                'method' => isset($details['method']) ? trim((string)$details['method']) : null,
            ];
        }

        return new self(array_replace(self::base(SyllabusRevisionSource::PdfExtraction), [
            'source_document' => [
                'original_filename' => $filename,
                'media_type' => 'application/pdf',
                'sha256' => $hash,
                'size_bytes' => $sizeBytes,
            ],
            'extraction' => [
                'extractor' => trim((string)$extractor) ?: null,
                'extractor_version' => trim((string)$extractorVersion) ?: null,
                'extracted_at' => ($extractedAt ?? new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ],
            'fields' => $normalizedFields,
        ]));
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $source = SyllabusRevisionSource::tryFrom((string)($data['source_type'] ?? ''))
            ?? SyllabusRevisionSource::ManualEntry;

        return new self(array_replace(self::base($source), $data, [
            'schema_version' => self::VERSION,
            'source_type' => $source->value,
        ]));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function getSourceType(): SyllabusRevisionSource
    {
        return SyllabusRevisionSource::from($this->data['source_type']);
    }

    /** @return array<string, mixed>|null */
    public function getSourceDocument(): ?array
    {
        return is_array($this->data['source_document'] ?? null)
            ? $this->data['source_document']
            : null;
    }

    /** @return array<string, array<string, mixed>> */
    public function getFields(): array
    {
        return is_array($this->data['fields'] ?? null) ? $this->data['fields'] : [];
    }

    /** @return array<string, mixed> */
    private static function base(SyllabusRevisionSource $source): array
    {
        return [
            'schema_version' => self::VERSION,
            'source_type' => $source->value,
            'source_document' => null,
            'source_revision' => null,
            'extraction' => null,
            'fields' => [],
        ];
    }
}
