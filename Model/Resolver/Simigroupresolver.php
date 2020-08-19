<?php

declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use \Simi\SimiconnectorGraphQl\Model\Resolver\DataProvider\Simigroupdataprovider;

class Simigroupresolver implements ResolverInterface
{
    /**
     * @var groupDataProvider
     */
    private $groupDataProvider;

    /**
     * @param Simigroupdataprovider $Simigroupdataprovider
     */
    public function __construct(
        Simigroupdataprovider $groupDataProvider
    ) {
        $this->groupDataProvider = $groupDataProvider;
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
        return $this->groupDataProvider->getSimiGroupData($args);
    }
}
