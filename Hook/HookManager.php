<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 07/10/2019
 * Time: 13:00
 */

namespace CustomFrontMenu\Hook;


use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class HookManager extends BaseHook
{
    public function onMainTopMenu(HookRenderEvent $event)
    {
        $event->add(
            $this->render('customFrontMenu/hook/main-in-top-menu-items.html', [])
        );
    }

    public function insertionJs(HookRenderEvent $event){
        $configJs = $this->addJS('customFrontMenu/assets/js/custom_front_menu.js');
        $event->add($configJs);

        $configCss = $this->addCSS('customFrontMenu/assets/css/custom_front_menu.css');
        $event->add($configCss);
    }
}