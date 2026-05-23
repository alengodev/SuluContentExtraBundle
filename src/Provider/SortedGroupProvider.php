<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Provider;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormGroup;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * Decorates the core `sulu_admin.metadata_group_provider` to sort form groups
 * by the order in which their translation keys appear in the translation
 * catalogue (domain "admin", prefix `sulu_admin.template_group.`).
 *
 * Whoever maintains `translations/admin+intl-icu.{locale}.yaml` controls the
 * display order — rearrange the lines under `sulu_admin.template_group` to
 * change the order of:
 *   - Article admin tabs (article / blog / job / reference)
 *   - SmartContent Type filter dropdown
 *   - any other consumer of `GroupProviderInterface::getGroups()`.
 *
 * Groups whose identifier has no entry in the translation file are appended at
 * the end, sorted alphabetically by their (already-translated) title.
 */
final readonly class SortedGroupProvider implements GroupProviderInterface
{
    private const TRANSLATION_DOMAIN = 'admin';

    private const TRANSLATION_PREFIX = 'sulu_admin.template_group.';

    public function __construct(
        private GroupProviderInterface $inner,
        private TranslatorBagInterface $translatorBag,
    ) {
    }

    public function getGroups(string $key): array
    {
        $groups = $this->inner->getGroups($key);
        $positions = $this->buildPositionMap();
        $fallback = \count($positions);

        \uasort(
            $groups,
            static function (FormGroup $a, FormGroup $b) use ($positions, $fallback): int {
                $posA = $positions[$a->identifier] ?? $fallback;
                $posB = $positions[$b->identifier] ?? $fallback;
                if ($posA !== $posB) {
                    return $posA <=> $posB;
                }

                return \strnatcasecmp($a->title, $b->title);
            },
        );

        return $groups;
    }

    /**
     * Builds an identifier → position map from the translation catalogue.
     * Position is the index at which the key appears in the YAML file.
     *
     * @return array<string, int>
     */
    private function buildPositionMap(): array
    {
        $messages = $this->translatorBag->getCatalogue()->all(self::TRANSLATION_DOMAIN);

        $positions = [];
        $index = 0;
        foreach ($messages as $messageKey => $_) {
            if (!\str_starts_with($messageKey, self::TRANSLATION_PREFIX)) {
                continue;
            }
            $identifier = \substr($messageKey, \strlen(self::TRANSLATION_PREFIX));
            $positions[$identifier] = $index++;
        }

        return $positions;
    }
}
