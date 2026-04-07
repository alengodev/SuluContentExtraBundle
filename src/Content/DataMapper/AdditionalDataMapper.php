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
                    \array_merge(
                        $this->filterKeys($data, $this->unlocalizedKeys),
                        $this->filterKeys($data, $this->localizedKeys),
                    ),
                );
            }

            return;
        }

        if ($unlocalizedDimensionContent instanceof AdditionalDataInterface) {
            $unlocalizedDimensionContent->setAdditionalData(
                $this->filterKeys($data, $this->unlocalizedKeys),
            );
        }

        if ($localizedDimensionContent instanceof AdditionalDataInterface) {
            $localizedDimensionContent->setAdditionalData(
                $this->filterKeys($data, $this->localizedKeys),
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string>   $keys
     *
     * @return array<string, mixed>
     */
    private function filterKeys(array $data, array $keys): array
    {
        if ([] === $keys) {
            return [];
        }

        return \array_filter(
            \array_intersect_key($data, \array_flip($keys)),
            static fn ($value) => null !== $value,
        );
    }
}
