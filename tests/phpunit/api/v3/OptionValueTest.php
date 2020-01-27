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
class api_v3_OptionValueTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface {

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
    $this->fund = $this->callAPISuccess('FinancialType', 'create', [
      'label' => 'Test Created Fund',
      'name' => 'test_created_fund',
      'is_deductible' => 1,
    ]);
    $this->unallocatedFund = $this->callAPISuccess('FinancialType', 'get', ['name' => 'Unassigned CH Fund']);
  }

  public function tearDown() {
    parent::tearDown();
    $this->callAPISuccess('FinancialType', 'delete', ['id' => $this->fund['id']]);
  }

  public function testCreateOptionValueCHChange() {
    $chFund = $this->callAPISuccess('OptionValue', 'create', [
      'label' => 'Test Created CH Fund 1',
      'option_group_id' => 'ch_fund',
      'value' => 'CH+99999',
    ]);
    $chFundMap = $this->callAPISuccess('OptionValueCH', 'get', ['value' => 'CH+99999']);
    // change CH Fund option value
    $this->callAPISuccess('OptionValue', 'create', ['value' => 'CH+1000000', 'id' => $chFund['id']]);

    // ensure that option value is changed
    $actualOptionValue = $this->callAPISuccess('OptionValueCH', 'getvalue', ['id' => $chFundMap['id'], 'return' => 'value']);
    $this->assertEquals('CH+1000000', $actualOptionValue);

    // delete created values
    $this->callAPISuccess('OptionValue', 'delete', ['id' => $chFund['id']]);
    $updatedMap = $this->callAPISuccess('OptionValueCH', 'get', []);
    $this->assertEmpty($updatedMap['values']);
  }

}
