<?php

/**
 * Connector data helper
 */

namespace Simi\SimiconnectorGraphQl\Helper;

use Magento\Bundle\Model\ResourceModel\Selection as BundleSelection;
use Magento\GroupedProduct\Model\ResourceModel\Product\Link as GroupedProductLink;
use \Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;
use \Magento\Bundle\Model\Product\Type as BundleType;
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
    public $bundleType;
    public $groupType;
    public $currencyFactory;
    public $pIdsFiltedByKey = [];

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
        GroupedType $groupType,
        BundleType $bundleType,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory
    ) {

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
        $this->groupType = $groupType;
        $this->bundleType = $bundleType;
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
        //before apply filter, get the productid and child product id for later get available filters
        $allProductIds = $collection->getAllIds();
        $arrayIDs = [];
        foreach ($allProductIds as $allProductId) {
            $arrayIDs[$allProductId] = '1';
        }
        $childProductsIds = $this->getChildrenIdsFromParentIds($arrayIDs, $collection->getResource());
        $this->beforeApplyFilterParentIds = $allProductIds;
        $this->beforeApplyFilterArrayIds = $arrayIDs;
        $this->beforeApplyFilterChildProductsIds = $childProductsIds;
        $this->beforeApplyFilterChildAndParentIds = array_merge(array_keys($childProductsIds), array_keys($arrayIDs));
        $childAndParentCollection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addStoreFilter()
            ->addFieldToFilter('entity_id', ['in' => $this->beforeApplyFilterChildAndParentIds])
            ->addFieldToFilter('status', 1);
        $this->stockHelper->addInStockFilterToCollection($childAndParentCollection);
        $this->beforeApplyFilterChildAndParentIds = $childAndParentCollection->getAllIds();
        //end
        $pIdsToFilter = $allProductIds;
        foreach ($params['filter']['layer'] as $key => $value) {
            $newCollection = $childAndParentCollection;
            if ($key == 'price') {
                $currencyCodeFrom = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
                $currencyCodeTo = $this->storeManager->getStore()->getBaseCurrency()->getCode();
                $rate = $this->currencyFactory->create()->load($currencyCodeTo)->getAnyRate($currencyCodeFrom);

                $value = explode('-', $value);
                if (isset($value[0]) && isset($value[1])) {
                    $priceFrom = $value[0] / $rate;
                    $priceTo   = $value[1] / $rate;
                    $collection->getSelect()->where("price_index.final_price > " . $priceFrom)
                        ->where("price_index.final_price < " . $priceTo);
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
                    $newCollection->addCategoriesFilter(['in' => $value]);
                } elseif ($key !== 'color' && $key !== 'size') {
                    //no need to filter by child products if not size or color (to optimize)
                    $this->filteredAttributes[$key] = $value;
                    if (is_array($value)) {
                        $insetArray = array();
                        foreach ($value as $child_value) {
                            $insetArray[] = array('finset' => array($child_value));
                        }
                        $newCollection->addAttributeToFilter($key, $insetArray);
                    } else
                        $newCollection->addAttributeToFilter($key, ['finset' => $value]);
                } else {
                    $this->filteredAttributes[$key] = $value;
                    # code...
                    $productIds = [];
                    $collectionChid = $this->productCollectionFactory->create();
                    $collectionChid->addAttributeToSelect('*')
                        ->addStoreFilter()
                        ->addAttributeToFilter('status', 1);
                    $collectionChid->addFieldToFilter('entity_id', ['in' => $this->beforeApplyFilterChildAndParentIds]);
                    $collectionChid->addFinalPrice();
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
                    try {
                        /**
                         * uncomment closure below to filter by bundle child, will make filtering slower
                         */
                        /**
                        foreach ($collectionChid as $product) {
                            // check for group products
                            if (
                                $this->groupType->getParentIdsByChild($product->getId())
                                && is_array($this->groupType->getParentIdsByChild($product->getId()))
                                && count($this->groupType->getParentIdsByChild($product->getId()))
                            ) {
                                $productIds = array_merge($productIds, $this->groupType->getParentIdsByChild($product->getId()));
                            }
                            // check for bundle products
                            if (
                                $this->bundleType->getParentIdsByChild($product->getId())
                                && is_array($this->bundleType->getParentIdsByChild($product->getId()))
                                && count($this->bundleType->getParentIdsByChild($product->getId()))
                            ) {
                                $productIds = array_merge($productIds, $this->bundleType->getParentIdsByChild($product->getId()));
                            }
                            $productIds[] = $product->getParentId() ? $product->getParentId() : $product->getId();
                        } 
                         */
                        foreach ($collectionChid as $product) {
                            $productIds[] = $product->getParentId();
                        }
                        $newCollection->addAttributeToFilter('entity_id', array('in' => $productIds));
                    } catch (\Exception $e) {
                        //when getting collection faced issue `product id already exist` - fallback to old attribute filter
                        if (is_array($value)) {
                            $insetArray = array();
                            foreach ($value as $child_value) {
                                $insetArray[] = array('finset' => array($child_value));
                            }
                            $newCollection->addAttributeToFilter($key, $insetArray);
                        } else
                            $newCollection->addAttributeToFilter($key, ['finset' => $value]);
                    }
                }
                $this->pIdsFiltedByKey[$key] = $newCollection->getAllIds();
            }
        }
        foreach ($this->pIdsFiltedByKey as $pIdsFiltedByKey) {
            $pIdsToFilter = array_intersect($pIdsToFilter, $pIdsFiltedByKey);
        }
        $collection->addAttributeToFilter('entity_id', array('in' => $pIdsToFilter));
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
        //$attributeCollection->addFieldToFilter('attribute_code', ['nin' => ['price']]);
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

        /**
         * Uncomment the lines below to bring back the price filter
         * */
        /**
        if (!(!empty($params['filter']['layer']) && is_array($params['filter']['layer']) && array_key_exists('price', $params['filter']['layer']))) {
            if ($this->afterFilterChildAndParentIds) {
                $parentAndChildCollection = $this->productCollectionFactory->create()->addPriceData()->addFinalPrice()
                    ->addFieldToFilter('entity_id', array('in' => $this->afterFilterChildAndParentIds))
                    ->addAttributeToFilter('status', 1);
                $this->_filterByPriceRange($layerFilters, $parentAndChildCollection, $params);
            }
        }
        */

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
                (count($value) >= 2)
            ) {
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
                (count($value) >= 2)
            ) {
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
        /**
         * Comment out the line below when you want to remove filtered option from  available filter options
         * */
        return $layerFilters;
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
        $childProductsIds = $this->getChildrenIdsFromParentIds($arrayIDs, $collection->getResource());
        $childAndParentIds = array_merge(array_keys($childProductsIds), array_keys($arrayIDs));
        $childAndParentCollection = $this->productCollectionFactory->create()->addFieldToFilter('entity_id', ['in' => $childAndParentIds])
            ->addFieldToFilter('status', 1);
        $this->stockHelper->addInStockFilterToCollection($childAndParentCollection);
        $childAndParentIds = $childAndParentCollection->getAllIds();
        $parentIds = array_keys($arrayIDs);
        $this->afterFilterChildAndParentIds = $childAndParentIds;
        foreach ($attributeCollection as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            $attributeOptions = [];
            //get value from child is going to cause wrong count value
            $toGetValueFromChild = ($attributeCode == 'color' || $attributeCode == 'size');
            $idArrayToFilter = $toGetValueFromChild ? $childAndParentIds : $parentIds;
            $filteredAbove = isset($this->filteredAttributes[$attributeCode]);
            if ($filteredAbove) {
                $idArrayToFilter = $toGetValueFromChild ? $this->beforeApplyFilterChildAndParentIds : $this->beforeApplyFilterParentIds;
                foreach ($this->pIdsFiltedByKey as $key => $pIdsFiltedByKey) {
                    if ($key !== $attributeCode) {
                        $idArrayToFilter = array_intersect($idArrayToFilter, $pIdsFiltedByKey);
                    }
                }
            }

            $attributeValues = $this->getAllAttributeValues($attributeCode, $collection, $idArrayToFilter);
            if (in_array($attribute->getDefaultFrontendLabel(), $titleFilters)) {
                continue;
            }
            foreach ($attributeValues as $productId => $optionIds) {
                if (
                    isset($optionIds[0]) &&
                    (
                        (isset($this->beforeApplyFilterArrayIds[$productId]) &&
                            ($this->beforeApplyFilterArrayIds[$productId] != null)) ||
                        (isset($this->beforeApplyFilterChildProductsIds[$productId]) &&
                            ($this->beforeApplyFilterChildProductsIds[$productId] != null)))
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
                if (
                    isset($option['value']) && isset($attributeOptions[$option['value']])
                    && $attributeOptions[$option['value']]
                ) {
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

    public function _filterByPriceRange(&$layerFilters, $collection, $params)
    {
        $priceRanges = $this->_getPriceRanges($collection);
        $filters     = [];
        $totalCount  = 0;
        $maxIndex    = 0;
        if ($this->simiObjectManager->get('Simi\Simiconnector\Helper\Data')->countArray($priceRanges['counts']) > 0) {
            $maxIndex = max(array_keys($priceRanges['counts']));
        }
        $countArr = $priceRanges['counts'];
        ksort($countArr);
        foreach ($countArr as $index => $count) {
            if ($index === '' || $index == 1) {
                $index = 1;
                $totalCount += $count;
            } else {
                $totalCount = $count;
            }
            if (isset($params['layer']['price'])) {
                $prices    = explode('-', $params['layer']['price']);
                $fromPrice = $prices[0];
                $toPrice   = $prices[1];
            } else {
                $fromPrice = $priceRanges['range'] * ($index - 1);
                $toPrice   = $priceRanges['range'] * ($index);
            }

            if ($index >= 1) {
                $filters[$index] = [
                    'value' => $fromPrice . '-' . $toPrice,
                    'label' => $this->_renderRangeLabel($fromPrice, $toPrice),
                    'count' => (int) ($totalCount)
                ];
            }
        }
        if (
            $this->simiObjectManager
            ->get('Simi\Simiconnector\Helper\Data')
            ->countArray($filters) >= 1
        ) {
            $priceAttributes = $this->simiObjectManager->get('\Magento\Eav\Model\Config')->getAttribute('catalog_product', 'price');
            $layerFilters[] = [
                'attribute' => 'price',
                'title'     => __('Price'),
                'filter'    => array_values($filters),
                'position'  => $priceAttributes->getPosition()
            ];
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
                //handle when maxprice is lower than the start of the range
                if ($range * $key <= $maxPrice) {
                    $newCounts[$key + 1] = $counts[$key];
                }
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


    protected function getChildrenIdsFromParentIds($arrayIDs, $resource)
    {
        $childProductsIds = [];
        if ($arrayIDs && count($arrayIDs)) {
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
        return $childProductsIds;
    }
}
