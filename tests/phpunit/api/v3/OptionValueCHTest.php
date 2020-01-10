<?php

use CRM_Chfunds_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

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
class api_v3_OptionValueCHTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  use Civi\Test\Api3TestTrait;

  /**
   * Financial Type aka Fund that is used by possibly multiple CHFunds
   */
  protected $fund;

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
    $this->fund = $this->callAPISuccess('FinancialType', 'create', [
      'label' => 'Test Created Fund',
      'name' => 'test_created_fund',
      'is_deductible' => 1,
    ]);
    $this->unallocatedFund = $this->callAPISuccess('FinancialType', 'get', ['name' => 'Unassigned CH Fund']);
  }

  public function tearDown() {
    parent::tearDown();
    //$this->callAPISuccess('FinancialType', 'delete', ['id' => $this->fund['id']]);
  }

  /**
   * Test that when calling an OptionValue API with option_group_id being ch_fund that it is correctly stored in the mapping.
   */
  public function testCreateCHFundOptionValue() {
     $ChFund =  $this->callAPISuccess('OptionValue', 'create', [
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
  }

}
