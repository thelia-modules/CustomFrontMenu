<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 08/10/2019
 * Time: 14:07
 */

namespace CustomFrontMenu\Loop;


use CustomFrontMenu\Model\CustomFrontMenuItems;
use CustomFrontMenu\Model\CustomFrontMenuItemsQuery;
use CustomFrontMenu\Model\CustomFrontMenuQuery;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ProductQuery;

class CustomFrontMenuItemsLoop extends BaseLoop implements PropelSearchLoopInterface
{
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('id'),
            Argument::createAlphaNumStringTypeArgument('menu_id'),
            Argument::createAlphaNumStringTypeArgument('menu_name'),
            Argument::createIntTypeArgument('possible_parent'),
            Argument::createBooleanTypeArgument('child_count'),
            Argument::createIntTypeArgument('depth'),
            Argument::createIntTypeArgument('parent_id'),
            Argument::createBooleanTypeArgument('in_order'),
            Argument::createAlphaNumStringTypeArgument('locale'),
            Argument::createIntTypeArgument('lang_id')
        );
    }

    public function buildModelCriteria()
    {

    }

    public function parseResults(LoopResult $loopResult)
    {
        $items = null;
        if (null !== $id = $this->getId()){
            $items = $this->getItemById($id);
        }

        $menuId = $this->getMenuId();
        if (null !== $menuName = $this->getMenuName()){
            $menu = CustomFrontMenuQuery::create()
                ->filterByMenuName($menuName)
                ->findOne();
            if (null !== $menu){
                $menuId = $menu->getId();
            }
        }

        $locale = $this->request->getSession()->getLang()->getLocale();

        if (null === $locale){
            if (null !== $languageId = $this->getLangId()){
                $locale = LangQuery::create()
                    ->findOneById($languageId)
                    ->getLocale();
            }
        }

        if (null !== $menuId){
            if ($this->getInOrder()){
                $items = $this->getItemsByMenuIdInOrder($menuId, $locale);
            }
            else{
                $depth = $this->getDepth();
                $items = $this->getItemsByMenuId($menuId, $depth, $locale);
            }
        }

        if (null !== $id = $this->getPossibleParent()){
            $items = $this->getPossibleParents($id);
        }

        if (null !== $parentId = $this->getParentId()){
            $items = $this->getItemsByParent($parentId);
        }
        if (null === $items){
            $items = [];
        }

        /** @var CustomFrontMenuItems $data */
        foreach ($items as $data) {
            $loopResultRow = new LoopResultRow($data);

            $itemInfo = $this->getItemInfo($data, $locale);

            $loopResultRow
                ->set('ID', $data->getId())
                ->set('TEXT_LINK', $data->getTextLink())
                ->set('TYPE', $data->getType())
                ->set('TYPE_ID', $data->getTypeId())
                ->set('TYPE_NAME', $itemInfo['name'])
                ->set('URL', $itemInfo['url'])
                ->set('PARENT', $data->getParent())
                ->set('DEPTH', $data->getDepth())
                ->set('MENU_ID', $data->getMenu())
                ->set('POSITION', $data->getPosition());

            if ($this->getChildCount()){
                $child = count(CustomFrontMenuItemsQuery::create()
                    ->filterByParent($data->getId())
                    ->find());
                $loopResultRow
                    ->set('CHILD_COUNT', $child);
            }

            $loopResult->addRow($loopResultRow);
        }
        return $loopResult;
    }

    protected function getItemsInOrder($data, $result = [])
    {
        /** @var CustomFrontMenuItems $item */
        foreach ($data as $item){
            $result[] = $item;
            if (null !== $children = CustomFrontMenuItemsQuery::create()
                    ->filterByMenu($item->getMenu())
                    ->filterByParent($item->getId())
                    ->orderByPosition()
                    ->find()){
                $result = $this->getItemsInOrder($children, $result);
            }
        }
        return $result;
    }

    protected function getPossibleParents($id)
    {
        $current = CustomFrontMenuItemsQuery::create()
            ->findOneById($id);
        $items = CustomFrontMenuItemsQuery::create()
            ->filterByMenu($current->getMenu())
            ->filterByLocale($current->getLocale())
            ->find();
        $children = $this->getAllChildren($current);
        return $parents = array_diff($items->getData(), [$current] ,$children);
    }

    protected function getAllChildren(CustomFrontMenuItems $item, $children = [])
    {
        $directChildren = CustomFrontMenuItemsQuery::create()->filterByParent($item->getId())->find()->getData();
        foreach ($directChildren as $child){
            $children[] = $child;
            $children = $this->getAllChildren($child, $children);
        }
        return $children;
    }

    protected function getItemById($id)
    {
        $items = [];
        return $items[] = CustomFrontMenuItemsQuery::create()
            ->filterById($id)
            ->find();
    }

    protected function getItemsByMenuIdInOrder($menuId, $locale)
    {
        if (null === $locale){
            return null;
        }
        $rootItems = CustomFrontMenuItemsQuery::create()
            ->filterByMenu($menuId)
            ->filterByDepth(0)
            ->filterByLocale($locale)
            ->orderByPosition()
            ->find();
        return $items = $this->getItemsInOrder($rootItems);
    }

    protected function getItemsByMenuId($menuId, $depth, $locale){
        $items = CustomFrontMenuItemsQuery::create()
            ->filterByMenu($menuId);

        if (null !== $depth){
            $items->filterByDepth($depth);
        }

        if (null !== $locale){
            $items->filterByLocale($locale);
        }

        return $items
            ->orderByPosition()
            ->find();
    }

    protected function getItemsByParent($parent)
    {
        return CustomFrontMenuItemsQuery::create()
            ->filterByParent($parent)
            ->orderByPosition()
            ->find();
    }

    protected function getItemInfo(CustomFrontMenuItems $data, $locale)
    {
        $query = null;
        switch ($data->getType()){
            case 'category':
                $query = CategoryQuery::create();
                break;
            case 'product':
                $query = ProductQuery::create();
                break;
            case 'content':
                $query = ContentQuery::create();
                break;
            case 'folder':
                $query = FolderQuery::create();
                break;
            case 'brand':
                $query = BrandQuery::create();
                break;
            case 'url':
                return [
                    'url' => $data->getUrl(),
                    'name' => null
                ];
                break;
        }
        $item =  $query->findOneById($data->getTypeId())->setLocale($locale);
        return [
            'url' => $item->getUrl(),
            'name' => $item->getTitle()
        ];

    }
}