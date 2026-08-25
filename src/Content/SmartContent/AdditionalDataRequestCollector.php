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
 * properties reference additionalData it records the requested alias => field mapping
 * here, and the enhancement attaches exactly those fields (under their alias) to the item.
 * Reset per request/worker message so nothing leaks across resolutions.
 */
final class AdditionalDataRequestCollector implements ResetInterface
{
    /** @var array<string, array<string, string>> resource uuid => alias => additionalData field */
    private array $map = [];

    /**
     * @param array<string, string> $aliasToField alias => additionalData field name
     */
    public function request(string $uuid, array $aliasToField): void
    {
        foreach ($aliasToField as $alias => $field) {
            $this->map[$uuid][$alias] = $field;
        }
    }

    public function isRequested(string $uuid): bool
    {
        return !empty($this->map[$uuid]);
    }

    /**
     * @return array<string, string> alias => field
     */
    public function requestedMap(string $uuid): array
    {
        return $this->map[$uuid] ?? [];
    }

    public function reset(): void
    {
        $this->map = [];
    }
}
