<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * StoreConfig page field resolver, used for GraphQL request processing.
 */
class SimiCategoryCms implements ResolverInterface
{
    public $cmsCategory;

    public function __construct(
        \Simi\SimiconnectorGraphQl\Model\Resolver\DataProvider\SimiCategoryCmsDataProvider $cmsCategory
    ) {
        $this->cmsCategory = $cmsCategory;
    }
    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $categoryId = null;
        if($value && isset($value['id']) && $value['id']) {
            $categoryId = $value['id'];
        }
        return $this->cmsCategory->getCategoryCms($categoryId);
    }
}