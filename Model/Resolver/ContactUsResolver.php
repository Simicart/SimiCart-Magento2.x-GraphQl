<?php

namespace Simi\SimiconnectorGraphQl\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Simi\SimiconnectorGraphQl\Model\Resolver\DataProvider\SimiContactUsDataprovider;

class ContactUsResolver implements ResolverInterface
{
    private $contactUsDataProvider;

    /**
     * @var SimiContactUsDataprovider
     */
    public function __construct(
        SimiContactUsDataprovider $contactUsDataProvider
    )
    {
        $this->contactUsDataProvider = $contactUsDataProvider;
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
    )
    {
        if (!isset($args['input'])) {
            throw new GraphQlInputException(__('Specify the "input" value.'));
        }
        try {
            return $this->contactUsDataProvider->contactUs($args['input']);
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()), $e);
        }

    }
}