<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver\Products\DataProvider;

use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionPostProcessor;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchResultApplierFactory;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchResultApplierInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\Catalog\Model\Product\Visibility;

/**
 * Product field data provider for product search, used for GraphQL resolver processing.
 */
class ProductSearch
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var ProductSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionPreProcessor;

    /**
     * @var CollectionPostProcessor
     */
    private $collectionPostProcessor;

    /**
     * @var SearchResultApplierFactory;
     */
    private $searchResultApplierFactory;

    public $simiObjectManager;
    public $simiProductHelper;
    public $categoryFactory;
    public $productFactory;
    public $resourceConnection;
    public $storeManager;
    public $registry;

    /**
     * @param CollectionFactory $collectionFactory
     * @param ProductSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionPreProcessor
     * @param CollectionPostProcessor $collectionPostProcessor
     * @param SearchResultApplierFactory $searchResultsApplierFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        ProductSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionPreProcessor,
        CollectionPostProcessor $collectionPostProcessor,
        SearchResultApplierFactory $searchResultsApplierFactory,
        \Magento\Framework\ObjectManagerInterface $simiObjectManager,
        \Simi\SimiconnectorGraphQl\Helper\Products $simiProductHelper,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Registry $registry
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionPreProcessor = $collectionPreProcessor;
        $this->collectionPostProcessor = $collectionPostProcessor;
        $this->searchResultApplierFactory = $searchResultsApplierFactory;
        $this->simiProductHelper = $simiProductHelper;
        $this->categoryFactory = $categoryFactory;
        $this->productFactory = $productFactory;
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->registry = $registry;
    }

    /**
     * Get list of product data with full data set. Adds eav attributes to result set from passed in array
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param SearchResultInterface $searchResult
     * @param array $attributes
     * @return SearchResultsInterface
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria,
        SearchResultInterface $searchResult,
        array $attributes = [],
        array $args //simiconnector changing
    ): SearchResultsInterface {
        /** @var Collection $collection */
        // $collection = $this->collectionFactory->create();

        // //Join search results
        // $this->getSearchResultsApplier($searchResult, $collection, $this->getSortOrderArray($searchCriteria))->apply();

        // $this->collectionPreProcessor->process($collection, $searchCriteria, $attributes);
        // $collection->load();
        // $this->collectionPostProcessor->process($collection, $attributes);

        /*
         * simiconnector changing
        */
        $collection = null;
        $helper = $this->simiProductHelper;
        $params = array(
            'filter' => array()
        );
        /*
         * apply filter
         */
        $is_search = 0;
        //filter by search query
        if ($args && isset($args['search']) && $args['search']) {
            $is_search = 1;
            $helper->is_search = 1;
            $params['filter']['q'] = $args['search'];
            $helper->getSearchProducts($collection, $params);
            if (!isset($args['sort']) || $args['sort']) {
                $collection->setOrder('relevance', 'desc');
            }
            $collection->setVisibility(array('in' => array(Visibility::VISIBILITY_IN_SEARCH, Visibility::VISIBILITY_BOTH)));
        }
        //filter by category
        if ($args && isset($args['filter']['category_id']['eq'])) {
            $category = $this->categoryFactory->create()
                ->load($args['filter']['category_id']['eq']);
            $collection = $category->getProductCollection();
            $collection->setVisibility(array('in' => array(Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_BOTH)));
        } else if (!$is_search || !$collection) {
            $category = $this->categoryFactory->create()
                ->load($this->storeManager->getStore()->getRootCategoryId());
            $collection = $category->getProductCollection();
            $collection->setVisibility(array('in' => array(Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_BOTH)));
        }
        $helper->builderQuery = $collection;

        $collection->addAttributeToSelect('*')->addFinalPrice();
        //get min and max price before filtering
        $registry = $this->registry;
        $registry->register('simi_min_price', $collection->getMinPrice());
        $registry->register('simi_max_price', $collection->getMaxPrice());

        //filter by graphql attribute filter (excluded search and category)
        if ($args && isset($args['filter'])) {
            foreach ($args['filter'] as $attr=>$value) {
                if ($attr != 'category_id' && $attr != 'q') {
                    $collection->addAttributeToFilter($attr, $value);
                }
            }
        }

        //filter product by simi_filter
        if ($args && isset($args['simiFilter']) && $simiFilter = json_decode($args['simiFilter'], true)) {
            $cat_filtered = false;
            if (isset($simiFilter['cat'])) {
                $simiFilter['category_id'] = $simiFilter['cat'];
                unset($simiFilter['cat']);
            }
            $params['filter']['layer'] = $simiFilter;
            $helper->filterCollectionByAttribute($collection, $params, $cat_filtered);
        }
        //To remove the filtered attribute to get all available filters (including the filtered values)
        $helper->filteredAttributes = [];

        //get simi_filter options
        if ($simiProductFilters = $helper->getLayerNavigator($collection, $params)) {
            $simiFilterOptions = array();
            if (isset($simiProductFilters['layer_filter'])) {
                foreach ($simiProductFilters['layer_filter'] as $layer_filter) {
                    if (isset($layer_filter['filter']) && $count = count($layer_filter['filter'])) {
                        $filtersubOptions = array();
                        foreach ($layer_filter['filter'] as $filtersubOption) {
                            $filtersubOption['value_string'] = (string) $filtersubOption['value'];
                            $filtersubOption['items_count'] = (int) $filtersubOption['count'];
                            $filtersubOptions[] = $filtersubOption;
                        }
                        $simiFilterOptions[] = array(
                            'name' => $layer_filter['title'],
                            'filter_items_count' => $count,
                            'request_var' => $layer_filter['attribute'],
                            'filter_items' => $filtersubOptions,
                        );
                    }
                }
            }

            if (count($simiFilterOptions)) {
                $registry = $this->registry;
                $registry->register('simiProductFilters', json_encode($simiFilterOptions));
            }
        }

        if (in_array('media_gallery_entries', $attributes)) {
            $collection->addMediaGalleryData();
        }
        if (in_array('options', $attributes)) {
            $collection->addOptionsToResult();
        }

        //simi add pagination + sort
        if (isset($args['currentPage']) && isset($args['pageSize'])) {
            $collection->setPageSize($args['pageSize']);
            $collection->setCurPage($args['currentPage']);
        }
        if (isset($args['simiProductSort'])) {
            if ($args['simiProductSort']['attribute'] == 'most_viewed')
                $this->applySimiViewCountSort($collection, $args['simiProductSort']['direction']);
            elseif ($args['simiProductSort']['attribute'] == 'top_rated')
	            $this->applySimiTopRatedSort($collection, $args['simiProductSort']['direction']);
            else
                $collection->setOrder($args['simiProductSort']['attribute'], $args['simiProductSort']['direction']);
        } else if (isset($args['sort'])) {
            foreach ($args['sort'] as $atr=>$dir) {
                $collection->setOrder($atr, $dir);
            }
        }

        $items = array();
        foreach ($collection->getData() as $index => $product) {
            $items[(int)$product['entity_id']] = $this->productFactory->create()
                ->load($product['entity_id']);
        }

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setTotalCount($collection->getSize());
        $searchResults->setItems($items);
        return $searchResults;
    }

    public function applySimiViewCountSort($collection, $dir) {
        $resource = $this->resourceConnection;
        $reportEventTable = $collection->getResource()->getTable('report_event');
        $conn = $resource->getConnection('catalog');
        $subSelect = $conn->select()->from(
            ['report_event_table' => $reportEventTable],
            'COUNT(report_event_table.event_id)'
        )->where('report_event_table.object_id = e.entity_id');
        $collection->getSelect()->columns(
            ['views' => $subSelect]
        )->order(
            'views '  . $dir
        );
    }

	public function applySimiTopRatedSort($collection, $dir) {
		$collection->joinField(
			'rating_summary',
			'review_entity_summary',
			'rating_summary',
			'entity_pk_value=entity_id',
			array(
				'entity_type' => 1,
				'store_id'    => $this->storeManager->getStore()->getId()
			),
			'left'
		);
		$collection->getSelect()->order( 'rating_summary ' . $dir );
	}

}
