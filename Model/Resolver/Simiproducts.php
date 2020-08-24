<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\CatalogGraphQl\Model\Resolver\Products\Query\Filter;
use Simi\SimiconnectorGraphQl\Model\Resolver\Products\Query\Search;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\Argument\SearchCriteria\Builder;
use Magento\Framework\GraphQl\Query\Resolver\Argument\SearchCriteria\SearchFilter;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\CatalogGraphQl\DataProvider\Product\SearchCriteriaBuilder;

/**
 * Products field resolver, used for GraphQL request processing.
 */
class Simiproducts implements ResolverInterface
{
    /**
     * @var Builder
     * @deprecated 100.3.4
     */
    private $searchCriteriaBuilder;

    /**
     * @var Search
     */
    private $searchQuery;

    /**
     * @var Filter
     * @deprecated 100.3.4
     */
    private $filterQuery;

    /**
     * @var SearchFilter
     * @deprecated 100.3.4
     */
    private $searchFilter;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchApiCriteriaBuilder;

    public $extraFields;
    public $productExtraData;
    public $currentProductModel;


    public $registry;
    public $simiObjectManager;
    public $eventManager;
    public $scopeConfig;
    public $simiOptionHelper;
    public $simiReviewHelper;
    public $simiPriceHelper;
    public $simiLayout;

    public function __construct(
        Builder $searchCriteriaBuilder,
        Search $searchQuery,
        Filter $filterQuery,
        SearchFilter $searchFilter,
        SearchCriteriaBuilder $searchApiCriteriaBuilder = null,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\ObjectManagerInterface $simiObjectManager,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Simi\Simiconnector\Helper\Options $simiOptionHelper,
        \Simi\Simiconnector\Helper\Review $simiReviewHelper,
        \Simi\Simiconnector\Helper\Price $simiPriceHelper,
        \Magento\Framework\View\LayoutInterface $simiLayout
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->searchQuery = $searchQuery;
        $this->filterQuery = $filterQuery;
        $this->searchFilter = $searchFilter;
        $this->searchApiCriteriaBuilder = $searchApiCriteriaBuilder ??
            \Magento\Framework\App\ObjectManager::getInstance()->get(SearchCriteriaBuilder::class);

        $this->registry = $registry;
        $this->simiObjectManager = $simiObjectManager;
        $this->eventManager = $eventManager;
        $this->scopeConfig = $scopeConfig;
        $this->simiOptionHelper = $simiOptionHelper;
        $this->simiReviewHelper = $simiReviewHelper;
        $this->simiPriceHelper = $simiPriceHelper;
        $this->simiLayout = $simiLayout;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if ($args['currentPage'] < 1) {
            throw new GraphQlInputException(__('currentPage value must be greater than 0.'));
        }
        if ($args['pageSize'] < 1) {
            throw new GraphQlInputException(__('pageSize value must be greater than 0.'));
        }
        if (!isset($args['search']) && !isset($args['filter'])) {
            throw new GraphQlInputException(
                __("'search' or 'filter' input argument is required.")
            );
        }

        //get product children fields queried
        $productFields = (array)$info->getFieldSelection(1);
        $includeAggregations = isset($productFields['filters']) || isset($productFields['aggregations']);
        $searchCriteria = $this->searchApiCriteriaBuilder->build($args, $includeAggregations);
        $searchResult = $this->searchQuery->getResult($searchCriteria, $info, $args);

        if ($searchResult->getCurrentPage() > $searchResult->getTotalPages() && $searchResult->getTotalCount() > 0) {
            throw new GraphQlInputException(
                __(
                    'currentPage value %1 specified is greater than the %2 page(s) available.',
                    [$searchResult->getCurrentPage(), $searchResult->getTotalPages()]
                )
            );
        }


        //simiconnector changing
        $registry = $this->registry;
        $simiProductFilters = $registry->registry('simiProductFilters');

        $products = $searchResult->getProductsSearchResult();
        $isShowOptionsInListing = $this->scopeConfig
            ->getValue('siminiaconfig/compareconfig/show_size_in_compare');
	    foreach ($products as $index => $product) {
		    $productModel = $product['model'];
		    if ($productModel->getId()) {
			    if (count($products) > 1) { //listing
				    $attributes = $productModel->toArray();
				    if (isset($attributes['description']))
					    unset($attributes['description']);

				    $this->productExtraData = array(
					    'attribute_values' => $attributes,
					    'app_reviews' => $this->simiReviewHelper
						    ->getProductReviews($productModel->getId())
				    );
				    $this->currentProductModel = $productModel;
				    $this->eventManager->dispatch(
					    'simi_simiconnector_graphql_simi_product_list_item_after',
					    ['object' => $this, 'extraData' => $this->productExtraData]
				    );
				    $product['simiExtraField'] = json_encode($this->productExtraData);
				    $products[$index] = $product;
			    } else { //details
				    $registry = $this->registry;
				    if (!$registry->registry('product') && $productModel->getId()) {
					    $registry->register('product', $productModel);
					    $registry->register('current_product', $productModel);
				    }

				    $app_reviews  = $this->simiReviewHelper
					    ->getProductReviews($productModel->getId());

				    $layout      = $this->simiLayout;
				    $block_att   = $layout->createBlock('Magento\Catalog\Block\Product\View\Attributes');
				    $_additional = $block_att->getAdditionalData();

				    $tierPrice   = $this->simiPriceHelper->getProductTierPricesLabel($productModel);

				    $this->extraFields = array(
					    'attribute_values' => $productModel->toArray(),
					    'app_reviews' => $app_reviews,
					    'additional'  => $_additional,
					    'app_tier_prices' => $tierPrice,
				    );
				    $this->currentProductModel = $productModel;
				    $this->eventManager->dispatch(
					    'simi_simiconnector_graphql_product_detail_extra_field_after',
					    ['object' => $this, 'data' => $this->extraFields]
				    );
				    $product['simiExtraField'] = json_encode($this->extraFields);
				    $products[$index] = $product;
			    }
		    }
	    }

        $this->result = [
            'total_count' => $searchResult->getTotalCount(),
            'items' => $products,
            'page_info' => [
	            'page_size' => $searchResult->getPageSize(),
	            'current_page' => $searchResult->getCurrentPage(),
	            'total_pages' => $searchResult->getTotalPages()
            ],
            'search_result' => $searchResult,
            'layer_type' => isset($args['search']) ? Resolver::CATALOG_LAYER_SEARCH : Resolver::CATALOG_LAYER_CATEGORY,
            'simi_filters' => $simiProductFilters?json_decode($simiProductFilters):array(),
            'minPrice' => $registry->registry('simi_min_price'),
            'maxPrice' => $registry->registry('simi_max_price')
        ];

        $this->eventManager->dispatch(
            'simi_simiconnector_graphql_simi_product_list_after',
            ['object' => $this, 'data' => $this->result]
        );

        return $this->result;
    }
}
