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
        // so write all configured keys in a single call.
        if ($unlocalizedDimensionContent === $localizedDimensionContent) {
            if ($unlocalizedDimensionContent instanceof AdditionalDataInterface) {
                $unlocalizedDimensionContent->setAdditionalData(
                    $this->writeKeys(
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
                $this->writeKeys(
                    $unlocalizedDimensionContent->getAdditionalData(),
                    $data,
                    $this->unlocalizedKeys,
                ),
            );
        }

        if ($localizedDimensionContent instanceof AdditionalDataInterface) {
            $localizedDimensionContent->setAdditionalData(
                $this->writeKeys(
                    $localizedDimensionContent->getAdditionalData(),
                    $data,
                    $this->localizedKeys,
                ),
            );
        }
    }

    /**
     * Write the submitted values for the configured keys over the already stored
     * additionalData.
     *
     * Only keys that are actually present in $data are mapped — including when their value
     * is empty (null), so clearing a field in the additionalData form is stored as an empty
     * value rather than dropped. Configured keys that are not part of the submission are
     * left untouched: they are neither added with a null placeholder (so a resource whose
     * form does not contain a key — e.g. an article without construction_year/location —
     * never gets that key written) nor removed.
     *
     * As a result an unrelated save (the main content tab, which lives in a separate form
     * and carries none of these keys) or a version restore of content that predates the
     * field preserves the existing additionalData unchanged, instead of wiping it and
     * corrupting the version snapshot taken from the draft.
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $data
     * @param array<int, string>   $keys
     *
     * @return array<string, mixed>
     */
    private function writeKeys(array $existing, array $data, array $keys): array
    {
        if ([] === $keys) {
            return $existing;
        }

        $incoming = \array_intersect_key($data, \array_flip($keys));

        return \array_merge($existing, $incoming);
    }
}
