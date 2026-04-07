# SuluContentExtraBundle

Extends [Sulu CMS](https://sulu.io/) 3.x Pages and Articles with configurable additional data, navigation link markers, and Doctrine ORM 3.x compatibility fixes for mapped-superclass entities.

## Features

- **Additional Data tab** — auto-registered for Pages and Articles via `PreviewFormViewBuilder`
- **Built-in entities** — concrete `Page`, `PageDimensionContent`, `Article`, `ArticleDimensionContent` extending Sulu's base classes; no project entities required
- **Configurable field mapping** — declare which form fields go to the unlocalized vs. localized dimension content via bundle config
- **Zero-config entity registration** — `sulu_page` / `sulu_article` objects are auto-configured via `PrependExtensionInterface`
- **Navigation link markers** — `NavigationLinkEnhancer` adds `sourceLink`/`sourceUuid` markers to link-type pages; `NavigationLinkTypeResolver` exposes them to templates
- **Doctrine compatibility** — `SuluPageAwareTreeListener`, `SafeTreeObjectHydrator`, `InheritedAssociationDeclaredFixerSubscriber` included; no separate bundle needed

## Requirements

- PHP 8.2+
- Sulu CMS ~3.0
- Symfony 7.x

## Installation

```bash
composer require alengo/sulu-content-extra-bundle
```

Register the bundle in `config/bundles.php`:

```php
Alengo\SuluContentExtraBundle\AlengoContentExtraBundle::class => ['all' => true],
```

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
```

To use additional data only for Articles (not Pages):

```yaml
alengo_content_extra:
    page:
        enabled: false
```

To disable the Article tab entirely:

```yaml
alengo_content_extra:
    article:
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
```

## Provided Forms

The bundle does **not** ship default form XML files — the project controls field definitions. Create your own in `config/forms/`:

```
config/forms/page_additional_data.xml
config/forms/article_additional_data.xml
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

## Provided Models

| Class | Purpose |
|---|---|
| `Entity\Page` | Concrete Doctrine entity (`pa_pages`) extending Sulu's `Page` |
| `Entity\PageDimensionContent` | Dimension content with `additionalData` JSON column (`pa_page_dimension_contents`) |
| `Entity\Article` | Concrete Doctrine entity (`ar_articles`) extending Sulu's `Article` |
| `Entity\ArticleDimensionContent` | Dimension content with `additionalData` JSON column (`ar_article_dimension_contents`) |
| `Model\AdditionalDataInterface` | Interface implemented by both dimension content entities |

## Navigation Link Markers

`NavigationLinkEnhancer` decorates Sulu's `sulu_page.page_link_dimension_content_enhancer`. When a page is of link type, it adds two markers to the template data:

| Field | Type | Description |
|---|---|---|
| `sourceLink` | `bool` | `true` when the page redirects to another page or URL |
| `sourceUuid` | `string` | UUID of the original link-type page |

`NavigationLinkTypeResolver` exposes these fields to templates via the `navlink` content section — bypassing Sulu's `TemplateResolver` which would otherwise drop unknown keys.

Both services are registered automatically.

## Doctrine Compatibility

The bundle ships fixes for Doctrine ORM 3.x + Gedmo tree extension when extending Sulu's mapped-superclass entities:

| Class | Purpose |
|---|---|
| `Doctrine\Tree\SuluPageAwareTreeListener` | Fixes Gedmo's `TreeListener` for Sulu's mapped-superclass `Page` |
| `Doctrine\Hydrator\SafeTreeObjectHydrator` | Fixes `TreeObjectHydrator::getChildrenField()` for Doctrine ORM 3.x |
| `Doctrine\EventSubscriber\InheritedAssociationDeclaredFixerSubscriber` | Fixes null `declared` on inherited association mappings |

These are registered automatically — no separate bundle or configuration needed.

### `auto_generate_proxy_classes: false` in production

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
