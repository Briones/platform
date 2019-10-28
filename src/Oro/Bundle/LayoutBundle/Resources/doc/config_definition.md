# Config Definition

## Table of Contents


* [Assets](#assets)
* [Images](#images)
* [JS Modules Definition](#js-modules-definition)
* [Page Templates](#page-templates)

## Overview

If you want to use a different configuration for your **theme**, such as **assets**, **images**, **jsmodules** or **page_templates**, you need to put it to the `layout/{theme_name}/config` folder.

## Assets

### Configuration

Assets configuration file should be placed in the `layout/{theme_name}/config` folder and named `assets.yml`, for example `DemoBundle/Resources/views/layouts/first_theme/config/assets.yml`

**Example:**

```yaml
#DemoBundle/Resources/views/layouts/first_theme/config/assets.yml
styles:
    inputs:
        - 'bundles/demo/css/bootstrap.min.css'
        - 'bundles/demo/css/font-awesome.min.css'
    output: 'css/layout/first_theme/styles.css'
```

```yml
#DemoBundle/Resources/views/layouts/first_theme/page/layout.yml
layout:
    actions:
    ...
    - '@add':
        id: styles
        parentId: head
        blockType: style
        options:
            src: '=data["asset"].getUrl(data["theme"].getStylesOutput(context["theme"]))'
    ...
```

**Example of how to create 2 or more outputs:**

```yaml
#DemoBundle/Resources/views/layouts/first_theme/config/assets.yml
libraries:
    inputs:
        - 'bundles/demo/css/bootstrap.min.css'
        - 'bundles/demo/css/font-awesome.min.css'
    output: 'css/layout/first_theme/lib.css'

own_styles:
    inputs:
        - 'bundles/demo/css/custom.min.css'
        - 'bundles/demo/css/additional.min.css'
    output: 'css/layout/first_theme/styles.css'
```

```yml
#DemoBundle/Resources/views/layouts/first_theme/page/layout.yml
layout:
    actions:
    ...
    - '@add':
        id: libraries
        parentId: head
        blockType: style
        options:
            src: '=data["asset"].getUrl(data["theme"].getStylesOutput(context["theme"], "libraries"))'
    - '@add':
        id: own_styles
        parentId: head
        blockType: style
        options:
            src: '=data["asset"].getUrl(data["theme"].getStylesOutput(context["theme"], "own_styles"))'
    ...
```

## Images

### Configuration

Images configuration file should be placed in the `layout/{theme_name}/config` folder and named `images.yml`, for example `DemoBundle/Resources/views/layouts/first_theme/config/images.yml`

**Example:**

```yaml
#DemoBundle/Resources/views/layouts/first_theme/config/images.yml
types:
    main:
        label: orob2b.product.productimage.type.main.label
        dimensions: ~
        max_number: 1
    listing:
        label: orob2b.product.productimage.type.listing.label
        dimensions: ~
        max_number: 1
    additional:
        label: orob2b.product.productimage.type.additional.label
        dimensions: ~
        max_number: ~
```

## JS Modules Definition

### Configuration

Bundle specific options are defined inside ```jsmodules.yml``` file. For admin console it has to be placed in ```%BundleName%\Resources\config``` folder and `%BundleName%\Resources\views\layouts\{theme_name}\config` folder for frontstore themes.
It can have six sections ```shim```, ```map```, ```app-modules```, ```dynamic-imports```, ```configs``` and ```aliases```.
Each bundle's JS module that isn't mentioned in any other JS module and is imported dynamically in runtime (e.g. using option from twig) has to be defined in ```dynamic-imports``` section to be included in webpack build. That section has subsection with build chunk names, it manages to place module to suitable chunk. Also each JS module that has to be configurable has to be added to ```configs``` section where key is module name and value is empty object

```yaml    
    aliases:
        jquery$: npmassets/jquery/dist/jquery
        jquery-ui$: oroui/lib/jquery-ui
        underscore$: npmassets/underscore/underscore
    app-modules:
        - oroui/js/app/modules/init-layout
    configs:
        oroui/js/app: {}
    dynamic-imports:
        oroui:
            - jquery
            - oroui/js/app/components/view-component
    map:
        "*":
            jquery: oroui/js/extend/jquery
        oroui/js/extend/jquery:
            jquery: jquery
    shim:
        jquery:
            expose:
                - $
                - jQuery
        jquery.form:
            imports:
                - jQuery=jquery
```

## Page Templates

A **page_template** is a collection of files that expand the visual presentation for one or more route names.

### Configuration

The page templates configuration file should be placed in the `layout/{theme_name}/config` folder and named `page_templates.yml`, 
for example `DemoBundle/Resources/views/layouts/first_theme/config/page_templates.yml`.
All page template **layout updates** should be stored in the `layout/{theme_name}/{route_name}/page_template/{page_template_KEY}/` folder, 
for example `DemoBundle/Resources/views/layouts/first_theme/demo_first_route_name/page_template/custom/layout.yml`.

#### Additional Configuration Reference

| Option | Description | Required |
|------- |-------------|----------|
| `label` | Label will be displayed in the page template management UI. | yes |
| `route_name` | Route name identifier, used in the path where **layout updates** stored. | yes |
| `key` | Key used in the path where **layout updates** are stored. | yes |
| `description` | Description will be displayed in the page template management UI. | no |
| `screenshot` | Screenshot for preview. This will be displayed in the page template management UI. | no |
| `enabled` | Enable/Disable page template | no |

**Example:**

```yaml
#DemoBundle/Resources/views/layouts/first_theme/config/page_templates.yml
templates:
    -
        label: Custom page template
        description: Custom page template description
        route_name: demo_first_route_name
        key: custom
    -
        label: Additional page template
        description: Additional page template description
        route_name: demo_first_route_name
        key: additional
    -
        label: Additional page template
        description: Additional page template description
        route_name: demo_second_route_name
        key: additional
titles:
    demo_first_route_name: First route name title
    demo_second_route_name: Second route name title
```

_NOTICE:_ Be aware that page templates inherit parent themes. To override the existing page template, add the **layout update** file to the page template path in your child theme. For example, if `first_theme` is the parent theme of `second_theme`, put the page template into the
`DemoBundle/Resources/views/layouts/second_theme/demo_first_route_name/page_template/custom/layout.yml`.

To disable some page templates, add `enabled: false`.
