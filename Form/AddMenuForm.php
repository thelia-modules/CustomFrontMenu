<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 08/10/2019
 * Time: 11:51
 */

namespace CustomFrontMenu\Form;


use CustomFrontMenu\CustomFrontMenu;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\LangQuery;

class AddMenuForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add('menu_name', TextType::class,[
                'required' => true,
                'label' => Translator::getInstance()->trans('Menu name', [], CustomFrontMenu::DOMAIN_NAME),
                'pattern' => "[^' ']+",
                'label_attr' => [
                    'for' => 'menu_name'
                ]
            ]);
    }

    public function getName()
    {
        return 'customfrontmenu_add-menu';
    }

    public function getLanguages(){
        $langs = LangQuery::create()
            ->filterByActive(1)
            ->find();
        $result = [];
        foreach ($langs as $lang){
            $result[$lang->getLocale()] = $lang->getTitle();
        }
        return $result;
    }
}