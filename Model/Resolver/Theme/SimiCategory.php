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


class SimiCategory implements ResolverInterface
{
    protected $storeManager;
    protected $collectionVisibility;
    protected $imageHelper;
    protected $collectionCategory;
    protected $homecategoriesApi;

    /**
     * @param DataProvider\Faq $faqRepository
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Simi\Simiconnector\Model\ResourceModel\Visibility\CollectionFactory $collectionVisibility,
        \Simi\Simiconnector\Helper\Data $helper,
        \Simi\Simiconnector\Model\ResourceModel\Simicategory\CollectionFactory $collectionCategory,
        \Simi\Simiconnector\Model\Api\Homecategories $categoriesApi
    )
    {
        $this->storeManager = $storeManager;
        $this->collectionVisibility = $collectionVisibility;
        $this->imageHelper = $helper;
        $this->collectionCategory = $collectionCategory;
        $this->homecategoriesApi = $categoriesApi;

    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $typeID = $this->imageHelper->getVisibilityTypeId('homecategory');
        $categoryCollection = $this->collectionCategory->create()->addFieldToFilter('status', '1')
            ->applyAPICollectionFilter('simiconnector_visibility', $typeID, (int)$context->getExtensionAttributes()->getStore()->getId());
        $returnHomeCategory = $categoryCollection->getData();

        if (sizeof($returnHomeCategory) == 0) {
            return null;
        } else {
            $finalResult = [];
            usort($returnHomeCategory, $this->build_sorter('sort_order'));
            for ($i = 0; $i < sizeof($returnHomeCategory); $i++) {
                $categoriesUrl = null;
                $categoriesUrlTablet = null;
                try {
                    if ($returnHomeCategory[$i]['simicategory_filename']) {
                        $imagesize = getimagesize(BP . '/pub/media/' . $returnHomeCategory[$i]['simicategory_filename']);
                        $returnHomeCategory[$i]['width'] = $imagesize[0];
                        $returnHomeCategory[$i]['height'] = $imagesize[1];
                        $categoriesFileName = str_replace("Simiconnector", "", $returnHomeCategory[$i]["simicategory_filename"]);
                        $categoriesUrl = $this->imageHelper->getBaseUrl() . $categoriesFileName;
                    }
                    if ($returnHomeCategory[$i]['simicategory_filename_tablet']) {
                        $imagesize = getimagesize(BP . '/pub/media/' . $returnHomeCategory[$i]['simicategory_filename_tablet']);
                        $returnHomeCategory[$i]['width_tablet'] = $imagesize[0];
                        $returnHomeCategory[$i]['height_tablet'] = $imagesize[1];
                        $categoriesFileNameTablet = str_replace("Simiconnector", "", $returnHomeCategory[$i]["simicategory_filename_tablet"]);
                        $categoriesUrlTablet = $this->imageHelper->getBaseUrl() . $categoriesFileNameTablet;
                    } else {
                        $categoriesUrlTablet = $categoriesUrl;
                    }
                } catch (\Exception $e) {
                    $returnHomeCategory[$i]['function_warning'] = true;
                }
                $categoryModel = $this->homecategoriesApi->loadCategoryWithId($returnHomeCategory[$i]['category_id']);
                $returnHomeCategory[$i]['url_path'] = $categoryModel->getUrlPath();
                $returnHomeCategory[$i]['cat_name'] = $categoryModel->getName();
                $childCollection = $this->homecategoriesApi->getVisibleChildren($returnHomeCategory[$i]['category_id']);
                if ($this->imageHelper->countCollection($childCollection)) {
                    $returnHomeCategory[$i]['has_children'] = true;
                } else {
                    $returnHomeCategory[$i]['has_children'] = false;
                }


                $category = [
                    'simicategory_id' => $returnHomeCategory[$i]['simicategory_id'],
                    'simicategory_name' => $returnHomeCategory[$i]['simicategory_name'],
                    'simicategory_filename' => $categoriesUrl,
                    'simicategory_filename_tablet' => $categoriesUrlTablet,
                    'category_id' => $returnHomeCategory[$i]['category_id'],
                    'status' => $returnHomeCategory[$i]['status'],
                    'website_id' => $returnHomeCategory[$i]['website_id'],
                    'storeview_id' => $returnHomeCategory[$i]['storeview_id'],
                    'sort_order' => $returnHomeCategory[$i]['sort_order'],
                    'matrix_width_percent' => $returnHomeCategory[$i]['matrix_width_percent'],
                    'matrix_height_percent' => $returnHomeCategory[$i]['matrix_height_percent'],
                    'matrix_width_percent_tablet' => $returnHomeCategory[$i]['matrix_width_percent_tablet'],
                    'matrix_height_percent_tablet' => $returnHomeCategory[$i]['matrix_height_percent_tablet'],
                    'matrix_row' => $returnHomeCategory[$i]['matrix_row'],
                    'content_type' => $returnHomeCategory[$i]['content_type'],
                    'store_view_id' => $returnHomeCategory[$i]['store_view_id'],
                    'width' => isset($returnHomeCategory[$i]['width']) ? $returnHomeCategory[$i]['width'] : null,
                    'height' => isset($returnHomeCategory[$i]['height']) ? $returnHomeCategory[$i]['height'] : null,
                    'width_tablet' => isset($returnHomeCategory[$i]['width_tablet']) ? $returnHomeCategory[$i]['width_tablet'] : null,
                    'height_tablet' => isset($returnHomeCategory[$i]['height_tablet']) ? $returnHomeCategory[$i]['height_tablet'] : null,
                    'has_children' => $returnHomeCategory[$i]['has_children'],
                    'cat_name' => $returnHomeCategory[$i]['cat_name'],
                    'url_path' => $returnHomeCategory[$i]['url_path'],
                    'total_category' => sizeof($returnHomeCategory)
                ];

                array_push($finalResult, $category);
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