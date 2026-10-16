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
 *
 * @category  Mage360
 * @package   Mage360_Brands
 * @copyright 2018 Mage360
 * @license   http://opensource.org/licenses/mit-license.php MIT License
 * @author    Qaiser Bashir
 */

namespace Mage360\Brands\Controller\View;

use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mage360\Brands\Model\Brands;
use Mage360\Brands\Model\ResourceModel\Brands\CollectionFactory as BrandsCollectionFactory;
use Mage360\Brands\Model\Source\Attributevalue;

class Index extends Action
{
    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfig;

    /**
     * @var PageFactory
     */
    public $resultPageFactory;

    /**
     * @var Resolver
     */
    public $layerResolver;

    /**
     * @var BrandsCollectionFactory
     */
    public $brandsCollectionFactory;

    /**
     * @var Attributevalue
     */
    public $attributevalue;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param Context                 $context
     * @param PageFactory             $resultPageFactory
     * @param ScopeConfigInterface    $scopeConfig
     * @param Resolver                $layerResolver
     * @param BrandsCollectionFactory $brandsCollectionFactory
     * @param StoreManagerInterface   $storeManager
     * @param Attributevalue          $attributevalue
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        ScopeConfigInterface $scopeConfig,
        Resolver $layerResolver,
        BrandsCollectionFactory $brandsCollectionFactory,
        StoreManagerInterface $storeManager,
        Attributevalue $attributevalue
    ) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
        $this->scopeConfig = $scopeConfig;
        $this->layerResolver = $layerResolver;
        $this->brandsCollectionFactory = $brandsCollectionFactory;
        $this->storeManager = $storeManager;
        $this->attributevalue = $attributevalue;
    }

    public function execute()
    {
        $brand = $this->getBrandDetails($this->resolveUrlKey());
        $attributeCode = null;

        if ($brand !== null && !empty($brand->getBrandId()) && $brand->getAttributeId()) {
            $attributeCode = $this->attributevalue->getAttribute()->getAttributeCode();
        }

        /**
         * Unknown/inactive brand or no brand attribute configured:
         * show the regular 404 page instead of rendering a half-empty layout.
         */
        if ($brand === null || empty($attributeCode)) {
            return $this->resultFactory->create(ResultFactory::TYPE_FORWARD)->forward('noroute');
        }

        /**
         * The layer has to be set up BEFORE the layout is built (first getLayout() call below),
         * because the layered navigation and the product list resolve the layer in their constructors.
         *
         * Product list and layered navigation share ONE product collection (the one of the category layer,
         * backed by the search engine). The brand is applied as an additional attribute filter on it,
         * so the filters in the sidebar always match the products shown in the list.
         * This replaces the former hand-built DB collection and the vendor patch of SearchResultApplier.
         */
        $this->layerResolver->create(Resolver::CATALOG_LAYER_CATEGORY);
        $layer = $this->layerResolver->get();
        $layer->setCurrentCategory($this->storeManager->getStore()->getRootCategoryId());
        $layer->getProductCollection()->addFieldToFilter($attributeCode, $brand->getAttributeId());

        $result = $this->resultPageFactory->create();

        $brandBlock = $result->getLayout()->getBlock('Brands.Brand');
        if ($brandBlock) {
            /**
             * pass $brand to the block so it's accessible in phtml.
             */
            $brandBlock->setBrand($brand);
        }

        /**
         * Set Title and Meta description and keywords.
         */
        $result->getConfig()->getTitle()->set($brand->getName());
        $result->getConfig()->setDescription((string)$brand->getSeoDesc());
        $result->getConfig()->setKeywords((string)$brand->getSeoKeyword());

        if ($this->scopeConfig->isSetFlag(Brands::CONFIG_BREADCRUMB, ScopeInterface::SCOPE_STORE)) {
            /**
             * @var \Magento\Theme\Block\Html\Breadcrumbs $breadcrumbsBlock
             */
            $breadcrumbsBlock = $result->getLayout()->getBlock('breadcrumbs');
            if ($breadcrumbsBlock) {
                $breadcrumbsBlock->addCrumb(
                    'home',
                    [
                        'label' => __('Home'),
                        'link'  => $this->_url->getUrl('')
                    ]
                );
                $breadcrumbsBlock->addCrumb(
                    'brands_home',
                    [
                        'link'  => $this->_url->getUrl(
                            $this->scopeConfig->getValue(Brands::CONFIG_MAIN_URL_KEY, ScopeInterface::SCOPE_STORE)
                        ),
                        'label' => $this->scopeConfig->getValue(
                            Brands::CONFIG_MENU_TITLE,
                            ScopeInterface::SCOPE_STORE
                        ),
                    ]
                );
                $breadcrumbsBlock->addCrumb(
                    'brand',
                    [
                        'label' => $brand->getName(),
                    ]
                );
            }
        }

        return $result;
    }

    /**
     * Extract the brand url key from the requested url (/brand/<url_key>.html).
     * The URL rewrite maps it to brands/view/index, so the original request URL is used.
     *
     * @return string|null
     */
    private function resolveUrlKey()
    {
        $path = (string)parse_url((string)$this->_url->getCurrentUrl(), PHP_URL_PATH);

        if (preg_match('#/' . preg_quote(Brands::URL_PREFIX, '#') . '/([^/]+?)(?:\.html)?/?$#', $path, $matches)) {
            return rawurldecode($matches[1]);
        }

        return null;
    }

    /**
     * Return the active brand for the given url key or null.
     *
     * @param string|null $urlKey
     * @return Brands|null
     */
    private function getBrandDetails($urlKey)
    {
        if ($urlKey === null || $urlKey === '') {
            return null;
        }

        $collection = $this->brandsCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('is_active', Brands::STATUS_ENABLED)
            ->addFieldToFilter('url_key', $urlKey)
            ->setPageSize(1);

        $brand = $collection->getFirstItem();

        return $brand && $brand->getId() ? $brand : null;
    }
}
