<?php declare(strict_types=1);

namespace Shopware\Storefront\Bridge\Product\Repository;

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermsQuery;
use Shopware\Api\Search\Sorting\FieldSorting;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Repository\ProductRepository;
use Shopware\Product\Searcher\ProductSearchResult;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicCollection;
use Shopware\ProductMedia\Repository\ProductMediaRepository;
use Shopware\ProductMedia\Searcher\ProductMediaSearchResult;
use Shopware\ProductPrice\Struct\ProductDetailPriceBasicCollection;
use Shopware\ProductPrice\Struct\ProductPriceBasicCollection;
use Shopware\Storefront\Bridge\Product\Struct\ProductBasicStruct;

class StorefrontProductRepository
{
    /**
     * @var ProductRepository
     */
    private $repository;

    /**
     * @var PriceCalculator
     */
    private $priceCalculator;

    /**
     * @var ProductMediaRepository
     */
    private $productMediaRepository;

    public function __construct(
        ProductRepository $repository,
        PriceCalculator $priceCalculator,
        ProductMediaRepository $productMediaRepository
    ) {
        $this->repository = $repository;
        $this->priceCalculator = $priceCalculator;
        $this->productMediaRepository = $productMediaRepository;
    }

    public function read(array $uuids, ShopContext $context): ProductBasicCollection
    {
        $products = $this->repository->readBasic($uuids, $context->getTranslationContext());

        $media = $this->fetchMedia($uuids, $context);

        $listingProducts = new ProductBasicCollection();

        foreach ($products as $base) {
            $product = ProductBasicStruct::createFrom($base);

            $taxRules = new TaxRuleCollection([
                new PercentageTaxRule($product->getTax()->getRate(), 100),
            ]);

            $product->setPrices(
                $this->calculatePrices(
                    $taxRules,
                    $this->filterCustomerPrices($product->getPrices(), $context),
                    $context
                )
            );
            $product->setListingPrices(
                $this->calculatePrices(
                    $taxRules,
                    $this->filterCustomerPrices($product->getListingPrices(), $context),
                    $context
                )
            );

            $product->setMedia($media->filterByProductUuid($product->getUuid()));

            $listingProducts->add($product);
        }

        return $listingProducts;
    }

    public function search(Criteria $criteria, ShopContext $context): ProductSearchResult
    {
        $uuids = $this->repository->searchUuids($criteria, $context->getTranslationContext());

        $products = $this->read($uuids->getUuids(), $context);

        $result = new ProductSearchResult($products->getElements());
        $result->setTotal($uuids->getTotal());

        return $result;
    }

    /**
     * @param array       $uuids
     * @param ShopContext $context
     *
     * @return ProductMediaSearchResult
     */
    protected function fetchMedia(array $uuids, ShopContext $context): ProductMediaSearchResult
    {
        /** @var ProductMediaSearchResult $media */
        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_media.productUuid', $uuids));
        $criteria->addSorting(new FieldSorting('product_media.isCover', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('product_media.position'));

        return $this->productMediaRepository->search($criteria, $context->getTranslationContext());
    }

    /**
     * @param ProductDetailPriceBasicCollection|ProductListingPriceBasicCollection $prices
     * @param ShopContext                                                          $context
     *
     * @return ProductDetailPriceBasicCollection|ProductListingPriceBasicCollection
     */
    private function filterCustomerPrices($prices, ShopContext $context)
    {
        $current = $prices->filterByCustomerGroupUuid(
            $context->getCurrentCustomerGroup()->getUuid()
        );
        if ($current->count() > 0) {
            return $current;
        }

        return $prices->filterByCustomerGroupUuid(
            $context->getFallbackCustomerGroup()->getUuid()
        );
    }

    /**
     * @param TaxRuleCollection                                              $taxRules
     * @param ProductPriceBasicCollection|ProductListingPriceBasicCollection $prices
     * @param ShopContext                                                    $context
     *
     * @return ProductListingPriceBasicCollection|ProductPriceBasicCollection
     */
    private function calculatePrices(TaxRuleCollection $taxRules, $prices, ShopContext $context)
    {
        foreach ($prices as $price) {
            $calculated = $this->priceCalculator->calculate(
                new PriceDefinition($price->getPrice(), $taxRules),
                $context
            );

            $price->setPrice($calculated->getTotalPrice());
        }

        return $prices;
    }
}