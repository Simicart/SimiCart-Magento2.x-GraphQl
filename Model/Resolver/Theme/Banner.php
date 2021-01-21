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


class Banner implements ResolverInterface
{
    protected $storeManager;
    protected $collectionVisibility;
    protected $collectionBanner;
    protected $imageHelper;
    protected $bannerCollection;
    protected $categoryModel;
    protected $productModel;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Simi\Simiconnector\Model\ResourceModel\Visibility\CollectionFactory $collectionVisibility,
        \Simi\Simiconnector\Model\ResourceModel\Banner\CollectionFactory $collectionBanner,
        \Simi\Simiconnector\Helper\Data $helper,
        \Magento\Catalog\Model\Category $categoryModel,
        \Magento\Catalog\Model\Product $productModel
    )
    {
        $this->storeManager = $storeManager;
        $this->collectionVisibility = $collectionVisibility;
        $this->collectionBanner = $collectionBanner;
        $this->imageHelper = $helper;
        $this->productModel = $productModel;
        $this->categoryModel = $categoryModel;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $typeID = $this->imageHelper->getVisibilityTypeId('banner');
        $bannerCollection = $this->collectionBanner->create()->addFieldToFilter('status', '1')
            ->applyAPICollectionFilter('simiconnector_visibility', $typeID, (int)$context->getExtensionAttributes()->getStore()->getId());
        $returnbanner = $bannerCollection->getData();
        //if no data is stored -> return null
        if (sizeof($returnbanner) == 0) {
            return null;
        } else {
            $finalResult = [];
            //reorder the data base on the sort_order attribute
            usort($returnbanner, $this->build_sorter('sort_order'));

            for ($i = 0; $i < sizeof($returnbanner); $i++) {
                // barnner url return by storeManager->getStore()->getBaseUrl(). name of the file
                /* 
                $bannerUrl = $storeManager->getStore()->
                getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).$returnbanner[$i]["banner_name"];
                // banner tablet url
                $bannerUrlTablet = $storeManager->getStore()->
                getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).$returnbanner[$i]["banner_name_tablet"];
                */

                //BP is represent for basic path, direct to the root of the folder, in this case it is : /var/www/html/magento2, this return all the attribute of the image
                $imageAttribute = getimagesize(BP . '/pub/media/' . $returnbanner[$i]['banner_name']);
                $bannerUrl = null;
                $bannernametablet = null;
                try {
                    if ($returnbanner[$i]['banner_name']) {
                        $imagesize = getimagesize(BP . '/pub/media/' . $returnbanner[$i]['banner_name']);
                        $returnbanner[$i]['width'] = $imagesize[0];
                        $returnbanner[$i]['height'] = $imagesize[1];
                        $bannername = str_replace("Simiconnector", "", $returnbanner[$i]["banner_name"]);
                        $bannerUrl = $this->imageHelper->getBaseUrl() . $bannername;
                    }
                    if ($returnbanner[$i]['banner_name_tablet']) {
                        $imagesize = getimagesize(BP . '/pub/media/' . $returnbanner[$i]['banner_name_tablet']);
                        $returnbanner[$i]['width_tablet'] = $imagesize[0];
                        $returnbanner[$i]['height_tablet'] = $imagesize[1];
                        $bannernametablet = str_replace("Simiconnector", "", $returnbanner[$i]["banner_name_tablet"]);
                        $bannernametablet = $this->imageHelper->getBaseUrl() . $bannernametablet;
                    } else {
                        $bannernametablet = $bannerUrl;
                    }
                } catch (\Exception $e) {
                    $returnbanner[$i]['function_warning'] = true;
                }

                //if this banner type is 2 -> set the url path and if it's 3 set the data url key
                if ($returnbanner[$i]['type'] == 2) {
                    $categoryModel = $this->categoryModel->load($returnbanner[$i]['category_id']);
                    $returnbanner[$i]['has_children'] = $categoryModel->hasChildren();
                    $returnbanner[$i]['cat_name'] = $categoryModel->getName();
                    $returnbanner[$i]['url_path'] = $categoryModel->getUrlPath();
                    $returnbanner[$i]['url_key'] = null;
                } else if ($returnbanner[$i]['type'] == 1) {
                    $productModel = $this->productModel->load($returnbanner[$i]['product_id']);
                    if ($productModel->getId()) {
                        $returnbanner[$i]['url_key'] = $productModel->getData('url_key');
                        $returnbanner[$i]['has_children'] = null;
                        $returnbanner[$i]['cat_name'] = null;
                        $returnbanner[$i]['url_path'] = null;
                    }
                } else {
                    $returnbanner[$i]['has_children'] = null;
                    $returnbanner[$i]['cat_name'] = null;
                    $returnbanner[$i]['url_path'] = null;
                    $returnbanner[$i]['url_key'] = null;
                }

                //return data
                $banner = [
                    'banner_id' => $returnbanner[$i]['banner_id'],
                    'banner_name' => $bannerUrl,
                    'banner_url' => $returnbanner[$i]['banner_url'],
                    'banner_name_tablet' => $bannernametablet,
                    'banner_title' => $returnbanner[$i]['banner_title'],
                    'status' => $returnbanner[$i]['status'],
                    'website_id' => $returnbanner[$i]['website_id'],
                    'category_id' => $returnbanner[$i]['category_id'],
                    'product_id' => $returnbanner[$i]['product_id'],
                    'sort_order' => $returnbanner[$i]['sort_order'],
                    'type' => (int)$returnbanner[$i]['type'],
                    'entity_id' => $returnbanner[$i]['entity_id'],
                    'content_type' => $returnbanner[$i]['content_type'],
                    'item_id' => $returnbanner[$i]['item_id'],
                    'store_view_id' => $returnbanner[$i]['store_view_id'],
                    'width' => isset($returnbanner[$i]['width']) ? $returnbanner[$i]['width'] : null,
                    'height' => isset($returnbanner[$i]['height']) ? $returnbanner[$i]['height'] : null,
                    'width_tablet' => isset($returnbanner[$i]['width_tablet']) ? $returnbanner[$i]['width_tablet'] : null,
                    'height_tablet' => isset($returnbanner[$i]['height_tablet']) ? $returnbanner[$i]['height_tablet'] : null,
                    'has_children' => $returnbanner[$i]['has_children'],
                    'cat_name' => $returnbanner[$i]['cat_name'],
                    'url_path' => $returnbanner[$i]['url_path'],
                    'url_key' => $returnbanner[$i]['url_key'],
                    'total_banner' => sizeof($returnbanner)
                ];
                array_push($finalResult, $banner);
            }

            return $finalResult;
        }
    }

    //this function soer the array by atribute key
    private function build_sorter($key)
    {
        return function ($a, $b) use ($key) {
            return strnatcmp((int)$a[$key], (int)$b[$key]);
        };
    }
    //load the categories with id
}