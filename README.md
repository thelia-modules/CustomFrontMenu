## English version

# Custom Front Menu

This module lets you create dynamic menus.

## Installation

### Prerequisites

The OpenApi module must be activated to enable CustomFrontMenu.

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is CustomFrontMenu.
* Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/custom-front-menu-module:~1.0
```

## Usage

In back-office, the configuration page allows you to configure the module.

You can select a specific menu to modify it.

Menu items can be added, deleted, renamed or moved. Translations can be made directly from the menu item edit screen.

Each menu item is linked to a URL. This can be entered directly or associated with a `brand`, `category`, `content`, `folder` or `product`.

In front-office, each menu is rendered by calling the `custom_front_menu()` Twig function
in your theme. The module ships markup only: style the `.cfm-menu` classes from your theme.

## Example

```twig
{{ custom_front_menu(388) }}
```

The visitor's locale is used by default. Pass a second argument to force one:

```twig
{{ custom_front_menu(388, 'fr_FR') }}
```

Entries whose target is deleted or unpublished are dropped from the rendered menu.

## API

The composed tree is also readable over HTTP, read-only:

```
GET /api/front/custom-front-menus/{id}
```

It answers `{id, items}`, each item being `{id, title, href, children}`. It replaces the
`open_api/custom-front-menu/{id}` endpoint of the 1.x line, which relied on the OpenApi
module that Thelia 3 no longer ships.

_________________

## Version française

# Custom Front Menu

Ce module vous permet de créer des menus dynamiques.

## Installation

### Prérequis

Le module OpenApi doit être activé pour utiliser CustomFrontMenu.

### Manuellement

* Copiez le module dans le répertoire ``<thelia_root>/local/modules/`` et assurez-vous que le nom du module est CustomFrontMenu.
* Activez-le dans votre panneau d'administration thelia.

### Composer

Ajoutez-le dans votre fichier principal thelia composer.json

```
composer require thelia/custom-front-menu-module:~1.0
```

## Utilisation

Dans le back-office, la page de configuration vous permet de configurer le module.

Vous pouvez sélectionner un menu spécifique pour le modifier.

Les éléments du menu peuvent être ajoutés, supprimés, renommés ou déplacés. Les traductions peuvent être effectuées directement à partir de l'écran d'édition des éléments du menu.

Chaque élément du menu est lié à une URL. Celle-ci peut être saisie directement ou associée à un `brand`, `category`, `content`, `folder` ou `product`.

Dans le front-office, chaque menu est rendu en appelant la fonction Twig `custom_front_menu()` dans votre thème.

Le module ne fournit que le markup : les classes `.cfm-menu` sont à styler depuis votre thème.

## Exemple

```twig
{{ custom_front_menu(388) }}
```

La locale du visiteur est utilisée par défaut. Un second argument permet de la forcer :

```twig
{{ custom_front_menu(388, 'fr_FR') }}
```
