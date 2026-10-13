# Changelog

## 1.7.0 (unreleased)

### Added

- Sulu 3.1 review workflow: the Additional Data tab carries the core review toolbar (request for publish,
  approval overlay, locked while a request is open); Sulu 3.0.x keeps the previous save/publish toolbar.
- Pre-validator `additional_data_required` for Sulu 3.1 request workflows.
- `sourceLinkResolved` navigation marker, `false` when the target of a link page is gone.
- Admin translations (de, en, it) for the default tab title and the pre-validator.
- Visible conditions understand the jexl `|length` transform and dotted paths into nested data.
- The container build fails on a key listed both as localized and unlocalized, and on an
  `entity_class` that does not implement `AdditionalDataInterface`.
- Tests, PHPStan (level max), PHP-CS-Fixer, Rector and GitHub Actions CI.

### Fixed

- The Snippet Additional Data tab is registered again on Sulu 3.1, which groups snippets per type.
- PHP 8.2: `NavigationLinkTypeResolver` used a typed class constant (PHP 8.3+), a fatal error on 8.2.
- Version restore detaches only the restored content root, so pending changes of other content loaded
  in the same request are no longer lost.
- Smart content: a resource referenced by two smart contents with different `properties` gets the fields
  of each, no longer a mix of both.
- `resolve_target_entities` for pages is only registered when `sulu_page` is installed.

### Changed

- Requires Sulu ^3.0.8, which fixes several security advisories of earlier 3.0 releases.

## 1.6.7 and earlier

See the [release tags](https://github.com/alengodev/SuluContentExtraBundle/tags).
