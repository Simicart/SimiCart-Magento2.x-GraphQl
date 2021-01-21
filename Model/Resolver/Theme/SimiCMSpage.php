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


class SimiCMSpage implements ResolverInterface
{
    protected $storeManager;
    protected $collectionVisibility;
    protected $imageHelper;
    protected $collectionCMS;

    /**
     * @param DataProvider\Faq $faqRepository
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Simi\Simiconnector\Model\ResourceModel\Visibility\CollectionFactory $collectionVisibility,
        \Simi\Simiconnector\Helper\Data $helper,
        \Simi\Simiconnector\Model\ResourceModel\Cms\CollectionFactory $collectionCMS
    )
    {
        $this->storeManager = $storeManager;
        $this->collectionVisibility = $collectionVisibility;
        $this->imageHelper = $helper;
        $this->collectionCMS = $collectionCMS;

    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $typeID = $this->imageHelper->getVisibilityTypeId('cms');
        $CMScollection = $this->collectionCMS->create()->addFieldToFilter('cms_status', '1')
            ->applyAPICollectionFilter('simiconnector_visibility', $typeID, (int)$context->getExtensionAttributes()->getStore()->getId());
        $returnCms = $CMScollection->getData();

        if (sizeof($returnCms) == 0) {
            return null;
        } else {
            usort($returnCms, $this->build_sorter('sort_order'));
            //push the return categiries into the array
            $finalResult = [];

            for ($i = 0; $i < sizeof($returnCms); $i++) {
                $fileName = str_replace("Simiconnector", "", $returnCms[$i]["cms_image"]);
                if ($fileName) {
                    $cmsUrl = $this->imageHelper->getBaseUrl() . $fileName;
                } else {
                    $cmsUrl = null;
                }
                // die(var_dump($cmsUrl));
                $cms = [
                    'cms_id' => $returnCms[$i]['cms_id'],
                    'cms_image' => $cmsUrl,
                    'cms_title' => $returnCms[$i]['cms_title'],
                    'cms_content' => $returnCms[$i]['cms_content'],
                    'cms_status' => $returnCms[$i]['cms_status'],
                    'website_id' => $returnCms[$i]['website_id'],
                    'type' => $returnCms[$i]['type'],
                    'category_id' => $returnCms[$i]['category_id'],
                    'sort_order' => $returnCms[$i]['sort_order'],
                    'cms_script' => $returnCms[$i]['cms_script'],
                    'cms_url' => $returnCms[$i]['cms_url'],
                    'cms_meta_title' => $returnCms[$i]['cms_meta_title'],
                    'cms_meta_desc' => $returnCms[$i]['cms_meta_desc'],
                    'entity_id' => $returnCms[$i]['entity_id'],
                    'content_type' => $returnCms[$i]['content_type'],
                    'item_id' => $returnCms[$i]['item_id'],
                    'store_view_id' => $returnCms[$i]['store_view_id']
                ];
                array_push($finalResult, $cms);
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