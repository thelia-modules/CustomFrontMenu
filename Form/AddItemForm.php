<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 07/10/2019
 * Time: 13:58
 */

namespace CustomFrontMenu\Form;


use CustomFrontMenu\CustomFrontMenu;
use CustomFrontMenu\Model\CustomFrontMenuItemsQuery;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\ProductQuery;

class AddItemForm extends BaseForm
{
    protected function buildForm()
    {
        $local = $this->getRequest()->getSession()->getLang()->getLocale();

        $this->formBuilder
            ->add('type',ChoiceType::class,[
                'choices' => [
                    "category" => Translator::getInstance()->trans('Category', [], CustomFrontMenu::DOMAIN_NAME),
                    "product" => Translator::getInstance()->trans('Product', [], CustomFrontMenu::DOMAIN_NAME),
                    "folder" => Translator::getInstance()->trans('Folder', [], CustomFrontMenu::DOMAIN_NAME),
                    "content" => Translator::getInstance()->trans('Content', [], CustomFrontMenu::DOMAIN_NAME),
                    "brand" => Translator::getInstance()->trans('Brand', [], CustomFrontMenu::DOMAIN_NAME),
                    "url" => Translator::getInstance()->trans('New link', [], CustomFrontMenu::DOMAIN_NAME),
                ],
                'required' => false,
                'label' => Translator::getInstance()->trans('Link to', [], CustomFrontMenu::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'type'
                ]
            ])
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
            ])
            ->add('categories', ChoiceType::class,[
                'required' => false,
                'choices' => $this->getCategories($local),
                'label' => Translator::getInstance()->trans('Page', [], CustomFrontMenu::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'categories'
                ]
            ])
            ->add('products', ChoiceType::class,[
                'required' => false,
                'choices' => $this->getProducts($local),
                'label' => Translator::getInstance()->trans('Page', [], CustomFrontMenu::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'products'
                ]
            ])
            ->add('contents', ChoiceType::class,[
                'required' => false,
                'choices' => $this->getContents($local),
                'label' => Translator::getInstance()->trans('Page', [], CustomFrontMenu::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'contents'
                ]
            ])
            ->add('folders', ChoiceType::class,[
                'required' => false,
                'choices' => $this->getFolders($local),
                'label' => Translator::getInstance()->trans('Page', [], CustomFrontMenu::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'folders'
                ]
            ])
            ->add('brands', ChoiceType::class,[
                'required' => false,
                'choices' => $this->getBrands($local),
                'label' => Translator::getInstance()->trans('Page', [], CustomFrontMenu::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'brands'
                ]
            ])
            ->add('parent', ChoiceType::class,[
                'required' => false,
                'choices' => $this->getParents(),
                'label' => Translator::getInstance()->trans('Parent', [], CustomFrontMenu::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'parent'
                ]
            ]);
    }

    public function getName()
    {
        return "customfrontmenu_add-item";
    }

    protected function getCategories($local)
    {
        $categories = CategoryQuery::create()->filterByVisible(1)->find();

        $result = [];
        foreach ($categories as $category){
            $category->setLocale($local);
            $result[$category->getId()] = $category->getTitle();
        }
        return $result;
    }

    protected function getProducts($local)
    {
        $products = ProductQuery::create()->filterByVisible(1)->find();

        $result = [];
        foreach ($products as $product){
            $product->setLocale($local);
            $result[$product->getId()] = $product->getTitle();
        }
        return $result;
    }

    protected function getContents($local)
    {
        $contents = ContentQuery::create()->filterByVisible(1)->find();

        $result = [];
        foreach ($contents as $content){
            $content->setLocale($local);
            $result[$content->getId()] = $content->getTitle();
        }
        return $result;
    }

    protected function getFolders($local)
    {
        $folders = FolderQuery::create()->filterByVisible(1)->find();

        $result = [];
        foreach ($folders as $folder){
            $folder->setLocale($local);
            $result[$folder->getId()] = $folder->getTitle();
        }
        return $result;
    }

    protected function getBrands($local)
    {
        $brands = BrandQuery::create()->filterByVisible(1)->find();

        $result = [];
        foreach ($brands as $brand){
            $brand->setLocale($local);
            $result[$brand->getId()] = $brand->getTitle();
        }
        return $result;
    }

    protected function getParents()
    {
        $langId = $this->getRequest()->get('edit_language_id');
        $locale = null;
        if ($langId){
            $locale = LangQuery::create()->findOneById($langId)->getLocale();
        }
        $menuId = $this->getRequest()->get('menu_id');
        $parents = CustomFrontMenuItemsQuery::create()
            ->filterByMenu($menuId)
            ->filterByLocale($locale)
            ->find();
        $result[] = Translator::getInstance()->trans('No parent', [], CustomFrontMenu::DOMAIN_NAME);
        foreach ($parents as $parent){
            $result[$parent->getId()] = $parent->getTextLink();
        }
        return $result;
    }
}