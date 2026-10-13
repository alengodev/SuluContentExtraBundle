<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\DependencyInjection;

/**
 * The processed config of one content type (page, article or snippet), see
 * {@see Configuration::buildContentNode()}. Turns the untyped array the config component
 * hands out into checked values, so the extension reads them without guessing their shape.
 *
 * @internal
 */
final readonly class ContentTypeConfig
{
    /**
     * @param list<string> $unlocalizedKeys
     * @param list<string> $localizedKeys
     */
    private function __construct(
        public bool $enabled,
        public string $rootClass,
        public string $entityClass,
        public string $formKey,
        public string $tabTitle,
        public array $unlocalizedKeys,
        public array $localizedKeys,
    ) {
    }

    /**
     * @param array<mixed> $config processed config of the content type
     * @param string $rootClassKey key of the content root class, e.g. "page_class"
     */
    public static function fromArray(array $config, string $rootClassKey): self
    {
        return new self(
            true === ($config['enabled'] ?? false),
            self::string($config, $rootClassKey),
            self::string($config, 'entity_class'),
            self::string($config, 'form_key'),
            self::string($config, 'tab_title'),
            self::stringList($config, 'unlocalized_keys'),
            self::stringList($config, 'localized_keys'),
        );
    }

    /**
     * @param array<mixed> $config
     */
    private static function string(array $config, string $key): string
    {
        $value = $config[$key] ?? null;
        if (!\is_string($value)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" option must be a string, got %s.', $key, \get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param array<mixed> $config
     *
     * @return list<string>
     */
    private static function stringList(array $config, string $key): array
    {
        $values = $config[$key] ?? [];
        if (!\is_array($values)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" option must be a list, got %s.', $key, \get_debug_type($values)));
        }

        $list = [];
        foreach ($values as $value) {
            if (!\is_string($value)) {
                throw new \InvalidArgumentException(\sprintf('The "%s" option must only contain strings, got %s.', $key, \get_debug_type($value)));
            }

            $list[] = $value;
        }

        return $list;
    }
}
