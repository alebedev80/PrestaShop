// Import utils
import testContext from '@utils/testContext';

// Import commonTests
import loginCommon from '@commonTests/BO/loginBO';

import myProfilePage from '@pages/BO/advancedParameters/team/myProfile';

import {expect} from 'chai';
import type {BrowserContext, Page} from 'playwright';
import {
  boDashboardPage,
  boModuleManagerPage,
  dataEmployees,
  dataModules,
  FakerEmployee,
  modPsGdprBoMain,
  modPsGdprBoTabHelp,
  utilsFile,
  utilsPlaywright,
} from '@prestashop-core/ui-testing';

const baseContext: string = 'modules_psgdpr_configuration_help';

describe('BO - Modules - GDPR: Help', async () => {
  let browserContext: BrowserContext;
  let page: Page;
  const employeeData: FakerEmployee = {...dataEmployees.defaultEmployee};

  before(async function () {
    browserContext = await utilsPlaywright.createBrowserContext(this.browser);
    page = await utilsPlaywright.newTab(browserContext);
  });

  after(async () => {
    await utilsPlaywright.closeBrowserContext(browserContext);
  });

  it('should login in BO', async function () {
    await loginCommon.loginBO(this, page);
  });

  it('should go to \'Modules > Module Manager\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToModuleManagerPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.modulesParentLink,
      boDashboardPage.moduleManagerLink,
    );
    await boModuleManagerPage.closeSfToolBar(page);

    const pageTitle = await boModuleManagerPage.getPageTitle(page);
    expect(pageTitle).to.contains(boModuleManagerPage.pageTitle);
  });

  it(`should search the module ${dataModules.psGdpr.name}`, async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'searchModule', baseContext);

    const isModuleVisible = await boModuleManagerPage.searchModule(page, dataModules.psGdpr);
    expect(isModuleVisible).to.eq(true);
  });

  it(`should go to the configuration page of the module '${dataModules.psGdpr.name}'`, async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToConfigurationPage', baseContext);

    await boModuleManagerPage.goToConfigurationPage(page, dataModules.psGdpr.tag);

    const pageTitle = await modPsGdprBoMain.getPageSubtitle(page);
    expect(pageTitle).to.eq(modPsGdprBoMain.pageSubTitle);
  });

  it('should display the tab "Help"', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'displayTabHelp', baseContext);

    const isTabVisible = await modPsGdprBoMain.goToTab(page, 5);
    expect(isTabVisible).to.be.equals(true);
  });

  [
    'Data accessibility',
    'Customer consent',
    'Data erasure',
  ].forEach((test: string, index: number) => {
    it(`should expand/shrink questions of the group "${test}"`, async function () {
      await testContext.addContextItem(this, 'testIdentifier', `expandShrinkQuestions${index}`, baseContext);

      const numQuestions = await modPsGdprBoTabHelp.getCountQuestions(page, test);
      expect(numQuestions).to.gt(0)
  
      for (let numQuestion = 1; numQuestion <= numQuestions; numQuestion++) {
        const isQuestionVisibleAfterOpen = await modPsGdprBoTabHelp.clickQuestion(page, test, numQuestion);
        expect(isQuestionVisibleAfterOpen).to.be.equals(true);

        const isQuestionVisibleAfterClose = await modPsGdprBoTabHelp.clickQuestion(page, test, numQuestion);
        expect(isQuestionVisibleAfterClose).to.be.equals(false);
      }
    });
  });

  it('should download the documentation in English', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'downloadDocEn', baseContext);

    const filePath = await modPsGdprBoTabHelp.downloadDocumentation(page);
    expect(filePath).to.not.eq(null);

    const doesFileExist = await utilsFile.doesFileExist(filePath, 5000);
    expect(doesFileExist).to.eq(true);

    const hasTitle = await utilsFile.isTextInPDF(filePath, 'User guide');
    expect(hasTitle).to.eq(true);

    const hasSubTitle = await utilsFile.isTextInPDF(filePath, 'OFFICIAL GDPR COMPLIANCE BY PRESTASHOP');
    expect(hasSubTitle).to.eq(true);

    await utilsFile.deleteFile(filePath);
  });

  it('should go to \'Your profile\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToMyProfilePage', baseContext);

    await boDashboardPage.goToMyProfile(page);
    await myProfilePage.closeSfToolBar(page);

    const pageTitle = await myProfilePage.getPageTitle(page);
    expect(pageTitle).to.contains(
      myProfilePage.pageTitleEdit(dataEmployees.defaultEmployee.lastName, dataEmployees.defaultEmployee.firstName),
    );
  });

  it('should update the language in French', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'updateLanguage', baseContext);
    
    employeeData.language = 'Français (French)';
    await myProfilePage.updateEditEmployee(page, employeeData.password, employeeData);

    const textResult = await myProfilePage.getAlertSuccess(page);
    expect(textResult).to.equal(myProfilePage.successfulUpdateMessage);
  });

  it('should go to \'Modules > Module Manager\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'returnToModuleManagerPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.modulesParentLink,
      boDashboardPage.moduleManagerLink,
    );
    await boModuleManagerPage.closeSfToolBar(page);

    const pageTitle = await boModuleManagerPage.getPageTitle(page);
    expect(pageTitle).to.contains(boModuleManagerPage.pageTitleFr);
  });

  it(`should search the module ${dataModules.psGdpr.name}`, async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'returnSearchModule', baseContext);

    const isModuleVisible = await boModuleManagerPage.searchModule(page, dataModules.psGdpr);
    expect(isModuleVisible).to.eq(true);
  });

  it(`should go to the configuration page of the module '${dataModules.psGdpr.name}'`, async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'returnToConfigurationPage', baseContext);

    await boModuleManagerPage.goToConfigurationPage(page, dataModules.psGdpr.tag);

    const pageTitle = await modPsGdprBoMain.getPageSubtitle(page);
    expect(pageTitle).to.eq(modPsGdprBoMain.pageSubTitleFr);
  });

  it('should display the tab "Help"', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'returnTabHelp', baseContext);

    const isTabVisible = await modPsGdprBoMain.goToTab(page, 5);
    expect(isTabVisible).to.be.equals(true);
  });

  it('should download the documentation in French', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'downloadDocFr', baseContext);

    const filePath = await modPsGdprBoTabHelp.downloadDocumentation(page);
    expect(filePath).to.not.eq(null);

    const doesFileExist = await utilsFile.doesFileExist(filePath, 5000);
    expect(doesFileExist).to.eq(true);

    const hasTitle = await utilsFile.isTextInPDF(filePath, 'Guide d’utilisation');
    expect(hasTitle).to.eq(true);

    const hasSubTitle = await utilsFile.isTextInPDF(filePath, 'RGPD OFFICIEL, ,PAR PRESTASHOP');
    expect(hasSubTitle).to.eq(true);

    await utilsFile.deleteFile(filePath);
  });

  it('should return to \'Your profile\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'returnToMyProfilePage', baseContext);

    await boDashboardPage.goToMyProfile(page);
    await myProfilePage.closeSfToolBar(page);

    const pageTitle = await myProfilePage.getPageTitle(page);
    expect(pageTitle).to.contains(
      myProfilePage.pageTitleEditFr(dataEmployees.defaultEmployee.lastName, dataEmployees.defaultEmployee.firstName),
    );
  });

  it('should reset the language', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'resetLanguage', baseContext);
    
    await myProfilePage.updateEditEmployee(page, dataEmployees.defaultEmployee.password, dataEmployees.defaultEmployee);

    const textResult = await myProfilePage.getAlertSuccess(page);
    expect(textResult).to.equal(myProfilePage.successfulUpdateMessageFR);
  });
});
