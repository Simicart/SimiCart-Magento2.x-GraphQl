<?php
namespace Simi\SimiconnectorGraphQl\Plugin\Controller;

class GraphQlPlugin
{
    /** @var \Magento\Framework\App\AreaList $areaList */
    private $areaList;

    /** @var \Magento\Framework\App\State $appState */
    private $appState;

    public function __construct(
        \Magento\Framework\App\AreaList $areaList,
        \Magento\Framework\App\State $appState
    )
    {
        $this->areaList = $areaList;
        $this->appState = $appState;
    }

    public function beforeDispatch(\Magento\GraphQl\Controller\GraphQl $subject)
    {
        $area = $this->areaList->getArea($this->appState->getAreaCode());
        $area->load(\Magento\Framework\App\Area::PART_TRANSLATE);
    }
}