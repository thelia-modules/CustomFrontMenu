# Custom Front Menu

This module lets you create dynamic menus.

## Installation

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

In front-office, each menu should be called by a smarty plugin manually.

To override the css file, you can replace or modify : `templates/frontOffice/default/assets/css/customFrontMenu.css.html`.

## Example

```smarty
{CustomFrontMenuPlugin menu_id=388}
```
