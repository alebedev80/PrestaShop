require('module-alias/register');

const {expect} = require('chai');

// Import utils
const helper = require('@utils/helpers');
const loginCommon = require('@commonTests/loginBO');

// Import pages
const dashboardPage = require('@pages/BO/dashboard');
const generalPage = require('@pages/BO/shopParameters/general');
const multiStorePage = require('@pages/BO/advancedParameters/multistore');
const addShopPage = require('@pages/BO/advancedParameters/multistore/shop/add');
const shopPage = require('@pages/BO/advancedParameters/multistore/shop');

// Import data
const ShopFaker = require('@data/faker/shop');

// Import test context
const testContext = require('@utils/testContext');

const baseContext = 'functional_BO_advancedParameters_multistore_filterSortAndPaginationShops';

let browserContext;
let page;
const ShopData = new ShopFaker({name: 'todelete0', shopGroup: 'Default', categoryRoot: 'Home'});

/*
Enable multistore
Create 20 shops
Filter by : Id and shop name, shop group, root category, URL
Pagination between pages
Delete the created shop
Disable multistore
 */
describe('Filter, sort and pagination shops', async () => {
  // before and after functions
  before(async function () {
    browserContext = await helper.createBrowserContext(this.browser);
    page = await helper.newTab(browserContext);
  });

  after(async () => {
    await helper.closeBrowserContext(browserContext);
  });

  it('should login in BO', async function () {
    await loginCommon.loginBO(this, page);
  });

  // 1 : Enable multi store
  describe('Enable multistore', async () => {
    it('should go to \'Shop parameters > General\' page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'goToGeneralPage', baseContext);

      await dashboardPage.goToSubMenu(
        page,
        dashboardPage.shopParametersParentLink,
        dashboardPage.shopParametersGeneralLink,
      );

      await generalPage.closeSfToolBar(page);

      const pageTitle = await generalPage.getPageTitle(page);
      await expect(pageTitle).to.contains(generalPage.pageTitle);
    });

    it('should enable \'Multi store\'', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'enableMultiStore', baseContext);

      const result = await generalPage.setMultiStoreStatus(page, true);
      await expect(result).to.contains(generalPage.successfulUpdateMessage);
    });
  });

  // 2 : Go to multistore page
  describe('Go to multistore page and create the first shop', async () => {
    it('should go to \'Advanced parameters > Multi store\' page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'goToMultiStorePage', baseContext);

      await dashboardPage.goToSubMenu(
        page,
        dashboardPage.advancedParametersLink,
        dashboardPage.multistoreLink,
      );

      await multiStorePage.closeSfToolBar(page);

      const pageTitle = await multiStorePage.getPageTitle(page);
      await expect(pageTitle).to.contains(multiStorePage.pageTitle);
    });

    it('should go to add new shop page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'goToAddNewShopPage', baseContext);

      await multiStorePage.goToNewShopPage(page);

      const pageTitle = await addShopPage.getPageTitle(page);
      await expect(pageTitle).to.contains(addShopPage.pageTitleCreate);
    });

    it('should create shop', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'createFirstShop', baseContext);

      const textResult = await addShopPage.setShop(page, ShopData);
      await expect(textResult).to.contains(multiStorePage.successfulCreationMessage);
    });
  });

  // 3 : Create 20 shop
  new Array(20).fill(0, 0, 20).forEach((test, index) => {
    describe(`Create shop n°${index + 1}`, async () => {
      const ShopData = new ShopFaker({name: `todelete${index + 1}`, shopGroup: 'Default', categoryRoot: 'Home'});
      it('should go to add new shop page', async function () {
        await testContext.addContextItem(this, 'testIdentifier', `goToAddNewShopPage${index}`, baseContext);

        await shopPage.goToNewShopPage(page);

        const pageTitle = await addShopPage.getPageTitle(page);
        await expect(pageTitle).to.contains(addShopPage.pageTitleCreate);
      });

      it('should create shop', async function () {
        await testContext.addContextItem(this, 'testIdentifier', `createShop${index}`, baseContext);

        const textResult = await addShopPage.setShop(page, ShopData);
        await expect(textResult).to.contains(multiStorePage.successfulCreationMessage);
      });
    });
  });

  // 4 : filter shop
  describe('Filter shop table', async () => {
    [
      {args: {filterBy: 'id_shop', filterValue: 10}},
      {args: {filterBy: 'a!name', filterValue: 'todelete10'}},
      {args: {filterBy: 'gs!name', filterValue: 'Default'}},
      {args: {filterBy: 'cl!name', filterValue: 'Home'}},
      {args: {filterBy: 'url', filterValue: 'Click here to set a URL for this shop.'}},
    ].forEach((test, index) => {
      it(`should filter list by ${test.args.filterBy}`, async function () {
        await testContext.addContextItem(this, 'testIdentifier', `filterBy_${test.args.filterBy}`, baseContext);

        await shopPage.filterTable(page, test.args.filterBy, test.args.filterValue);

        const numberOfElementAfterFilter = await shopPage.getNumberOfElementInGrid(page);

        for (let i = 1; i <= numberOfElementAfterFilter; i++) {
          const textColumn = await shopPage.getTextColumn(page, i, test.args.filterBy);
          await expect(textColumn).to.contains(test.args.filterValue);
        }
      });

      it('should reset filter and check the number of shops', async function () {
        await testContext.addContextItem(this, 'testIdentifier', `resetFilter_${index}`, baseContext);

        const numberOfElement = await shopPage.resetAndGetNumberOfLines(page);
        await expect(numberOfElement).to.be.above(20);
      });
    });
  });

  // 5 : pagination
  describe('Pagination next and previous', async () => {
    it('should change the item number to 20 per page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'changeItemNumberTo20', baseContext);

      const paginationNumber = await shopPage.selectPaginationLimit(page, '20');
      expect(paginationNumber).to.equal('1');
    });

    it('should click on next', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'clickOnNext', baseContext);

      const paginationNumber = await shopPage.paginationNext(page);
      expect(paginationNumber).to.equal('2');
    });

    it('should click on previous', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'clickOnPrevious', baseContext);

      const paginationNumber = await shopPage.paginationPrevious(page);
      expect(paginationNumber).to.equal('1');
    });

    it('should change the item number to 50 per page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'changeItemNumberTo50', baseContext);

      const paginationNumber = await shopPage.selectPaginationLimit(page, '50');
      expect(paginationNumber).to.equal('1');
    });
  });

  // 6 : Delete shop created
  describe('delete all shops created', async () => {
    new Array(20).fill(0, 0, 20).forEach((test, index) => {
      it(`should delete the shop 'todelete${index}'`, async function () {
        await testContext.addContextItem(this, 'testIdentifier', `deleteShop${index}`, baseContext);

        await shopPage.filterTable(page, 'a!name', `todelete${index}`);

        const textResult = await shopPage.deleteShop(page, 1);
        await expect(textResult).to.contains(shopPage.successfulDeleteMessage);
      });
    });
  });

  // 7 : Disable multi store
  describe('Disable multistore', async () => {
    it('should go to "Shop parameters > General" page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'goToGeneralPage2', baseContext);

      await dashboardPage.goToSubMenu(
        page,
        dashboardPage.shopParametersParentLink,
        dashboardPage.shopParametersGeneralLink,
      );

      await generalPage.closeSfToolBar(page);

      const pageTitle = await generalPage.getPageTitle(page);
      await expect(pageTitle).to.contains(generalPage.pageTitle);
    });

    it('should disable "Multi store"', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'disableMultiStore', baseContext);

      const result = await generalPage.setMultiStoreStatus(page, false);
      await expect(result).to.contains(generalPage.successfulUpdateMessage);
    });
  });
});
