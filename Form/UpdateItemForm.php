<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 09/10/2019
 * Time: 10:50
 */

namespace CustomFrontMenu\Form;


use CustomFrontMenu\CustomFrontMenu;
use CustomFrontMenu\Model\CustomFrontMenuItemsQuery;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Type\IntType;

class UpdateItemForm extends BaseForm
{
    protected function buildForm()
    {

        $this->formBuilder
            ->add('url', TextType::class,[
                'required' => false,
                'label' => Translator::getInstance()->trans('URL', [], CustomFrontMenu::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'url'
                ]
            ])
            ->add('text', TextType::class,[
                'required' => true,
                'label' => Translator::getInstance()->trans('Text link', [], CustomFrontMenu::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'text'
                ]
            ]);

    }

    public function getName()
    {
        return "customfrontmenu_update-item";
    }
}