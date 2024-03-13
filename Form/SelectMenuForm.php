<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 08/10/2019
 * Time: 10:28
 */

namespace CustomFrontMenu\Form;



use CustomFrontMenu\CustomFrontMenu;
use CustomFrontMenu\Model\CustomFrontMenu as CustomMenu;
use CustomFrontMenu\Model\CustomFrontMenuQuery;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\LangQuery;

class SelectMenuForm extends BaseForm
{
    protected function buildForm()
    {
        $menuObjects = CustomFrontMenuQuery::create()->find();
        $menus[] = $this->translator->trans('Select a menu', [], CustomFrontMenu::DOMAIN_NAME);
        /** @var CustomMenu $menu */
        foreach ($menuObjects as $menu){
           $menus[$menu->getId()] = $menu->getMenuName();
        }

        $this->formBuilder
            ->add('select_menu', ChoiceType::class,[
                'required' => true,
                'choices' => $menus,
            ]);
    }

    public function getName()
    {
        return 'customfrontmenu_select-menu';
    }

    protected function getLangTitle($local)
    {
        $lang = LangQuery::create()
            ->findOneByLocale($local);

        return $lang->getTitle();
    }

}