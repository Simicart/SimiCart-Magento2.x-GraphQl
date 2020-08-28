<?php

/**
 * Connector data helper
 */

namespace Simi\SimiconnectorGraphQl\Helper;

class Products extends \Magento\Framework\App\Helper\AbstractHelper
{

    public $simiObjectManager;
    public $storeManager;
    public $builderQuery;
    public $data        = [];
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

    const XML_PATH_RANGE_STEP = 'catalog/layered_navigation/price_range_step';
    const MIN_RANGE_POWER     = 10;

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
        \Magento\Catalog\Model\Product $productModelFactory
    ) {

        $this->simiObjectManager = $simiObjectManager;
        $this->scopeConfig      = $scopeConfigInterface;
        $this->storeManager     = $storeManager;
        $this->productStatus     = $productStatus;
        $this->productVisibility = $productVisibility;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->searchCollection = $searchCollection;
        $this->priceHelper = $priceHelper;
        $this->imageHelper = $imageHelper;
        $this->stockHelper = $stockHelper;
        $this->categoryModelFactory = $categoryModelFactory;
        $this->productModelFactory = $productModelFactory;
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
        $categoryModel    = $this->categoryModelFactory->create()->load($id);
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
                $value  = explode('-', $value);
                $priceFilter = array();
                if (isset($value[0]))
                    $priceFilter['from'] = $value[0];
                if (isset($value[0]))
                    $priceFilter['to'] = $value[1];
                $collection->addFieldToFilter('price', $priceFilter);
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
                }elseif ($key == 'size' || $key == 'color') {
                    $this->filteredAttributes[$key] = $value;                    
                    # code...
                    $productIds = [];
                    $collectionChid         = $this->productCollectionFactory->create();
                  
                    $collectionChid->addAttributeToSelect('*')
                        ->addStoreFilter()
                        ->addAttributeToFilter('status', 1)
                        ->addFinalPrice();
                    if (is_array($value)) {
                        $insetArray = array();
                        foreach ($value as $child_value) {
                            $insetArray[] = array('finset'=> array($child_value));
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
                            $insetArray[] = array('finset'=> array($child_value));
                        }
                        $collection->addAttributeToFilter($key, $insetArray);
                    } else
                        $collection->addAttributeToFilter($key, ['finset' => $value]);
                }
            }
        }
    }

    public function getSearchProducts(&$collection, $params)
    {
        $searchCollection = $this->searchCollection;
        $searchCollection->addSearchFilter($params['filter']['q']);
        $collection = $searchCollection;
    }

    public function getLayerNavigator($collection = null, $params = null)
    {
        if (!$collection) {
            $collection = $this->builderQuery;
        }
        if (!$params) {
            $data       = $this->getData();
            $params = isset($data['params'])?$data['params']:array();
        }
        $attributeCollection = $this->attributeCollectionFactory->create();
        $attributeCollection
            ->addIsFilterableFilter()
            //->addVisibleFilter() //cody comment out jun152019
            //->addFieldToFilter('used_in_product_listing', 1) //cody comment out jun152019
            //->addFieldToFilter('is_visible_on_front', 1) //cody comment out jun152019
        ;
        if ($this->is_search)
            $attributeCollection->addFieldToFilter('is_filterable_in_search', 1);

        $allProductIds = $collection->getAllIds();
        $arrayIDs      = [];
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
            $filters            = [];
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
                'title'     => __('Categories'),
                'filter'    => ($filters),
            ];
        }

        $paramArray = (array)$params;
        $selectedFilters = $this->_getSelectedFilters();
        $selectableFilters = count($allProductIds)?
            $this->_getSelectableFilters($collection, $paramArray, $selectedFilters, $layerFilters):
            array()
        ;

        $layerArray = ['layer_filter' => $selectableFilters];
        if (count($selectedFilters) > 0) {
            $layerArray['layer_state'] = $selectedFilters;
        }

        return $layerArray;
    }

    public function _getSelectedFilters()
    {
        $selectedFilters   = [];
        foreach ($this->filteredAttributes as $key => $value) {
            if (($key == 'category_id') && is_array($value) &&
                (count($value)>=2)) {
                $value = $value[1];
                $category = $this->loadCategoryWithId($value);
                $selectedFilters[] = [
                    'value'=>$value,
                    'label'=>$category->getName(),
                    'attribute' => 'category_id',
                    'title'     => __('Categories'),
                ];
                continue;
            }
            if (($key == 'price') && is_array($value) &&
                (count($value)>=2)) {
                $selectedFilters[] = [
                    'value'=> implode('-', $value),
                    'label'=> $this->_renderRangeLabel($value[0], $value[1]),
                    'attribute' => 'price',
                    'title'     => __('Price')
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
                        $selectedFilters[]    = $layerFilter;
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
        $childProductsIds      = [];
        if ($arrayIDs && count($arrayIDs)) {
            $childProducts = $this->productCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('type_id', 'simple');
            $select = $childProducts->getSelect();
            $select->joinLeft(
                    array('link_table' => $collection->getResource()->getTable('catalog_product_super_link')),
                    'link_table.product_id = e.entity_id',
                    array('product_id', 'parent_id')
                );
            $select = $childProducts->getSelect();
            $select->where("link_table.parent_id IN (".implode(',', array_keys($arrayIDs)).")");
            foreach ($childProducts->getAllIds() as $allProductId) {
                $childProductsIds[$allProductId] = '1';
            }
        }

        foreach ($attributeCollection as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            $attributeOptions = [];
            $attributeValues  = $collection->getAllAttributeValues($attribute->getAttributeCode());
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
                            $attributeOptions[$optionId] ++;
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
                    $filters[]       = $option;
                }
            }

            if (count($filters) >= 1) {
                $titleFilters[] = $attribute->getDefaultFrontendLabel();
                $layerFilters[] = [
                    'attribute' => $attribute->getAttributeCode(),
                    'title'     => $attribute->getDefaultFrontendLabel(),
                    'filter'    => $filters,
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

        $index    = 1;
        $counts = [];
        do {
            $range  = pow(10, strlen(floor($maxPrice)) - $index);
            $counts = $collection->getAttributeValueCountByRange('price', $range);
            $index++;
        } while ($range > self::MIN_RANGE_POWER && count($counts) < 2 && $index <= 2);

        //re-forming array
        if (isset($counts[''])) {
            $counts[0] = $counts[''];
            unset($counts['']);
            $newCounts = [];
            foreach ($counts as $key => $count) {
                $newCounts[$key+1] = $counts[$key];
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
        $helper             = $this->priceHelper;
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
}
