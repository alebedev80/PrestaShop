<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Product\SpecificPrice\Validate;

use DateTime;
use PrestaShop\PrestaShop\Adapter\AbstractObjectModelValidator;
use PrestaShop\PrestaShop\Adapter\Country\Repository\CountryRepository;
use PrestaShop\PrestaShop\Adapter\Currency\Repository\CurrencyRepository;
use PrestaShop\PrestaShop\Adapter\Product\Combination\Repository\CombinationRepository;
use PrestaShop\PrestaShop\Adapter\Shop\Repository\ShopGroupRepository;
use PrestaShop\PrestaShop\Adapter\Shop\Repository\ShopRepository;
use PrestaShop\PrestaShop\Core\Domain\Country\ValueObject\CountryId;
use PrestaShop\PrestaShop\Core\Domain\Country\ValueObject\NoCountryId;
use PrestaShop\PrestaShop\Core\Domain\Currency\ValueObject\CurrencyId;
use PrestaShop\PrestaShop\Core\Domain\Currency\ValueObject\NoCurrencyId;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\ValueObject\CombinationId;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\ValueObject\NoCombinationId;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Exception\SpecificPriceConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\NoShopGroupId;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\NoShopId;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopGroupId;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopId;
use PrestaShop\PrestaShop\Core\Exception\CoreException;
use PrestaShop\PrestaShop\Core\Util\DateTime\DateTime as DateTimeUtil;
use SpecificPrice;

/**
 * Validates SpecificPrice properties using legacy object model
 */
class SpecificPriceValidator extends AbstractObjectModelValidator
{
    /**
     * @var ShopGroupRepository
     */
    private $shopGroupRepository;

    /**
     * @var ShopRepository
     */
    private $shopRepository;

    /**
     * @var CombinationRepository
     */
    private $combinationRepository;

    /**
     * @var CurrencyRepository
     */
    private $currencyRepository;

    /**
     * @var CountryRepository
     */
    private $countryRepository;

    /**
     * @param ShopGroupRepository $shopGroupRepository
     * @param ShopRepository $shopRepository
     * @param CombinationRepository $combinationRepository
     * @param CurrencyRepository $currencyRepository
     * @param CountryRepository $countryRepository
     */
    public function __construct(
        ShopGroupRepository $shopGroupRepository,
        ShopRepository $shopRepository,
        CombinationRepository $combinationRepository,
        CurrencyRepository $currencyRepository,
        CountryRepository $countryRepository
    ) {
        $this->shopGroupRepository = $shopGroupRepository;
        $this->shopRepository = $shopRepository;
        $this->combinationRepository = $combinationRepository;
        $this->currencyRepository = $currencyRepository;
        $this->countryRepository = $countryRepository;
    }

    /**
     * @param SpecificPrice $specificPrice
     *
     * @throws CoreException
     * @throws SpecificPriceConstraintException
     */
    public function validate(SpecificPrice $specificPrice): void
    {
        $this->validateSpecificPriceProperty($specificPrice, 'price', SpecificPriceConstraintException::INVALID_PRICE);
        $this->validateSpecificPriceProperty($specificPrice, 'reduction', SpecificPriceConstraintException::INVALID_REDUCTION_AMOUNT);
        $this->validateSpecificPriceProperty($specificPrice, 'reduction_tax', SpecificPriceConstraintException::INVALID_FROM_QUANTITY);
        $this->validateSpecificPriceProperty($specificPrice, 'reduction_type', SpecificPriceConstraintException::INVALID_REDUCTION_TYPE);
        $this->validateSpecificPriceProperty($specificPrice, 'from_quantity', SpecificPriceConstraintException::INVALID_FROM_QUANTITY);
        $this->validateSpecificPriceProperty($specificPrice, 'from', SpecificPriceConstraintException::INVALID_FROM_DATETIME);
        $this->validateSpecificPriceProperty($specificPrice, 'to', SpecificPriceConstraintException::INVALID_TO_DATETIME);
        $this->assertDateRangeIsNotInverse($specificPrice);

        $this->validateSpecificPriceProperty($specificPrice, 'id_cart', SpecificPriceConstraintException::INVALID_RELATION_ID);
        $this->validateSpecificPriceProperty($specificPrice, 'id_country', SpecificPriceConstraintException::INVALID_RELATION_ID);
        $this->validateSpecificPriceProperty($specificPrice, 'id_currency', SpecificPriceConstraintException::INVALID_RELATION_ID);
        $this->validateSpecificPriceProperty($specificPrice, 'id_customer', SpecificPriceConstraintException::INVALID_RELATION_ID);
        $this->validateSpecificPriceProperty($specificPrice, 'id_group', SpecificPriceConstraintException::INVALID_RELATION_ID);
        $this->validateSpecificPriceProperty($specificPrice, 'id_product', SpecificPriceConstraintException::INVALID_RELATION_ID);
        $this->validateSpecificPriceProperty($specificPrice, 'id_product_attribute', SpecificPriceConstraintException::INVALID_RELATION_ID);
        $this->validateSpecificPriceProperty($specificPrice, 'id_shop', SpecificPriceConstraintException::INVALID_RELATION_ID);
        $this->validateSpecificPriceProperty($specificPrice, 'id_shop_group', SpecificPriceConstraintException::INVALID_RELATION_ID);
        $this->validateSpecificPriceProperty($specificPrice, 'id_specific_price_rule', SpecificPriceConstraintException::INVALID_RELATION_ID);
        $this->assertRelatedEntitiesExist($specificPrice);
    }

    /**
     * @param SpecificPrice $specificPrice
     * @param string $property
     * @param int $errorCode
     *
     * @throws CoreException
     * @throws SpecificPriceConstraintException
     */
    private function validateSpecificPriceProperty(SpecificPrice $specificPrice, string $property, int $errorCode): void
    {
        $this->validateObjectModelProperty($specificPrice, $property, SpecificPriceConstraintException::class, $errorCode);
    }

    /**
     * Checks if date range values are not inverse. (range from not bigger than range to)
     *
     * @param SpecificPrice $specificPrice
     *
     * @throws SpecificPriceConstraintException
     */
    private function assertDateRangeIsNotInverse(SpecificPrice $specificPrice)
    {
        if (empty($specificPrice->from) || empty($specificPrice->to)) {
            return;
        }

        if (DateTimeUtil::NULL_DATETIME === $specificPrice->from || DateTimeUtil::NULL_DATETIME === $specificPrice->to) {
            return;
        }

        $from = new DateTime($specificPrice->from);
        $to = new DateTime($specificPrice->to);
        if ($from->diff($to)->invert) {
            throw new SpecificPriceConstraintException('The date time for specific price cannot be inverse', SpecificPriceConstraintException::INVALID_DATE_RANGE);
        }
    }

    /**
     * @param SpecificPrice $specificPrice
     */
    private function assertRelatedEntitiesExist(SpecificPrice $specificPrice): void
    {
        $shopGroupId = (int) $specificPrice->id_shop_group;
        if ($shopGroupId !== NoShopGroupId::NO_SHOP_GROUP_ID) {
            $this->shopGroupRepository->assertShopGroupExists(new ShopGroupId($shopGroupId));
        }

        $shopId = (int) $specificPrice->id_shop;
        if ($shopId !== NoShopId::NO_SHOP_ID) {
            $this->shopRepository->assertShopExists(new ShopId($shopId));
        }

        $combinationId = (int) $specificPrice->id_product_attribute;
        if ($combinationId !== NoCombinationId::NO_COMBINATION_ID) {
            $this->combinationRepository->assertCombinationExists(new CombinationId($combinationId));
        }

        $currencyId = (int) $specificPrice->id_currency;
        if ($currencyId !== NoCurrencyId::NO_CURRENCY_ID) {
            $this->currencyRepository->assertCurrencyExists(new CurrencyId($currencyId));
        }

        $countryId = (int) $specificPrice->id_country;
        if ($countryId !== NoCountryId::NO_COUNTRY_ID) {
            $this->countryRepository->assertCountryExists(new CountryId($countryId));
        }
        //@todo:
        //  group
        //  customer
    }
}
