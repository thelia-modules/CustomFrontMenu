<?php

use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class CustomFrontMenuPlugin extends AbstractSmartyPlugin
{
    public function getPluginDescriptors(): array
    {
        return [
            new SmartyPluginDescriptor(
                'function',
                'CustomFrontMenu',
                 $this,
                'CustomFrontMenu'
            ),
        ];
    }
    
    public function CustomFrontMenu($params): void // dependency injection of the custom front menu service
    {
        echo $params['menu_id'];
    }
}