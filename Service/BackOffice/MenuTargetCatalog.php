<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*************************************************************************************/

namespace CustomFrontMenu\Service\BackOffice;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Model\BrandQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\ProductQuery;

/**
 * The pickable targets of a menu entry, for the composition screen.
 *
 * The Smarty screen ran five {loop} and emitted one <script> tag per row, so a shop with
 * ten thousand products shipped ten thousand script tags. This runs the same five queries
 * once and hands the screen a single payload to put in a data- attribute.
 */
final readonly class MenuTargetCatalog
{
    private const FALLBACK_LOCALE = 'en_US';

    /**
     * @return array<string, list<array<string, string|int>>>
     */
    public function targets(?string $locale = null): array
    {
        $locale ??= self::FALLBACK_LOCALE;

        return [
            'brand' => $this->rows(BrandQuery::create(), $locale),
            'category' => $this->rows(CategoryQuery::create(), $locale),
            'content' => $this->rows(ContentQuery::create(), $locale),
            'folder' => $this->rows(FolderQuery::create(), $locale),
            'product' => $this->rows(ProductQuery::create(), $locale, withReference: true),
        ];
    }

    /**
     * @param BrandQuery|CategoryQuery|ContentQuery|FolderQuery|ProductQuery $query
     *
     * @return list<array<string, string|int>>
     */
    private function rows(ModelCriteria $query, string $locale, bool $withReference = false): array
    {
        // LEFT_JOIN, not the default inner join: an item with no translation in this
        // locale must still be pickable, titleless rather than absent.
        $results = $query
            ->joinWithI18n($locale, Criteria::LEFT_JOIN)
            ->orderById()
            ->find();

        $rows = [];

        foreach ($results as $result) {
            $row = [
                'id' => (int) $result->getId(),
                'title' => (string) $result->getTitle(),
            ];

            if ($withReference) {
                $row['reference'] = (string) $result->getRef();
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
