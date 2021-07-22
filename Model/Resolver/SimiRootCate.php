<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver;

use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ExtractDataFromCategoryTree;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;

/**
 * Category tree field resolver, used for GraphQL request processing.
 */
class SimiRootCate implements ResolverInterface
{
    /**
     * Name of type in GraphQL
     */
    const CATEGORY_INTERFACE = 'CategoryInterface';

    /**
     * @var \Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\CategoryTree
     */
    private $categoryTree;

    /**
     * @var ExtractDataFromCategoryTree
     */
    private $extractDataFromCategoryTree;
    private $storeManager;


    /**
     * @param \Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\CategoryTree $categoryTree
     * @param ExtractDataFromCategoryTree $extractDataFromCategoryTree
     */
    public function __construct(
        \Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\CategoryTree $categoryTree,
        ExtractDataFromCategoryTree $extractDataFromCategoryTree,
        \Magento\Framework\ObjectManagerInterface $simiObjectManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->categoryTree = $categoryTree;
        $this->extractDataFromCategoryTree = $extractDataFromCategoryTree;
        $this->simiObjectManager = $simiObjectManager;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (isset($value[$field->getName()])) {
            return $value[$field->getName()];
        }

        $storeManager = $this->storeManager;
        $rootCategoryId = (int)$storeManager->getStore()->getRootCategoryId();
        $categoriesTree = $this->categoryTree->getTree($info, $rootCategoryId);

        if (empty($categoriesTree) || ($categoriesTree->count() == 0)) {
            throw new GraphQlNoSuchEntityException(__('Category doesn\'t exist'));
        }

        $result = $this->extractDataFromCategoryTree->execute($categoriesTree);
        return current($result);
    }
}
