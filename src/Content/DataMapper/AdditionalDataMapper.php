<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\DataMapper;

use Alengo\SuluContentExtraBundle\Model\AdditionalDataInterface;
use Sulu\Content\Application\ContentDataMapper\DataMapper\DataMapperInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

final class AdditionalDataMapper implements DataMapperInterface
{
    /**
     * @param class-string                 $entityClass
     * @param array<int, string>           $unlocalizedKeys
     * @param array<int, string>           $localizedKeys
     */
    public function __construct(
        private readonly string $entityClass,
        private readonly array $unlocalizedKeys,
        private readonly array $localizedKeys,
    ) {
    }

    public function map(
        DimensionContentInterface $unlocalizedDimensionContent,
        DimensionContentInterface $localizedDimensionContent,
        array $data,
    ): void {
        if (!$localizedDimensionContent instanceof $this->entityClass) {
            return;
        }

        // In preview mode both dimension contents are the same merged object.
        // Calling setAdditionalData() twice would overwrite the first set of keys,
        // so merge all configured keys in a single call.
        if ($unlocalizedDimensionContent === $localizedDimensionContent) {
            if ($unlocalizedDimensionContent instanceof AdditionalDataInterface) {
                $unlocalizedDimensionContent->setAdditionalData(
                    $this->mergeKeys(
                        $unlocalizedDimensionContent->getAdditionalData(),
                        $data,
                        \array_merge($this->unlocalizedKeys, $this->localizedKeys),
                    ),
                );
            }

            return;
        }

        if ($unlocalizedDimensionContent instanceof AdditionalDataInterface) {
            $unlocalizedDimensionContent->setAdditionalData(
                $this->mergeKeys(
                    $unlocalizedDimensionContent->getAdditionalData(),
                    $data,
                    $this->unlocalizedKeys,
                ),
            );
        }

        if ($localizedDimensionContent instanceof AdditionalDataInterface) {
            $localizedDimensionContent->setAdditionalData(
                $this->mergeKeys(
                    $localizedDimensionContent->getAdditionalData(),
                    $data,
                    $this->localizedKeys,
                ),
            );
        }
    }

    /**
     * Merge the incoming values for the configured keys over the already stored
     * additionalData. Only keys that are actually present in $data are updated; keys
     * missing from the submission keep their existing value.
     *
     * This guarantees additionalData is always written back on every persist, even when
     * the current submission carries no additionalData entries — e.g. a save of the main
     * content tab (which lives in a separate form and does not include these fields) or a
     * version restore of content that predates the field. Without this, such a persist
     * would reset additionalData to an empty array and silently drop the stored values,
     * which in turn corrupts the version snapshot created from the draft.
     *
     * A key that is present but null is treated as an explicit clear (dropped), so
     * emptying a field in the additionalData form still removes it.
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $data
     * @param array<int, string>   $keys
     *
     * @return array<string, mixed>
     */
    private function mergeKeys(array $existing, array $data, array $keys): array
    {
        if ([] === $keys) {
            return $existing;
        }

        $incoming = \array_intersect_key($data, \array_flip($keys));

        return \array_filter(
            \array_merge($existing, $incoming),
            static fn ($value) => null !== $value,
        );
    }
}
