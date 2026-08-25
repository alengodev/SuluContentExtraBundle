<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\SmartContent;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Bridges the AdditionalDataResolver (which receives the smart_content `properties`
 * config) and the resource-loader enhancement (which surfaces additionalData on items
 * but does NOT receive properties).
 *
 * The resolver runs immediately before the enhancement for the same item; when the
 * properties reference additionalData it flags the item's resource UUID here, and the
 * enhancement only attaches additionalData for flagged UUIDs. Reset per request/worker
 * message so flags never leak across resolutions.
 */
final class AdditionalDataRequestCollector implements ResetInterface
{
    /** @var array<string, true> */
    private array $requested = [];

    public function request(string $uuid): void
    {
        $this->requested[$uuid] = true;
    }

    public function isRequested(string $uuid): bool
    {
        return isset($this->requested[$uuid]);
    }

    public function reset(): void
    {
        $this->requested = [];
    }
}
