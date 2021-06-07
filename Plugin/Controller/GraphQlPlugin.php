<?php
namespace Simi\SimiconnectorGraphQl\Plugin\Controller;

class GraphQlPlugin
{
    /** @var \Magento\Framework\App\AreaList $areaList */
    private $areaList;

    /** @var \Magento\Framework\App\State $appState */
    private $appState;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    private $localeResolver;
    
    /**
     * @var \Magento\Framework\TranslateInterface
     */
    private $translation;

    public function __construct(
        \Magento\Framework\App\AreaList $areaList,
        \Magento\Framework\App\State $appState,
        \Magento\Framework\TranslateInterface $translation,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->areaList = $areaList;
        $this->appState = $appState;
        $this->translation = $translation;
        $this->localeResolver = $localeResolver;
        $this->storeManager = $storeManager;
    }

    public function beforeDispatch(\Magento\GraphQl\Controller\GraphQl $subject, \Magento\Framework\App\RequestInterface $request)
    {
        $area = $this->areaList->getArea($this->appState->getAreaCode());
        $storeIdOrCode = $request->getHeader('store') ?: $this->storeManager->getStore()->getCode();
        $locale = $this->storeManager->getStore($storeIdOrCode)->getConfig('general/locale/code');
        $this->localeResolver->setLocale($locale);
        $this->translation->setLocale($locale);
        $area->load(\Magento\Framework\App\Area::PART_TRANSLATE);
    }
}