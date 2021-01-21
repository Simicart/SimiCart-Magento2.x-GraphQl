<?php

namespace Simi\SimiconnectorGraphQl\Plugin;

use Magento\Framework\Stdlib\ArrayManager;

class CustomizableOptionsDataProvider
{
    /**
     * @var ArrayManager
     */
    private $arrayManager;

    public function __construct(
        ArrayManager $arrayManager
    )
    {
        $this->arrayManager = $arrayManager;
    }

    public function aroundExecute($subject, $proceed, array $cartItemData)
    {
        $customizableOptions = $this->arrayManager->get('customizable_options', $cartItemData, []);

        $customizableOptionsData = [];
        foreach ($customizableOptions as $customizableOption) {
            if (isset($customizableOption['value_string'])) {
                $customizableOptionsData[$customizableOption['id']] = $this->convertCustomOptionValue(
                    $customizableOption['value_string']
                );
            }
        }

        return ['options' => $customizableOptionsData];
    }

    /**
     * Convert custom options value
     *
     * @param string $value
     *
     * @return string|array
     */
    private function convertCustomOptionValue(string $value)
    {
        $value = trim($value);
        if (substr($value, 0, 1) === "[" &&
            substr($value, strlen($value) - 1, 1) === "]") {
            $value = explode(',', substr($value, 1, -1));

            // huy kon customize custom option file
            $optionValueForFile = [];
            if (count($value) > 0 && substr($value[0], 0, 1) === "{") {
                foreach ($value as $val) {
                    $expObj = explode(':', $val);
                    if (count($expObj) > 1) {
                        $optKey = substr($expObj[0], 0, 1) === "{" ? substr($expObj[0], 1) : $expObj[0];
                        $optValue = substr($expObj[1], strlen($expObj[1]) - 1, 1) === "}" ? substr($expObj[1], 0, -1) : $expObj[1];
                        $optionValueForFile[trim($optKey, '"')] = trim($optValue, '"');
                    }
                }
            }

            if (count($optionValueForFile)) {
                return $optionValueForFile;
            }
        }

        return $value;
    }

}