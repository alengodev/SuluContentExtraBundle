<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\SmartContent;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Bridges the AdditionalDataResolver (which receives the smart_content `properties`
 * config) and the resource-loader enhancement (which surfaces additionalData on items
 * but does NOT receive properties).
 *
 * Sulu's ContentResolver runs the resolvers of a smart-content item immediately before the
 * enhancement of that same item. The resolver records the alias => field mapping of the
 * item's `properties` here, replacing any earlier mapping for the uuid, and the enhancement
 * takes it out again. So the same resource referenced by two smart contents with different
 * `properties` gets exactly the fields of the one being resolved, never a mix of both.
 * Reset per request/worker message so nothing leaks across resolutions.
 */
final class AdditionalDataRequestCollector implements ResetInterface
{
    /** @var array<string, array<string, string>> resource uuid => alias => additionalData field */
    private array $map = [];

    /**
     * Records the fields the item currently being resolved references. An empty mapping
     * clears an earlier one, so a later item without additionalData properties gets nothing.
     *
     * @param array<string, string> $aliasToField alias => additionalData field name
     */
    public function request(string $uuid, array $aliasToField): void
    {
        if ([] === $aliasToField) {
            unset($this->map[$uuid]);

            return;
        }

        $this->map[$uuid] = $aliasToField;
    }

    /**
     * Returns and forgets the mapping recorded for the item being enhanced.
     *
     * @return array<string, string> alias => field
     */
    public function take(string $uuid): array
    {
        $aliasToField = $this->map[$uuid] ?? [];
        unset($this->map[$uuid]);

        return $aliasToField;
    }

    public function reset(): void
    {
        $this->map = [];
    }
}
