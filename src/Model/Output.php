<?php
declare(strict_types=1);
/**
 * Mage360_Brands extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/mit-license.php
 */
namespace Mage360\Brands\Model;

class Output
{
    /**
     * @var \Magento\Widget\Model\Template\Filter\Interceptor
     */
    public $templateProcessor;

    /**
     * @param \Magento\Widget\Model\Template\Filter\Interceptor $templateProcessor
     */
    public function __construct(
        \Magento\Widget\Model\Template\Filter\Interceptor $templateProcessor
    ) {
        $this->templateProcessor = $templateProcessor;
    }

    /**
     * @param $string
     * @return string
     */
    public function filterOutput($string)
    {
        return $this->templateProcessor->filter($string);
    }
}
