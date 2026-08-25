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
     * Produce the additionalData to store for the given configured keys.
     *
     * The additionalData fields live in their own admin form, separate from the main
     * content form. Depending on which form is being saved, $data either carries the
     * additionalData fields or none of them:
     *
     *  - When the additionalData form is submitted, at least one configured key is present
     *    in $data. In that case the *complete* configured key set is written back, using
     *    the submitted value and defaulting anything omitted to null — so empty fields are
     *    stored too, exactly like Sulu persists the full "seo"/"excerpt" extension data.
     *    This keeps every configured key present in the stored JSON instead of silently
     *    dropping the empty ones.
     *
     *  - When none of the configured keys is present, the submission belongs to another
     *    form (e.g. the main content tab) or to a version restore of content that predates
     *    the field. The existing additionalData is then preserved unchanged, so an
     *    unrelated save never wipes it — which would otherwise also corrupt the version
     *    snapshot taken from the draft.
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

        $submitted = false;
        foreach ($keys as $key) {
            if (\array_key_exists($key, $data)) {
                $submitted = true;

                break;
            }
        }

        if (!$submitted) {
            return $existing;
        }

        $additionalData = [];
        foreach ($keys as $key) {
            $additionalData[$key] = $data[$key] ?? null;
        }

        return $additionalData;
    }
}
