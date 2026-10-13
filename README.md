# SuluContentExtraBundle

Extends [Sulu CMS](https://sulu.io/) 3.x Pages, Articles and Snippets with configurable additional data and navigation link markers.

## Features

- **Additional Data tab** — auto-registered for Pages, Articles and Snippets via `PreviewFormViewBuilder`
- **Built-in entities** — concrete `Page`, `PageDimensionContent`, `Article`, `ArticleDimensionContent`, `Snippet`, `SnippetDimensionContent` extending Sulu's base classes; no project entities required
- **Configurable field mapping** — declare which form fields go to the unlocalized vs. localized dimension content via bundle config
- **Zero-config entity registration** — `sulu_page` / `sulu_article` / `sulu_snippet` objects are auto-configured via `PrependExtensionInterface`
- **Review workflow aware** — on Sulu 3.1 the Additional Data tab carries the same review toolbar as the core tabs (request for publish, approval overlay, locked while a request is open); on 3.0.x the previous save/publish toolbar
- **Review pre-validator** — `additional_data_required` blocks review requests and publishing while configured fields are empty (Sulu 3.1)
- **Fails loud on misconfiguration** — a key listed as both localized and unlocalized, or an `entity_class` without `AdditionalDataInterface`, stops the container build
- **Navigation link markers** — `NavigationLinkEnhancer` adds `sourceLink`/`sourceLinkResolved`/`sourceUuid` markers to link-type pages; `NavigationLinkTypeResolver` exposes them to templates
- **Sortable template groups** — `SortedGroupProvider` decorates `sulu_admin.metadata_group_provider` and orders form groups (Article admin tabs, SmartContent Type filter, ...) by the appearance order of their `sulu_admin.template_group.*` keys in `translations/admin+intl-icu.{locale}.yaml`

## Requirements

- PHP 8.2+
- Sulu CMS ^3.0.8, including 3.1 (3.0.8 fixes several security advisories) (Doctrine/Gedmo compatibility fixes for mapped-superclass entities are bundled with Sulu since 3.0.6 — see [sulu/sulu#8743](https://github.com/sulu/sulu/pull/8743))
- Symfony 6.4, 7.x or 8.x (whichever your Sulu version supports)

## Installation

```bash
composer require alengo/sulu-content-extra-bundle
```

Register the bundle in `config/bundles.php`:

```php
Alengo\SuluContentExtraBundle\AlengoContentExtraBundle::class => ['all' => true],
```

The bundle registers the Doctrine mapping of its entities itself, so it works with and without
`doctrine.orm.auto_mapping` (DoctrineBundle 3 defaults to `false`). It assumes the single default entity manager
configured through `doctrine.orm`.

That's it — no further configuration required for a standard setup.

## Configuration

Create `config/packages/alengo_content_extra.yaml` to declare which form fields are stored in the unlocalized vs. localized dimension content:

```yaml
alengo_content_extra:
    page:
        form_key: page_additional_data        # default
        unlocalized_keys:
            - template_theme
            - template_logo_light
        localized_keys:
            - notes
    article:
        form_key: article_additional_data     # default
        unlocalized_keys:
            - template_theme
        localized_keys:
            - notes
    snippet:
        form_key: snippet_additional_data     # default
        unlocalized_keys:
            - template_theme
        localized_keys:
            - notes
```

To use additional data only for Articles (not Pages or Snippets):

```yaml
alengo_content_extra:
    page:
        enabled: false
    snippet:
        enabled: false
```

To disable the Snippet tab entirely:

```yaml
alengo_content_extra:
    snippet:
        enabled: false
```

### Full configuration reference

```yaml
alengo_content_extra:
    page:
        enabled: true
        page_class: Alengo\SuluContentExtraBundle\Entity\Page           # override with custom entity
        entity_class: Alengo\SuluContentExtraBundle\Entity\PageDimensionContent
        form_key: page_additional_data
        tab_title: sulu_admin.app.additional_data
        unlocalized_keys: []
        localized_keys: []
    article:
        enabled: true
        article_class: Alengo\SuluContentExtraBundle\Entity\Article
        entity_class: Alengo\SuluContentExtraBundle\Entity\ArticleDimensionContent
        form_key: article_additional_data
        tab_title: sulu_admin.app.additional_data
        unlocalized_keys: []
        localized_keys: []
    snippet:
        enabled: true
        snippet_class: Alengo\SuluContentExtraBundle\Entity\Snippet
        entity_class: Alengo\SuluContentExtraBundle\Entity\SnippetDimensionContent
        form_key: snippet_additional_data
        tab_title: sulu_admin.app.additional_data
        unlocalized_keys: []
        localized_keys: []
```

## Provided Forms

The bundle does **not** ship default form XML files — the project controls field definitions. Create your own in `config/forms/`:

```
config/forms/page_additional_data.xml
config/forms/article_additional_data.xml
config/forms/snippet_additional_data.xml
```

Example form:

```xml
<?xml version="1.0" ?>
<form xmlns="http://schemas.sulu.io/template/template"
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:schemaLocation="http://schemas.sulu.io/template/template https://github.com/sulu/sulu/blob/2.x/src/Sulu/Bundle/AdminBundle/Resources/schema/form.xsd">

    <key>page_additional_data</key>

    <properties>
        <property name="template_theme" type="select" mandatory="false">
            <meta>
                <title lang="de">Theme</title>
                <title lang="en">Theme</title>
            </meta>
            <params>
                <param name="values" type="collection">
                    <param name="default" type="collection">
                        <param name="title" value="Default"/>
                        <param name="name" value="default"/>
                    </param>
                </param>
            </params>
        </property>
    </properties>
</form>
```

## Conditional fields (`visibleCondition`)

Fields of the additional data form may carry a `visibleCondition`, e.g. to show a field only for some templates.
When the content is saved, the data mapper evaluates these conditions against the submitted data and does not
store keys whose field is hidden — so switching a page to a template without that field does not keep stale
values. Conditions of enclosing sections apply to their fields.

The admin evaluates conditions with [jexl](https://github.com/TomFrost/Jexl), the bundle with Symfony's
ExpressionLanguage, bridging the common jexl constructs:

| Works | Example |
|---|---|
| Comparisons, `&&`, `\|\|`, `!`, `in` | `template == "special" && !hideTeaser` |
| The `length` transform | `blocks\|length > 1` |
| Dotted paths into nested data | `ext.excerpt.title == ""` |

**Known limitation:** conditions that still cannot be evaluated — other jexl transforms, or variables the
submitted data does not carry, such as the admin-only `__webspace` and `__parent` — are treated as *visible*,
so their field is stored as if no condition existed. The bundle never drops a value it cannot reason about;
it may only keep one the admin hides.

## Review workflow (Sulu 3.1)

On Sulu 3.1 the Additional Data tab carries the core review toolbar, so editors request a review or publish
from it like from any other tab, and it is locked while a request is open.

The bundle also adds the pre-validator `additional_data_required`, the counterpart of Sulu's `seo_required`
and `excerpt_required`. It refuses "request for review" and "publish" while one of the listed fields is empty:

```yaml
# config/packages/sulu_content.yaml
sulu_content:
    request_workflows:
        default:
            pre_validators:
                additional_data_required: { fields: [template_theme, notes] }
```

Unlocalized and localized keys can be mixed. A field counts as missing when it is null, an empty or
whitespace-only string or an empty array; `false` and `0` are values. The editor sees the field names in the
message "Additional data fields are still missing: …".

## Translations

The bundle ships admin translations (de, en, it) for the default tab title `sulu_admin.app.additional_data`
and the pre-validator messages. Override any of them in the project's `translations/`.

## Additional data in smart content

Smart content items can pick single additional data fields through the `properties` param of their
`smart_content` property:

```xml
<param name="properties" type="collection">
    <param name="theme" value="additionalData.template_theme"/>
</param>
```

The item then carries the value as `item.theme`. Each item gets exactly the fields its own `properties` request,
also when the same page or article appears in several smart contents with different mappings.

## Provided Models

| Class | Purpose |
|---|---|
| `Entity\Page` | Concrete Doctrine entity (`pa_pages`) extending Sulu's `Page` |
| `Entity\PageDimensionContent` | Dimension content with `additionalData` JSON column (`pa_page_dimension_contents`) |
| `Entity\Article` | Concrete Doctrine entity (`ar_articles`) extending Sulu's `Article` |
| `Entity\ArticleDimensionContent` | Dimension content with `additionalData` JSON column (`ar_article_dimension_contents`) |
| `Entity\Snippet` | Concrete Doctrine entity (`sn_snippets`) extending Sulu's `Snippet` |
| `Entity\SnippetDimensionContent` | Dimension content with `additionalData` JSON column (`sn_snippet_dimension_contents`) |
| `Model\AdditionalDataInterface` | Interface implemented by all dimension content entities |

## Navigation Link Markers

`NavigationLinkEnhancer` decorates Sulu's `sulu_page.page_link_dimension_content_enhancer`. When a page is of link type, it adds two markers to the template data:

| Field | Type | Description |
|---|---|---|
| `sourceLink` | `bool` | `true` when the page is a link page (redirects to another page or URL) |
| `sourceLinkResolved` | `bool` | `true` when the link target could be resolved, `false` when it is gone (deleted page, missing translation, unknown media, ...) |
| `sourceUuid` | `string` | UUID of the original link-type page |

`NavigationLinkTypeResolver` exposes these fields to templates via the `navlink` content section — bypassing Sulu's `TemplateResolver` which would otherwise drop unknown keys.

Both services are registered automatically.

## `auto_generate_proxy_classes: false` in production

The bundle registers Doctrine's `resolve_target_entities` for all enabled entity overrides. This replaces Sulu's original class references in association mappings at container build time, so Doctrine never needs to generate proxies for the original Sulu classes at runtime.

This allows setting `auto_generate_proxy_classes: false` in production (recommended):

```yaml
# config/packages/prod/doctrine.yaml
when@prod:
    doctrine:
        orm:
            auto_generate_proxy_classes: false
            proxy_dir: '%kernel.build_dir%/doctrine/orm/Proxies'
```

Proxies are generated during `cache:warmup` as part of the normal deploy process.

## Development

```bash
composer update --with sulu/sulu:3.1.x-dev   # until Sulu 3.1 is released
composer lint                                 # PHP-CS-Fixer, Rector, PHPStan (level max), PHPUnit
composer test -- --testsuite functional       # boots tests/Application, a minimal Sulu app with the bundle
composer fix                                  # apply Rector and PHP-CS-Fixer
```

CI (`.github/workflows/ci.yml`) runs the unit and functional tests against Sulu 3.0.8 with the lowest
dependencies (Symfony 6.4), the newest 3.0.x (Symfony 7.4) and 3.1 (Symfony 8), and the full lint against 3.1.
The functional tests boot the admin and website kernel without a database.
