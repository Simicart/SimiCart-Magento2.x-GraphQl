<?php

namespace Simi\SimiconnectorGraphQl\Model\Resolver\Theme;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Model\ResourceModel\Report\Bestsellers\CollectionFactory as BestSellersCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;


class ProductList implements ResolverInterface
{
    protected $storeManager;
    protected $collectionVisibility;
    protected $imageHelper;
    protected $collectionProductList;
    protected $productListApi;

    /**
     * @param DataProvider\Faq $faqRepository
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Simi\Simiconnector\Model\ResourceModel\Visibility\CollectionFactory $collectionVisibility,
        \Simi\Simiconnector\Helper\Data $helper,
        \Simi\Simiconnector\Model\ResourceModel\Productlist\CollectionFactory $collectionProductList,
        \Simi\Simiconnector\Helper\Productlist $productlistApi
    )
    {
        $this->storeManager = $storeManager;
        $this->collectionVisibility = $collectionVisibility;
        $this->imageHelper = $helper;
        $this->collectionProductList = $collectionProductList;
        $this->productListApi = $productlistApi;

    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $typeID = $this->imageHelper->getVisibilityTypeId('productlist');
        $productListCollection = $this->collectionProductList->create()->addFieldToFilter('list_status', '1')
            ->applyAPICollectionFilter('simiconnector_visibility', $typeID, (int)$context->getExtensionAttributes()->getStore()->getId());
        $returnProductList = $productListCollection->getData();

        if (sizeof($returnProductList) == 0) {
            return null;
        } else {
            //reorder the data base on the attribute sort_order
            $finalResult = [];
            usort($returnProductList, $this->build_sorter('sort_order'));
            for ($i = 0; $i < sizeof($returnProductList); $i++) {
                $productListUrl = null;
                $productListUrlTablet = null;
                try {
                    if ($returnProductList[$i]['list_image']) {
                        $imagesize = getimagesize(BP . '/pub/media/' . $returnProductList[$i]["list_image"]);
                        $returnProductList[$i]['width'] = $imagesize[0];
                        $returnProductList[$i]['height'] = $imagesize[1];
                        $productListFileName = str_replace("Simiconnector", "", $returnProductList[$i]["list_image"]);
                        //get the url file by helper
                        $productListUrl = $this->imageHelper->getBaseUrl() . $productListFileName;
                    }
                    if ($returnProductList[$i]['list_image_tablet']) {
                        $imagesize = getimagesize(BP . '/pub/media/' . $returnProductList[$i]['list_image_tablet']);
                        $returnProductList[$i]['width_tablet'] = $imagesize[0];
                        $returnProductList[$i]['height_tablet'] = $imagesize[1];
                        $productListFileNameTablet = str_replace("Simiconnector", "", $returnProductList[$i]["list_image_tablet"]);
                        //get the url file by helper
                        $productListUrlTablet = $this->imageHelper->getBaseUrl() . $productListFileNameTablet;
                    } else {
                        $productListUrlTablet = $productListUrl;
                    }
                } catch (\Exception $e) {
                    $returnProductList[$i]['function_warning'] = true;
                }


                $typeArray = $this->productListApi->getListTypeId();
                $listTypeIndex = $returnProductList[$i]["list_type"];
                $returnProductList[$i]['type_name'] = isset($typeArray[$listTypeIndex]) ? $typeArray[$listTypeIndex] : $typeArray[6];
                $productList = [
                    'productlist_id' => $returnProductList[$i]['productlist_id'],
                    'list_title' => $returnProductList[$i]['list_title'],
                    'list_image' => $productListUrl,
                    'list_image_tablet' => $productListUrlTablet,
                    'list_type' => $returnProductList[$i]['list_type'],
                    'list_products' => $returnProductList[$i]['list_products'],
                    'list_status' => $returnProductList[$i]['list_status'],
                    'sort_order' => $returnProductList[$i]['sort_order'],
                    'matrix_width_percent' => $returnProductList[$i]['matrix_width_percent'],
                    'matrix_height_percent' => $returnProductList[$i]['matrix_height_percent'],
                    'matrix_width_percent_tablet' => $returnProductList[$i]['matrix_width_percent_tablet'],
                    'matrix_height_percent_tablet' => $returnProductList[$i]['matrix_height_percent_tablet'],
                    'matrix_row' => $returnProductList[$i]['matrix_height_percent_tablet'],
                    'category_id' => $returnProductList[$i]['category_id'],
                    'entity_id' => $returnProductList[$i]['entity_id'],
                    'content_type' => $returnProductList[$i]['content_type'],
                    'item_id' => $returnProductList[$i]['item_id'],
                    'store_view_id' => $returnProductList[$i]['store_view_id'],
                    'width' => isset($returnProductList[$i]['width']) ? $returnProductList[$i]['width'] : null,
                    'height' => isset($returnProductList[$i]['height']) ? $returnProductList[$i]['height'] : null,
                    'width_tablet' => isset($returnProductList[$i]['width_tablet']) ? $returnProductList[$i]['width_tablet'] : null,
                    'height_tablet' => isset($returnProductList[$i]['height_tablet']) ? $returnProductList[$i]['height_tablet'] : null,
                    'type_name' => $returnProductList[$i]['type_name']
                ];

                array_push($finalResult, $productList);
            }
            return $finalResult;
        }

    }

    //function to sort the array by it's attributes
    private function build_sorter($key)
    {
        return function ($a, $b) use ($key) {
            return strnatcmp($a[$key], $b[$key]);
        };
    }

}