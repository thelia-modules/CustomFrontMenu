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

Each menu item is linked to a URL. This can be entered directly or associated with a `brand`, `category`, `content`, `folder` or `product`, or a `page` while the Page module is active.

An item associated with an object and left without a label takes the title of that object, in
each language, read when the menu is displayed: renaming the category renames the item. Typing a
label replaces it; emptying the label brings the object's title back.

An item can be set to open in a new tab. Every change made on the composition screen is recorded
in the administration log.

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
    <a href="{{ item.href }}"{% if item.newTab %} target="_blank" rel="noopener noreferrer"{% endif %}>{{ item.title }}</a>

    {% for child in item.children %}
        <a href="{{ child.href }}">{{ child.title }}</a>
    {% endfor %}
{% endfor %}
```

Each node is `{id, title, href, newTab, children}`, nested to any depth. An entry with no
target has an empty `href`: it is a label, and it is yours to render as one. `newTab` is a
boolean, always false on a label.

`href` is always `http(s)`, site-relative, or empty — filtered on the way out, so an
address entered before this rule existed cannot reach your template either. `title` is
plain text that your own template must escape, as Twig does by default.

The visitor's locale is used by default. Pass a second argument to force one:

```twig
{% for item in custom_front_menu('header', 'fr_FR') %}
```

An unknown code answers an empty list rather than raising: a menu deleted in the
back-office must not take the storefront down.

Entries whose target is deleted or unpublished are dropped from the rendered menu. So are
entries pointing at a page once the Page module is deactivated.

## Data model

Menus and their entries share one table, `custom_front_menu_item`, as a single nested set: the
root node holds the menus, each menu is a level-1 node carrying its `code`, and its entries are
the nodes below it. This is the storage of the 1.x line, kept so that upgrading a shop only adds
the `code` column and leaves every existing row, id and translation where it was. A separate
menu table would have meant moving every 1.x menu into it on upgrade, for no gain on reads: the
tree of one menu is a single range query on the nested set either way.

## API

The composed tree is also readable over HTTP, read-only:

```
GET /api/front/custom-front-menus/{code}
```

It answers `{code, items}`, each item being `{id, title, href, newTab, children}`. Pass
`?locale=fr_FR` to pick the language, as for the other front endpoints; without it, or with a
language that is not active, the shop's language is used. It replaces the
`open_api/custom-front-menu/{id}` endpoint of the 1.x line, which relied on the OpenApi
module that Thelia 3 no longer ships.

## Tests

The tests build on the Thelia test cases (`Thelia\Test\IntegrationTestCase`, `ApiTestCase`,
`WebIntegrationTestCase`), so they run from the root of a Thelia 3 project whose test database
is prepared, with the module active:

```
php bin/test-prepare
APP_ENV=test vendor/bin/phpunit -c phpunit.xml.dist local/modules/CustomFrontMenu/Tests
```

Adjust the path when the module is installed under `vendor/thelia/modules/`. An environment
that exports `DATABASE_*` variables to the shell (DDEV does) overrides `.env.test`: unset them
first, or the tests run against the development database.

`Unit/` needs no database. `Integration/` covers the services (composition, tree resolution,
back-office presentation, Twig function), `Api/` the front endpoint, `Http/BackOffice/` the
composition screens with their permission and CSRF checks.

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

Chaque élément du menu est lié à une URL. Celle-ci peut être saisie directement ou associée à un `brand`, `category`, `content`, `folder` ou `product`, ou à une `page` tant que le module Page est actif.

Un élément associé à un objet et laissé sans libellé prend le titre de cet objet, langue par
langue, lu à l'affichage du menu : renommer la catégorie renomme l'élément. Saisir un libellé le
remplace ; vider le libellé fait revenir le titre de l'objet.

Un élément peut s'ouvrir dans un nouvel onglet. Chaque modification faite sur l'écran de
composition est inscrite au journal d'administration.

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
    <a href="{{ item.href }}"{% if item.newTab %} target="_blank" rel="noopener noreferrer"{% endif %}>{{ item.title }}</a>

    {% for child in item.children %}
        <a href="{{ child.href }}">{{ child.title }}</a>
    {% endfor %}
{% endfor %}
```

Chaque nœud est `{id, title, href, newTab, children}`, imbriqué à toute profondeur. Une entrée
sans cible a un `href` vide : c'est un libellé, à rendre comme tel. `newTab` est un booléen,
toujours faux sur un libellé.

`href` est toujours `http(s)`, relatif au site, ou vide — filtré à la sortie, donc une
adresse saisie avant l'existence de cette règle n'atteint pas non plus votre gabarit.
`title` est du texte brut, que votre gabarit doit échapper, comme Twig le fait par défaut.

La locale du visiteur est utilisée par défaut. Un second argument permet de la forcer :

```twig
{% for item in custom_front_menu('header', 'fr_FR') %}
```

Un code inconnu rend une liste vide, sans lever d'exception : un menu supprimé au
back-office ne doit pas emporter la boutique.

Les entrées dont la cible est supprimée ou hors ligne sont retirées du menu rendu, de même que
les entrées vers une page une fois le module Page désactivé.

## Modèle de données

Les menus et leurs entrées partagent une table, `custom_front_menu_item`, sous la forme d'un
seul arbre imbriqué (nested set) : le nœud racine porte les menus, chaque menu est un nœud de
niveau 1 porteur de son `code`, et ses entrées sont les nœuds en dessous. C'est le stockage de
la version 1.x, conservé pour qu'une mise à jour n'ajoute que la colonne `code` et laisse en
place chaque ligne, identifiant et traduction existants. Une table de menus séparée aurait
obligé à y déplacer chaque menu 1.x à la mise à jour, sans rien gagner en lecture : l'arbre d'un
menu reste une seule requête par intervalle sur l'arbre imbriqué.

## Tests

Les tests s'appuient sur les classes de test de Thelia (`Thelia\Test\IntegrationTestCase`,
`ApiTestCase`, `WebIntegrationTestCase`) : ils se lancent depuis la racine d'un projet Thelia 3
dont la base de test est préparée, module actif :

```
php bin/test-prepare
APP_ENV=test vendor/bin/phpunit -c phpunit.xml.dist local/modules/CustomFrontMenu/Tests
```

Adapter le chemin quand le module est installé sous `vendor/thelia/modules/`. Un
environnement qui exporte des variables `DATABASE_*` dans le shell (c'est le cas de DDEV)
prend le pas sur `.env.test` : les retirer d'abord, sinon les tests tournent sur la base de
développement.

`Unit/` se passe de base de données. `Integration/` couvre les services (composition,
résolution de l'arbre, présentation au back-office, fonction Twig), `Api/` le point d'accès
front, `Http/BackOffice/` les écrans de composition avec leurs contrôles de droits et de jeton.
