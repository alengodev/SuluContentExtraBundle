<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\DataMapper;

use Alengo\SuluContentExtraBundle\Model\AdditionalDataInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Content\Application\ContentDataMapper\DataMapper\DataMapperInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

final class AdditionalDataMapper implements DataMapperInterface
{
    private readonly ExpressionLanguage $expressionLanguage;

    /**
     * @param class-string       $entityClass
     * @param array<int, string> $unlocalizedKeys
     * @param array<int, string> $localizedKeys
     */
    public function __construct(
        private readonly string $entityClass,
        private readonly array $unlocalizedKeys,
        private readonly array $localizedKeys,
        private readonly string $formKey,
        private readonly MetadataProviderInterface $formMetadataProvider,
    ) {
        $this->expressionLanguage = new ExpressionLanguage();
    }

    public function map(
        DimensionContentInterface $unlocalizedDimensionContent,
        DimensionContentInterface $localizedDimensionContent,
        array $data,
    ): void {
        if (!$localizedDimensionContent instanceof $this->entityClass) {
            return;
        }

        // Keys whose form field is hidden for the currently submitted template (their
        // visibleCondition evaluates to false). Such fields are still sent by the admin as
        // null, but must not be written — otherwise e.g. an article using a template
        // without construction_year/location would still persist those keys.
        $hiddenKeys = $this->resolveHiddenKeys($data, $localizedDimensionContent->getLocale());

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
                        $hiddenKeys,
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
                    $hiddenKeys,
                ),
            );
        }

        if ($localizedDimensionContent instanceof AdditionalDataInterface) {
            $localizedDimensionContent->setAdditionalData(
                $this->writeKeys(
                    $localizedDimensionContent->getAdditionalData(),
                    $data,
                    $this->localizedKeys,
                    $hiddenKeys,
                ),
            );
        }
    }

    /**
     * Update the stored additionalData for the given configured keys from the submission.
     *
     * Per configured key:
     *  - absent from $data                → left unchanged (an unrelated save, e.g. the main
     *                                       content tab, never wipes additionalData, which
     *                                       would also corrupt the version snapshot);
     *  - present and visible              → written, including an empty (null) value, so the
     *                                       full set of a template's active fields is stored
     *                                       exactly like Sulu persists the seo/excerpt data;
     *  - present but hidden for the current template → removed, so fields that do not belong
     *                                       to the submitted template are not persisted.
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $data
     * @param array<int, string>   $keys
     * @param array<string, true>  $hiddenKeys
     *
     * @return array<string, mixed>
     */
    private function writeKeys(array $existing, array $data, array $keys, array $hiddenKeys): array
    {
        $additionalData = $existing;

        foreach ($keys as $key) {
            if (!\array_key_exists($key, $data)) {
                continue;
            }

            if (isset($hiddenKeys[$key])) {
                unset($additionalData[$key]);

                continue;
            }

            $additionalData[$key] = $data[$key];
        }

        return $additionalData;
    }

    /**
     * Determine which configured keys are hidden for the submitted template by evaluating
     * their form field's visibleCondition against $data.
     *
     * Fails open: any problem resolving the form metadata or evaluating a condition leaves
     * the key visible, so this filter can never drop data it does not understand.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, true> set of hidden key names
     */
    private function resolveHiddenKeys(array $data, ?string $locale): array
    {
        if (null === $locale) {
            return [];
        }

        try {
            $formMetadata = $this->formMetadataProvider->getMetadata($this->formKey, $locale, []);
        } catch (\Throwable) {
            return [];
        }

        if (!$formMetadata instanceof FormMetadata) {
            return [];
        }

        $keys = \array_merge($this->unlocalizedKeys, $this->localizedKeys);
        $conditions = [];
        $this->collectConditions($formMetadata->getItems(), null, $conditions);

        $hidden = [];
        foreach ($conditions as $key => $condition) {
            if (null === $condition || !\in_array($key, $keys, true)) {
                continue;
            }

            try {
                if (!$this->expressionLanguage->evaluate($condition, $data)) {
                    $hidden[$key] = true;
                }
            } catch (\Throwable) {
                // Unparseable/unsupported condition: keep the field visible.
            }
        }

        return $hidden;
    }

    /**
     * Walk the form items and collect, per field, the combined visibleCondition of the
     * field and its enclosing section(s). Null means the field is always visible.
     *
     * @param array<int, object>         $items
     * @param array<string, string|null> $conditions
     */
    private function collectConditions(array $items, ?string $parentCondition, array &$conditions): void
    {
        foreach ($items as $item) {
            if ($item instanceof SectionMetadata) {
                $this->collectConditions(
                    $item->getItems(),
                    $this->combineConditions($parentCondition, $item->getVisibleCondition()),
                    $conditions,
                );

                continue;
            }

            if ($item instanceof FieldMetadata) {
                $conditions[$item->getName()] = $this->combineConditions(
                    $parentCondition,
                    $item->getVisibleCondition(),
                );
            }
        }
    }

    private function combineConditions(?string $parentCondition, ?string $condition): ?string
    {
        $parentCondition = ('' === $parentCondition) ? null : $parentCondition;
        $condition = ('' === $condition) ? null : $condition;

        if (null === $parentCondition) {
            return $condition;
        }

        if (null === $condition) {
            return $parentCondition;
        }

        return '(' . $parentCondition . ') && (' . $condition . ')';
    }
}
