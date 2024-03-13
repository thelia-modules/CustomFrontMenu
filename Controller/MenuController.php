<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 08/10/2019
 * Time: 12:13
 */

namespace CustomFrontMenu\Controller;



use CustomFrontMenu\CustomFrontMenu;
use CustomFrontMenu\Form\AddItemForm;
use CustomFrontMenu\Form\AddMenuForm;
use CustomFrontMenu\Form\SelectMenuForm;
use CustomFrontMenu\Form\UpdateItemForm;
use CustomFrontMenu\Model\CustomFrontMenu as CustomFrontMenuModel;
use CustomFrontMenu\Model\CustomFrontMenuItems;
use CustomFrontMenu\Model\CustomFrontMenuItemsQuery;
use CustomFrontMenu\Model\CustomFrontMenuQuery;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

class MenuController extends BaseAdminController
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createAction()
    {
        $form = new AddMenuForm($this->getRequest());

        $vform = $this->validateForm($form);

        $name = $vform->get('menu_name')->getData();

        try{
            $menu = new CustomFrontMenuModel();
            $menu
                ->setMenuName($name)
                ->save();
        }catch (\Exception $e){
            $this->setupFormErrorContext(
                "Wrong Menu Name",
                $this->getTranslator()->trans("This menu already exists", [], CustomFrontMenu::DOMAIN_NAME),
                $form
            );
        }

        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu',['menu_id' => $menu->getId()])
        );
    }

    public function selectAction()
    {
        $form = new SelectMenuForm($this->getRequest());

        $vform = $this->validateForm($form);

        $id = $vform->get('select_menu')->getData();

        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu',['menu_id' => $id])
        );
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function deleteAction()
    {
        $menuId = $this->getRequest()->get('menu_id');
        $locale = $this->getRequest()->get('locale');

        CustomFrontMenuItemsQuery::create()->filterByMenu($menuId)->filterByLocale($locale)->delete();
        CustomFrontMenuQuery::create()->filterById($menuId)->delete();

        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu')
        );
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function addItemAction()
    {
        $form = new AddItemForm($this->getRequest());

        $vform = $this->validateForm($form);

        $menuId = $this->getRequest()->get('menu_id');
        $languageId = $this->getRequest()->get('lang_id');

        $lang = LangQuery::create()
            ->findOneById($languageId);
        if (null === $lang){
            $this->setupFormErrorContext(
                "No lang",
                $this->getTranslator()->trans("No language selected", [], CustomFrontMenu::DOMAIN_NAME),
                $form
            );
            return $this->generateRedirect(
                URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu',[
                    'menu_id' => $menuId,
                ])
            );
        }
        $locale = $lang->getLocale();

        $type = $vform->get('type')->getData();

        $parentId = $vform->get('parent')->getData();

        $depth = 0;

        if ($parentId){
            $depth = $this->getDepth($parentId, $menuId);
        }

        $position = $this->getPosition($parentId, $menuId, $locale);

        $text = $vform->get('text')->getData();

        $id = null;
        $url = null;
        switch ($type){
            case 'category':
                $id = $vform->get('categories')->getData();
                break;
            case 'product':
                $id = $vform->get('products')->getData();
                break;
            case 'content':
                $id = $vform->get('contents')->getData();
                break;
            case 'folder':
                $id = $vform->get('folders')->getData();
                break;
            case 'brand':
                $id = $vform->get('brands')->getData();
                break;
            case 'url':
                $url = $vform->get('url')->getData();
                break;
        }

        $item = new CustomFrontMenuItems();
        $item
            ->setTextLink($text)
            ->setType($type)
            ->setTypeId($id)
            ->setUrl($url)
            ->setParent($parentId)
            ->setDepth($depth)
            ->setPosition($position)
            ->setMenu($menuId)
            ->setLocale($locale)
            ->save();

        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu',[
                'menu_id' => $menuId,
                'edit_language_id' => $languageId
            ])
        );
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updateItemAction()
    {
        $form = new UpdateItemForm($this->getRequest());

        $vform = $this->validateForm($form);

        $itemId = $this->getRequest()->get('item_id');

        $url = $vform->get('url')->getData();

        $text = $vform->get('text')->getData();

        $item = CustomFrontMenuItemsQuery::create()->findOneById($itemId);

        $languageId = LangQuery::create()
            ->findOneByLocale($item->getLocale())
            ->getId();

        $menuId = $item->getMenu();

        if ($url !== $item->getUrl()){
            $item->setUrl($url);
        }
        if ($text !== $item->getTextLink()){
            $item->setTextLink($text);
        }
        $item->save();
        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu',[
                'menu_id' => $menuId,
                'edit_language_id' => $languageId
            ])
        );
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updatePositionAction()
    {
        $itemId = $this->getRequest()->get('item_id');
        $position = $this->getRequest()->get('position'.$itemId);

        $item = CustomFrontMenuItemsQuery::create()->findOneById($itemId);
        $item->setPosition($position)->save();

        $languageId = LangQuery::create()
            ->findOneByLocale($item->getLocale())
            ->getId();

        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu',[
                'menu_id' => $item->getMenu(),
                'edit_language_id' => $languageId
            ])
        );
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function deleteItemAction()
    {
        $itemId = $this->getRequest()->get('item_id');

        $item = CustomFrontMenuItemsQuery::create()->findOneById($itemId);

        $languageId = LangQuery::create()
            ->findOneByLocale($item->getLocale())
            ->getId();

        $children = CustomFrontMenuItemsQuery::create()->filterByParent($itemId)->find();
        foreach ($children as $child){
            $child
                ->setParent(0)
                ->setDepth($this->getDepth(0, $item->getMenu()))
                ->save();
            $this->updateChildren($child->getId(), $item->getMenu());
        }
        $item->delete();
        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu',[
                'menu_id' => $item->getMenu(),
                'edit_language_id' => $languageId
            ])
        );
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updateParentAction()
    {
        $itemId = $this->getRequest()->get('item_id');
        $parent = $this->getRequest()->get('parent'.$itemId);

        $item = CustomFrontMenuItemsQuery::create()->findOneById($itemId);

        $item
            ->setDepth($this->getDepth($parent, $item->getMenu()))
            ->setParent($parent)
            ->setPosition($this->getPosition($parent, $item->getMenu(), $item->getLocale()))
            ->save();

        $this->updateChildren($item->getId(), $item->getMenu());

        $languageId = LangQuery::create()
            ->findOneByLocale($item->getLocale())
            ->getId();

        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl('/admin/module/CustomFrontMenu',[
                'menu_id' => $item->getMenu(),
                'edit_language_id' => $languageId
            ])
        );
    }

    public function getMenuJsonAction()
    {
        $menuName = $this->getRequest()->get('menu_name');
        $locale = $this->getRequest()->get('locale');
        $menu = CustomFrontMenuQuery::create()
            ->findOneByMenuName($menuName);

        $itemsQuery =  CustomFrontMenuItemsQuery::create()
            ->filterByMenu($menu->getId());
        if (null !== $locale){
            $itemsQuery->filterByLocale($locale);
        }
        $items = $itemsQuery
            ->orderByParent()
            ->orderByPosition()
            ->find();
        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'text_link' => $item->getTextLink(),
                'url' => $item->getUrl(),
                'locale' => $item->getLocale(),
                'parent' => $item->getParent(),
                'depth' => $item->getDepth(),
                'position' => $item->getPosition(),
                'menu' => $item->getMenu()
            ];
        }

        return $this->jsonResponse(json_encode($result), 200);
    }

    protected function getDepth($parentId, $menuId, $count = 0)
    {
        $item = CustomFrontMenuItemsQuery::create()
            ->findOneById($parentId);

        if ($item){
            $count++;
            $count =  $this->getDepth($item->getParent(), $menuId, $count);
        }
        return $count;
    }

    protected function getPosition($parent, $menuId, $locale)
    {
        $items = CustomFrontMenuItemsQuery::create()
            ->filterByMenu($menuId)
            ->filterByParent($parent)
            ->filterByLocale($locale)
            ->find();

        return count($items)+1;
    }

    protected function updateChildren($parentId, $menuId)
    {
        $children = CustomFrontMenuItemsQuery::create()
            ->filterByParent($parentId)
            ->find();
        foreach ($children as $child) {
            $this->updateChildren($child->getId(), $menuId);
            $child
                ->setDepth($this->getDepth($child->getParent(), $menuId))
                ->save();
        }
    }


}