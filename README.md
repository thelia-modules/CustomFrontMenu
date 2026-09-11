## English version

# Custom Front Menu

This module lets you create dynamic menus.

## Installation

### Prerequisites

Thelia 3.0 or later. The menu API is served by the core API Platform stack: no
additional module is required.

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

In front-office, the `custom_front_menu()` Twig function answers the composed tree. It
returns **data, not markup**: a navigation is where your layout, breakpoints and
interaction live, so the module hands you the nodes and you write the elements you want.

A menu is addressed by its **code**, not by its id: the id comes from an autoincrement and
differs from one installation to the next, so a theme built on it breaks on the next shop.
The code is yours to choose when you create the menu, and the composition screen shows the
exact call to copy.

## Example

```twig
{% for item in custom_front_menu('header') %}
    <a href="{{ item.href }}">{{ item.title }}</a>

    {% for child in item.children %}
        <a href="{{ child.href }}">{{ child.title }}</a>
    {% endfor %}
{% endfor %}
```

Each node is `{id, title, href, children}`, nested to any depth. An entry with no target
has an empty `href`: it is a label, and it is yours to render as one.

`href` is always `http(s)`, site-relative, or empty — filtered on the way out, so an
address entered before this rule existed cannot reach your template either. `title` is
plain text that your own template must escape, as Twig does by default.

The visitor's locale is used by default. Pass a second argument to force one:

```twig
{% for item in custom_front_menu('header', 'fr_FR') %}
```

An unknown code answers an empty list rather than raising: a menu deleted in the
back-office must not take the storefront down.

Entries whose target is deleted or unpublished are dropped from the rendered menu.

## API

The composed tree is also readable over HTTP, read-only:

```
GET /api/front/custom-front-menus/{code}
```

It answers `{code, items}`, each item being `{id, title, href, children}`. It replaces the
`open_api/custom-front-menu/{id}` endpoint of the 1.x line, which relied on the OpenApi
module that Thelia 3 no longer ships.

_________________

## Version française

# Custom Front Menu

Ce module vous permet de créer des menus dynamiques.

## Installation

### Prérequis

Thelia 3.0 ou supérieur. L'API du menu est servie par API Platform, fourni par le
cœur : aucun module supplémentaire n'est requis.

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

Dans le front-office, la fonction Twig `custom_front_menu()` rend l'arbre composé. Elle
rend **des données, pas du markup** : une navigation est l'endroit où vivent la mise en
page, les points de rupture et les interactions du thème, donc le module donne les nœuds et
l'intégrateur écrit les éléments qu'il veut.

Un menu s'appelle par son **code**, pas par son identifiant : l'identifiant vient d'un
auto-incrément et change d'une installation à l'autre, donc un thème qui s'appuie dessus
casse sur la boutique suivante. Le code est choisi à la création du menu, et l'écran de
composition affiche l'appel exact à recopier.

## Exemple

```twig
{% for item in custom_front_menu('header') %}
    <a href="{{ item.href }}">{{ item.title }}</a>

    {% for child in item.children %}
        <a href="{{ child.href }}">{{ child.title }}</a>
    {% endfor %}
{% endfor %}
```

Chaque nœud est `{id, title, href, children}`, imbriqué à toute profondeur. Une entrée sans
cible a un `href` vide : c'est un libellé, à rendre comme tel.

`href` est toujours `http(s)`, relatif au site, ou vide — filtré à la sortie, donc une
adresse saisie avant l'existence de cette règle n'atteint pas non plus votre gabarit.
`title` est du texte brut, que votre gabarit doit échapper, comme Twig le fait par défaut.

La locale du visiteur est utilisée par défaut. Un second argument permet de la forcer :

```twig
{% for item in custom_front_menu('header', 'fr_FR') %}
```

Un code inconnu rend une liste vide, sans lever d'exception : un menu supprimé au
back-office ne doit pas emporter la boutique.
