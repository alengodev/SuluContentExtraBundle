# SuluContentExtraBundle

Adds a configurable **Additional Data** tab to [Sulu CMS](https://sulu.io/) 3.x Pages and Articles. Custom fields are stored as a JSON column on the dimension content entity — no extra tables, no extra API routes.

Also ships Doctrine ORM 3.x compatibility fixes required when extending Sulu's `Page` and `Article` mapped-superclass entities.

## Features

- **Additional Data tab** — auto-registered for Pages and Articles via `PreviewFormViewBuilder`
- **Built-in entities** — concrete `Page`, `PageDimensionContent`, `Article`, `ArticleDimensionContent` extending Sulu's base classes; no project entities required
- **Configurable field mapping** — declare which form fields go to the unlocalized vs. localized dimension content via bundle config
- **Zero-config entity registration** — `sulu_page` / `sulu_article` objects are auto-configured via `PrependExtensionInterface`
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

## Doctrine Compatibility

The bundle ships fixes for Doctrine ORM 3.x + Gedmo tree extension when extending Sulu's mapped-superclass entities:

| Class | Purpose |
|---|---|
| `Doctrine\Tree\SuluPageAwareTreeListener` | Fixes Gedmo's `TreeListener` for Sulu's mapped-superclass `Page` |
| `Doctrine\Hydrator\SafeTreeObjectHydrator` | Fixes `TreeObjectHydrator::getChildrenField()` for Doctrine ORM 3.x |
| `Doctrine\EventSubscriber\InheritedAssociationDeclaredFixerSubscriber` | Fixes null `declared` on inherited association mappings |

These are registered automatically — no separate bundle or configuration needed.
