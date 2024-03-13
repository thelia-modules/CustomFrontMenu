# Custom Front Menu

This module allows you to create dynamic menu.

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

To get the items of you menu you can use the loop `custom_front_menu_items` or you can get a json file with the route `/admin/customfrontmenu/get/menu/{menu_name}?locale={locale}`.


## Loop

To retrieve your menu you can use the loop `custom_front_menu_items`.

### Input arguments

|Argument |Description |
|---      |--- |
|**id** | The id of an item |
|**menu_id** | The id of a menu, return the items of this menu |
|**menu_name** | The name of a menu, return the items of this menu |
|**depth** | The depth of items, filter by depth |
|**parent_id** | The Id of a parent, filter by parent |
|**locale** | Locale of a menu (fr_FR, en_US ...), filter by language |
|**lang_id** | Id of a language, filter by language |
|**child_count** | If true return the number of children |
|**in_order** | Return all items in order |
|**possible_parent** | The id of an item, return the possible parents of this item (an item can't have his children or himself as a parent) |

### Output arguments

|Variable   |Description |
|---        |--- |
|$ID   | Id of this item |
|$TEXT_LINK   | Text to display |
|$TYPE   | Type of the page item (category, product, folder, document, brand, url) |
|$TYPE_ID   | Id of the page |
|$TYPE_NAME   | name of the category, product, folder, document or brand |
|$URL   | Url of this item |
|$PARENT   | The id of the parent of this item |
|$DEPTH   | The number of parent |
|$MENU_ID   | The id of the menu |
|$POSITION   | The position of this item |
|$CHILD_COUNT   | The number of direct children of this item |

### Exemple
    
    <div class="collapse navbar-collapse" id="navbar-primary">
        {loop name="my-menu-loop" type="custom_front_menu_items" menu_name="my-menu" depth=0 locale=$locale child_count=true}
            {if $CHILD_COUNT > 0}
                <li class="dropdown">
                    <a href="{$URL}" class="dropdown-toggle">{$TEXT_LINK}</a>
                    <ul class="dropdown-menu" role="menu">
                        {loop type="custom_front_menu_items" name="sub-link" parent_id="{$ID}"}
                            <li><a href="{$URL}">{$TEXT_LINK}</a></li>    
                        {/loop}
                    </ul>
                </li>
            {else}
                <li><a href="{$URL}">{$TEXT_LINK}</a></li>
            {/if}   
        {/loop}
    </div>


