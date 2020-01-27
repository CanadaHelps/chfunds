<?php

use CRM_Chfunds_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class api_v3_OptionValueCHTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface {

  use Civi\Test\Api3TestTrait;

  /**
   * Financial Type aka Fund that is used by possibly multiple CHFunds
   * @var array
   */
  protected $fund;

  /**
   * Financial Type aka Fund that is the default fund.
   * @var array
   */
  protected $unallocatedFund;

  /**
   * Custom Group to hold the Fund custom field.
   * @var array
   */
  protected $customGroup;

  /**
   * Fund Custom field.
   * @var array
   */
  protected $customField;

  /**
   * Should we destroy the custom fields that we create or not
   * @var bool
   */
  protected $tearDownCustomField = TRUE;

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
    // If the ch_fund option group is no longer present (likely from the removal of the custom field in the taredown. recreate it
    $optionGroup = $this->callAPISuccess('OptionGroup', 'get', ['name' => 'ch_fund']);
    if (empty($optionGroup['count'])) {
      $optionGroupNew = $this->callAPISuccess('OptionGroup', 'create', [
        'title' => 'CH Fund',
        'name' => 'ch_fund',
        'data_type' => 'String',
        'description' => '',
        'is_active' => 1,
        'is_reserved' => 1,
      ]);
      // Ensure that the civicrm_managed table is also updated just in case.
      CRM_Core_DAO::executeQuery("UPDATE civicrm_managed SET entity_id = %1 WHERE entity_type = 'OptionGroup' AND module = 'biz.jmaconsulting.chfunds'", [1 => [$optionGroupNew['id'], 'Positive']]);
    }
    $optionGroup = $this->callAPISuccess('OptionGroup', 'get', ['name' => 'ch_fund']);
    $customFieldCheck = $this->callAPISuccess('CustomField', 'get', [
      'option_group_id' => 'ch_fund',
    ]);
    if (empty($customFieldCheck['count'])) {
      $this->customGroup = $this->callAPISuccess('CustomGroup', 'create', [
        'title' => 'Additional info',
        'extends' => 'Contribution',
        'collapse_display' => 1,
        'is_public' => 1,
        'is_active' => 1,
      ]);
      $this->customField = $this->callAPISuccess('CustomField', 'create', [
        'custom_group_id' => $this->customGroup['id'],
        'label' => 'CH Fund',
        'name' => 'Fund',
        'data_type' => 'String',
        'option_group_id' => 'ch_fund',
        'is_searchable' => 1,
        'is_active' => 1,
        'html_type' => 'ContactReference',
      ]);
    }
    else {
      $this->customField = $customFieldCheck;
      $this->tearDownCustomField = FALSE;
    }
    $this->fund = $this->callAPISuccess('FinancialType', 'create', [
      'label' => 'Test Created Fund',
      'name' => 'test_created_fund',
      'is_deductible' => 1,
    ]);
    $this->unallocatedFund = $this->callAPISuccess('FinancialType', 'get', ['name' => 'Unassigned CH Fund']);
  }

  public function tearDown() {
    parent::tearDown();
    if ($this->tearDownCustomField) {
      $this->callAPISuccess('CustomField', 'delete', ['id' => $this->customField['id']]);
      $this->callAPISuccess('CustomGroup', 'delete', ['id' => $this->customGroup['id']]);
    }
    $this->callAPISuccess('FinancialType', 'delete', ['id' => $this->fund['id']]);
  }

  /**
   * Test that when calling an OptionValue API with option_group_id being ch_fund that it is correctly stored in the mapping.
   */
  public function testCreateCHFundOptionValue() {
    $chFund = $this->callAPISuccess('OptionValue', 'create', [
      'label' => 'Test Created CH Fund 1',
      'option_group_id' => 'ch_fund',
      'value' => 'CH+99999',
    ]);
    // Confirm that when we create a CHFund that it is initially allocated to the unallocated CHFund Financial type (fund).
    $this->assertEquals([$this->unallocatedFund['id'] => 'Test Created CH Fund 1'], E::getCHFundsByFinancialType());
    $chFundMap = $this->callAPISuccess('OptionValueCH', 'get', ['value' => 'CH+99999']);
    // Confirm that we can use the API to change the financial type mapping.
    $this->callAPISuccess('OptionValueCH', 'create', ['financial_type_id' => $this->fund['id'], 'id' => $chFundMap['id']]);
    $this->assertEquals([$this->fund['id'] => 'Test Created CH Fund 1'], E::getCHFundsByFinancialType());
    $this->callAPISuccess('OptionValue', 'delete', ['id' => $chFund['id']]);
    $updatedMap = $this->callAPISuccess('OptionValueCH', 'get', []);
    // Confirm that deleting the option value also cascaded to deleting the CHFund mapping.
    $this->assertEmpty($updatedMap['values']);
  }

  /**
   * Test creating multiple CHFunds and assigning them to the same fund
   */
  public function testCreateMultipleCHFunds() {
    $chFund = $this->callAPISuccess('OptionValue', 'create', [
      'label' => 'Test Created CH Fund 1',
      'option_group_id' => 'ch_fund',
      'value' => 'CH+99999',
    ]);
    $chFundMap = $this->callAPISuccess('OptionValueCH', 'get', ['value' => 'CH+99999']);
    $this->callAPISuccess('OptionValueCH', 'create', ['financial_type_id' => $this->fund['id'], 'id' => $chFundMap['id']]);
    $chFund2 = $this->callAPISuccess('OptionValue', 'create', [
      'label' => 'Test Created CH Fund 2',
      'option_group_id' => 'ch_fund',
      'value' => 'CH+999999',
    ]);
    // Confirm that the new CHFund has been initially allocated to the new unallocated CHFund fund.
    $this->assertEquals([$this->unallocatedFund['id'] => 'Test Created CH Fund 2', $this->fund['id'] => 'Test Created CH Fund 1'], E::getCHFundsByFinancialType());
    $chFundMap2 = $this->callAPISuccess('OptionValueCH', 'get', ['value' => 'CH+999999']);
    $this->callAPISuccess('OptionValueCH', 'create', ['financial_type_id' => $this->fund['id'], 'id' => $chFundMap2['id']]);
    $this->assertEquals([$this->fund['id'] => 'Test Created CH Fund 1, Test Created CH Fund 2'], E::getCHFundsByFinancialType());

    $this->callAPISuccess('OptionValue', 'delete', ['id' => $chFund['id']]);
    $updatedMap = $this->callAPISuccess('OptionValueCH', 'get', []);
    // Confirm that deleting the option value also cascaded to deleting the CHFund mapping.
    $this->assertEquals(1, $updatedMap['count']);
    $this->callAPISuccess('OptionValue', 'delete', ['id' => $chFund2['id']]);
    $updatedMap = $this->callAPISuccess('OptionValueCH', 'get', []);
    $this->assertEmpty($updatedMap['values']);
  }

}
