<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver\DataProvider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\StoreConfigManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * StoreConfig field data provider, used for GraphQL request processing.
 */
class SimiCategoryCmsDataProvider extends DataProviderInterface
{
	public $simiObjectManager;
	public $simiLayout;
	public $categoryFactory;
	public $blockFactory;
	public $storeManager;

	public function __construct(
		\Magento\Framework\ObjectManagerInterface $simiObjectManager,
		\Magento\Framework\View\LayoutInterface $simiLayout,
		\Magento\Catalog\Model\CategoryFactory $categoryFactory,
		\Magento\Cms\Model\BlockFactory $blockFactory,
		\Magento\Store\Model\StoreManagerInterface $storeManager
	) {
		$this->simiObjectManager = $simiObjectManager;
		$this->simiLayout = $simiLayout;
		$this->categoryFactory = $categoryFactory;
		$this->blockFactory = $blockFactory;
		$this->storeManager = $storeManager;
	}
	/**
	 * Get store config data
	 *
	 * @return array
	 */
	public function getCategoryCms($catId){
		$categoryId = $this->storeManager->getStore()->getRootCategoryId();

		if($catId) {
			$categoryId = $catId;
		}

		$model = $this->categoryFactory->create();
		$category = $model->load($categoryId);
		$displayMode = $category->getDisplayMode();
		$landingPage = $category->getlandingPage();
		$cmsIdentifer = '';
		$cms = '';
		if($landingPage) {
			$blockModel = $this->blockFactory->create()->load($landingPage);
			$cmsIdentifer = $blockModel->getIdentifier();
			$block = $this->simiLayout
				->createBlock('Magento\Cms\Block\Block');
			$block->setBlockId($category['landing_page']);
			$cms = $block->toHtml();
		}
		return [
			'display_mode' => $displayMode,
			'cms_identifier' => $cmsIdentifer,
			'cms' => $cms
		];
	}
}
