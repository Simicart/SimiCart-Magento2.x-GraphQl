<?php

/**
 * Connector data helper
 */

namespace Simi\SimiconnectorGraphQl\Helper;
use Magento\Bundle\Model\ResourceModel\Selection as BundleSelection;
use Magento\GroupedProduct\Model\ResourceModel\Product\Link as GroupedProductLink;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Catalog\Api\Data\ProductInterface;

class Products extends \Magento\Framework\App\Helper\AbstractHelper
{

    public $simiObjectManager;
    public $storeManager;
    public $builderQuery;
    public $data = [];
    public $sortOrders = [];
    public $category;
    public $productStatus;
    public $productVisibility;
    public $filteredAttributes = [];
    public $is_search = 0;
    public $productCollectionFactory;
    public $attributeCollectionFactory;
    public $searchCollection;
    public $priceHelper;
    public $imageHelper;
    public $stockHelper;
    public $categoryModelFactory;
    public $productModelFactory;
    public $bundleSelection;
    public $groupedProductLink;
    public $metadataPool;
    public $currencyFactory;

    const XML_PATH_RANGE_STEP = 'catalog/layered_navigation/price_range_step';
    const MIN_RANGE_POWER = 10;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Catalog\Model\Product\Attribute\Source\Status $productStatus,
        \Magento\Catalog\Model\Product\Visibility $productVisibility,
        \Magento\Framework\ObjectManagerInterface $simiObjectManager,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $attributeCollectionFactory,
        \Magento\Search\Model\SearchCollectionInterface $searchCollection,
        \Magento\Framework\Pricing\Helper\Data $priceHelper,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\CatalogInventory\Helper\Stock $stockHelper,
        \Magento\Catalog\Model\Category $categoryModelFactory,
        \Magento\Catalog\Model\Product $productModelFactory,
        BundleSelection $bundleSelection,
        GroupedProductLink $groupedProductLink,
        MetadataPool $metadataPool,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory
    )
    {

        $this->simiObjectManager = $simiObjectManager;
        $this->scopeConfig = $scopeConfigInterface;
        $this->storeManager = $storeManager;
        $this->productStatus = $productStatus;
        $this->productVisibility = $productVisibility;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->searchCollection = $searchCollection;
        $this->priceHelper = $priceHelper;
        $this->imageHelper = $imageHelper;
        $this->stockHelper = $stockHelper;
        $this->categoryModelFactory = $categoryModelFactory;
        $this->productModelFactory = $productModelFactory;
        $this->bundleSelection = $bundleSelection;
        $this->groupedProductLink = $groupedProductLink;
        $this->metadataPool = $metadataPool;
        $this->currencyFactory = $currencyFactory;
        parent::__construct($context);
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * @return product collection.
     *
     */
    public function getBuilderQuery()
    {
        return $this->builderQuery;
    }

    public function getProduct($product_id)
    {
        $this->builderQuery = $this->productModelFactory->create()->load($product_id);
        if (!$this->builderQuery->getId()) {
            throw new \Exception(__('Resource cannot callable.'), 6);
        }
        return $this->builderQuery;
    }


    public function loadCategoryWithId($id)
    {
        $categoryModel = $this->categoryModelFactory->create()->load($id);
        return $categoryModel;
    }

    public function loadAttributeByKey($key)
    {
        return $this->attributeCollectionFactory->create()
            ->getItemByColumnValue('attribute_code', $key);
    }

    public function filterCollectionByAttribute($collection, $params, &$cat_filtered)
    {
        foreach ($params['filter']['layer'] as $key => $value) {
            if ($key == 'price') {
                $currencyCodeFrom = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
                $currencyCodeTo = $this->storeManager->getStore()->getBaseCurrency()->getCode();
                $rate = $this->currencyFactory->create()->load($currencyCodeTo)->getAnyRate($currencyCodeFrom);

                $value = explode('-', $value);
	            if ( isset( $value[0] ) && isset( $value[1] ) ) {
		            $priceFrom = $value[0] / $rate;
		            $priceTo   = $value[1] / $rate;
		            $collection->getSelect()->where( "price_index.final_price > " . $priceFrom )
		                       ->where( "price_index.final_price < " . $priceTo );
	            }
            } else {
                if ($key == 'category_id') {
                    $cat_filtered = true;
                    if ($this->category) {
                        if (is_array($value)) {
                            $value[] = $this->category->getId();
                        } else {
                            $value = [$this->category->getId(), $value];
                        }
                    }
                    $this->filteredAttributes[$key] = $value;
                    $collection->addCategoriesFilter(['in' => $value]);
                } elseif ($key == 'size' || $key == 'color') {
                    $this->filteredAttributes[$key] = $value;
                    # code...
                    $productIds = [];
                    $collectionChid = $this->productCollectionFactory->create();

                    $collectionChid->addAttributeToSelect('*')
                        ->addStoreFilter()
                        ->addAttributeToFilter('status', 1)
                        ->addFinalPrice();
                    if (is_array($value)) {
                        $insetArray = array();
                        foreach ($value as $child_value) {
                            $insetArray[] = array('finset' => array($child_value));
                        }
                        $collectionChid->addAttributeToFilter($key, $insetArray);
                    } else
                        $collectionChid->addAttributeToFilter($key, ['finset' => $value]);
                    $collectionChid->getSelect()
                        ->joinLeft(
                            array('link_table' => $collection->getResource()->getTable('catalog_product_super_link')),
                            'link_table.product_id = e.entity_id',
                            array('product_id', 'parent_id')
                        );

                    $collectionChid->getSelect()->group('link_table.parent_id');

                    foreach ($collectionChid as $product) {
                        $productIds[] = $product->getParentId();
                    }

                    $collection->addAttributeToFilter('entity_id', array('in' => $productIds));
                } else {
                    $this->filteredAttributes[$key] = $value;
                    if (is_array($value)) {
                        $insetArray = array();
                        foreach ($value as $child_value) {
                            $insetArray[] = array('finset' => array($child_value));
                        }
                        $collection->addAttributeToFilter($key, $insetArray);
                    } else
                        $collection->addAttributeToFilter($key, ['finset' => $value]);
                }
            }
        }
    }
    
    public function getLayerNavigator($collection = null, $params = null)
    {
        if (!$collection) {
            $collection = $this->builderQuery;
        }
        if (!$params) {
            $data = $this->getData();
            $params = isset($data['params']) ? $data['params'] : array();
        }
        $attributeCollection = $this->attributeCollectionFactory->create();
        $attributeCollection
            ->addIsFilterableFilter()
            //->addVisibleFilter() //cody comment out jun152019
            //->addFieldToFilter('used_in_product_listing', 1) //cody comment out jun152019
            //->addFieldToFilter('is_visible_on_front', 1) //cody comment out jun152019
        ;
        $attributeCollection->addFieldToFilter('attribute_code', ['nin' => ['price']]);
        if ($this->is_search)
            $attributeCollection->addFieldToFilter('is_filterable_in_search', 1);

        $allProductIds = $collection->getAllIds();
        $arrayIDs = [];
        foreach ($allProductIds as $allProductId) {
            $arrayIDs[$allProductId] = '1';
        }
        $layerFilters = [];

        $titleFilters = [];
        $this->_filterByAtribute($collection, $attributeCollection, $titleFilters, $layerFilters, $arrayIDs);

        // category
        if ($this->category) {
            $childrenCategories = $this->category->getChildrenCategories();
            $collection->addCountToCategories($childrenCategories);
            $filters = [];
            foreach ($childrenCategories as $childCategory) {
                if ($childCategory->getProductCount()) {
                    $filters[] = [
                        'label' => $childCategory->getName(),
                        'value' => $childCategory->getId(),
                        'count' => $childCategory->getProductCount()
                    ];
                }
            }

            $layerFilters[] = [
                'attribute' => 'category_id',
                'title' => __('Categories'),
                'filter' => ($filters),
            ];
        }

        $paramArray = (array)$params;
        $selectedFilters = $this->_getSelectedFilters();
        $selectableFilters = count($allProductIds) ?
            $this->_getSelectableFilters($collection, $paramArray, $selectedFilters, $layerFilters) :
            array();

        $layerArray = ['layer_filter' => $selectableFilters];
        if (count($selectedFilters) > 0) {
            $layerArray['layer_state'] = $selectedFilters;
        }

        return $layerArray;
    }

    public function _getSelectedFilters()
    {
        $selectedFilters = [];
        foreach ($this->filteredAttributes as $key => $value) {
            if (($key == 'category_id') && is_array($value) &&
                (count($value) >= 2)) {
                $value = $value[1];
                $category = $this->loadCategoryWithId($value);
                $selectedFilters[] = [
                    'value' => $value,
                    'label' => $category->getName(),
                    'attribute' => 'category_id',
                    'title' => __('Categories'),
                ];
                continue;
            }
            if (($key == 'price') && is_array($value) &&
                (count($value) >= 2)) {
                $selectedFilters[] = [
                    'value' => implode('-', $value),
                    'label' => $this->_renderRangeLabel($value[0], $value[1]),
                    'attribute' => 'price',
                    'title' => __('Price')
                ];
                continue;
            }

            $attribute = $this->loadAttributeByKey($key);
            if (is_array($value)) {
                $value = $value[0];
            }
            if ($attribute)
                foreach ($attribute->getSource()->getAllOptions() as $layerFilter) {
                    if ($layerFilter['value'] == $value) {
                        $layerFilter['attribute'] = $key;
                        $layerFilter['title'] = $attribute->getDefaultFrontendLabel();
                        $selectedFilters[] = $layerFilter;
                    }
                }
        }
        return $selectedFilters;
    }

    public function _getSelectableFilters($collection, $paramArray, $selectedFilters, $layerFilters)
    {
        $selectableFilters = [];
        if (is_array($paramArray) && isset($paramArray['filter'])) {
            foreach ($layerFilters as $layerFilter) {
                $filterable = true;
                foreach ($selectedFilters as $key => $value) {
                    if ($layerFilter['attribute'] == $value['attribute']) {
                        $filterable = false;
                        break;
                    }
                }
                if ($filterable) {
                    $selectableFilters[] = $layerFilter;
                }
            }
        }
        return $selectableFilters;
    }

    public function _filterByAtribute($collection, $attributeCollection, &$titleFilters, &$layerFilters, $arrayIDs)
    {
        $childProductsIds = [];
        if ($arrayIDs && count($arrayIDs)) {
            $resource = $collection->getResource();
            //configurable products
            $childProducts = $this->productCollectionFactory->create()
                ->addAttributeToSelect('*');
            $select = $childProducts->getSelect();
            $select->joinLeft(
                array('link_table' => $resource->getTable('catalog_product_super_link')),
                'link_table.product_id = e.entity_id',
                array('product_id', 'parent_id')
            );
            $select = $childProducts->getSelect();
            $select->where("link_table.parent_id IN (" . implode(',', array_keys($arrayIDs)) . ")");
            foreach ($childProducts->getAllIds() as $allProductId) {
                $childProductsIds[$allProductId] = '1';
            }
            //bundle products
            $connection = $this->bundleSelection->getConnection();
            $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
            $select = $connection->select()->from(
                ['tbl_selection' => $this->bundleSelection->getMainTable()],
                ['product_id', 'parent_product_id', 'option_id']
            )->join(
                ['e' => $resource->getTable('catalog_product_entity')],
                'e.entity_id = tbl_selection.product_id AND e.required_options=0',
                []
            )->join(
                ['parent' => $resource->getTable('catalog_product_entity')],
                'tbl_selection.parent_product_id = parent.' . $linkField
            )->join(
                ['tbl_option' => $resource->getTable('catalog_product_bundle_option')],
                'tbl_option.option_id = tbl_selection.option_id',
                ['required']
            )->where(
                'parent.entity_id IN (' . implode(',', array_keys($arrayIDs)) . ')'
            );
            foreach ($connection->fetchAll($select) as $row) {
                if (isset($row['product_id']))
                    $childProductsIds[$row['product_id']] = '1';
            }
            //grouped products
            $connection = $this->groupedProductLink->getConnection();
            $bind = [':link_type_id' => GroupedProductLink::LINK_TYPE_GROUPED];
            $select = $connection->select()->from(
                ['l' => $this->groupedProductLink->getMainTable()],
                ['linked_product_id']
            )->join(
                ['cpe' => $this->groupedProductLink->getTable('catalog_product_entity')],
                sprintf(
                    'cpe.%s = l.product_id',
                    $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField()
                )
            )->where(
                'cpe.entity_id IN (' . implode(',', array_keys($arrayIDs)) . ')'
            )->where(
                'link_type_id = :link_type_id'
            );

            $select->join(
                ['e' => $this->groupedProductLink->getTable('catalog_product_entity')],
                'e.entity_id = l.linked_product_id AND e.required_options = 0',
                []
            );
            foreach ($connection->fetchAll($select, $bind) as $row) {
                if (isset($row['linked_product_id']))
                    $childProductsIds[$row['linked_product_id']] = '1';
            }
        }
        $childAndParentIds = array_merge(array_keys($childProductsIds), array_keys($arrayIDs));
        foreach ($attributeCollection as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            $attributeOptions = [];
            $attributeValues = $this->getAllAttributeValues($attributeCode, $collection, $childAndParentIds);
            if (in_array($attribute->getDefaultFrontendLabel(), $titleFilters)) {
                continue;
            }
            foreach ($attributeValues as $productId => $optionIds) {
                if (isset($optionIds[0]) &&
                    (
                        (isset($arrayIDs[$productId]) && ($arrayIDs[$productId] != null)) ||
                        (isset($childProductsIds[$productId]) && ($childProductsIds[$productId] != null))
                    )
                ) {
                    $optionIds = explode(',', $optionIds[0]);
                    foreach ($optionIds as $optionId) {
                        if (isset($attributeOptions[$optionId])) {
                            $attributeOptions[$optionId]++;
                        } else {
                            $attributeOptions[$optionId] = 1;
                        }
                    }
                }
            }

            $options = $attribute->getSource()->getAllOptions();
            $filters = [];
            foreach ($options as $option) {
                if (isset($option['value']) && isset($attributeOptions[$option['value']])
                    && $attributeOptions[$option['value']]) {
                    $option['count'] = $attributeOptions[$option['value']];
                    $filters[] = $option;
                }
            }

            if (count($filters) >= 1) {
                $titleFilters[] = $attribute->getDefaultFrontendLabel();
                $layerFilters[] = [
                    'attribute' => $attribute->getAttributeCode(),
                    'title' => $attribute->getDefaultFrontendLabel(),
                    'filter' => $filters,
                ];
            }
        }
    }

    /*
     * Get price range filter
     *
     * @param @collection \Magento\Catalog\Model\ResourceModel\Product\Collection
     * @return array
     */

    public function _getPriceRanges($collection)
    {
        $collection->addPriceData();
        $maxPrice = $collection->getMaxPrice();

        $index = 1;
        $counts = [];
        do {
            $range = pow(10, strlen(floor($maxPrice)) - $index);
            $counts = $collection->getAttributeValueCountByRange('price', $range);
            $index++;
        } while ($range > self::MIN_RANGE_POWER && count($counts) < 2 && $index <= 2);

        //re-forming array
        if (isset($counts[''])) {
            $counts[0] = $counts[''];
            unset($counts['']);
            $newCounts = [];
            foreach ($counts as $key => $count) {
                $newCounts[$key + 1] = $counts[$key];
            }
            $counts = $newCounts;
        }
        return ['range' => $range, 'counts' => $counts];
    }

    /*
     * Show price filter label
     *
     * @param $fromPrice int
     * @param $toPrice int
     * @return string
     */

    public function _renderRangeLabel($fromPrice, $toPrice)
    {
        $helper = $this->priceHelper;
        $formattedFromPrice = $helper->currency($fromPrice, true, false);
        if ($toPrice === '') {
            return __('%1 and above', $formattedFromPrice);
        } elseif ($fromPrice == $toPrice) {
            return $formattedFromPrice;
        } else {
            if ($fromPrice != $toPrice) {
                $toPrice -= .01;
            }

            return __('%1 - %2', $formattedFromPrice, $helper->currency($toPrice, true, false));
        }
    }


    /**
     * Return all attribute values as array in form:
     * array(
     *   [entity_id_1] => array(
     *          [store_id_1] => store_value_1,
     *          [store_id_2] => store_value_2,
     *          ...
     *          [store_id_n] => store_value_n
     *   ),
     *   ...
     * )
     *
     * @param string $attribute attribute code
     * @param Magento\Catalog\Model\ResourceModel\Product\Collection $collection product collection
     * @param array $childAndParentIds id of product to filter
     * @return array
     */
    public function getAllAttributeValues($attribute, $collection, $childAndParentIds)
    {
        /** @var $select \Magento\Framework\DB\Select */
        $select = clone $collection->getSelect();
        $attribute = $collection->getEntity()->getAttribute($attribute);

        $fieldMainTable = $collection->getConnection()->getAutoIncrementField($collection->getMainTable());
        $fieldJoinTable = $attribute->getEntity()->getLinkField();
        $select->reset()
            ->from(
                ['cpe' => $collection->getMainTable()],
                ['entity_id']
            )->join(
                ['cpa' => $attribute->getBackend()->getTable()],
                'cpe.' . $fieldMainTable . ' = cpa.' . $fieldJoinTable,
                ['store_id', 'value']
            )->where('attribute_id = ?', (int)$attribute->getId())
            ->where('cpe.entity_id IN (?)', $childAndParentIds);

        $data = $collection->getConnection()->fetchAll($select);
        $res = [];

        foreach ($data as $row) {
            $res[$row['entity_id']][$row['store_id']] = $row['value'];
        }

        return $res;
    }
}
