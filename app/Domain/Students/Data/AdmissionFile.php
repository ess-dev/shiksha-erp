<?php

namespace App\Domain\Students\Data;

/**
 * A scan already stored on the tenant disk during admission. `kind` is a
 * `DocumentKind` value.
 */
final readonly class AdmissionFile
{
    public function __construct(
        public string $kind,
        public string $originalName,
        public string $path,
        public string $mimeType,
        public int $sizeBytes,
        public ?string $uploadedByIdentityUlid,
    ) {}
}
