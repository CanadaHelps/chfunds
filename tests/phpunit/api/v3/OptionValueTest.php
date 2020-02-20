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
 /**
  * Contact ID
  * @var int
  */
  protected $individualID;

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
    $this->individualID =  $this->callAPISuccess('Contact', 'create', ['first_name' => 'Alan', 'last_name' => 'MouseMouse', 'contact_type' => 'Individual'])['id'];
  }

  public function tearDown() {
    parent::tearDown();
    if ($this->tearDownCustomField) {
      $this->callAPISuccess('CustomField', 'delete', ['id' => $this->customField['id']]);
      $this->callAPISuccess('CustomGroup', 'delete', ['id' => $this->customGroup['id']]);
    }
    $this->callAPISuccess('FinancialType', 'get', ['id' => $this->fund['id'], 'api.FinancialType.delete' => ["id" => "\$value.id"]]);
    $this->callAPISuccess('Contact', 'delete', ['id' => $this->individualID]);
  }

  /**
   * Test OptionValueCH if its value is updated if the OptionValue value is changed by updating its value
   *  1. Create a option Value
   *  2. Update the optionValue value from 'CH+99999' to 'CH1000000'
   *  3. Check corresponding OptionValueCH mapping, if its value is updated or not
   */
  public function testMappingOnOptionvalueValueChange() {
    $chFund = $this->callAPISuccess('OptionValue', 'create', [
      'label' => 'Test Created CH Fund 1',
      'option_group_id' => 'ch_fund',
      'value' => 'CH+99999',
    ]);
    $chFundMap = $this->callAPISuccess('OptionValueCH', 'get', ['value' => 'CH+99999']);
    // change CH Fund option value
    $this->callAPISuccess('OptionValue', 'create', ['value' => 'CH+1000000', 'option_group_name' => 'ch_fund', 'id' => $chFund['id']]);

    // ensure that option value is changed
    $actualOptionValue = $this->callAPISuccess('OptionValueCH', 'getvalue', ['id' => $chFundMap['id'], 'return' => 'value']);
    $this->assertEquals('CH+1000000', $actualOptionValue);

    // To ensure that linked associated Fund is not changed, lets change the Fund to something else, other then 'Unassigned CH Fund' say 'Test Created Fund'
    //  and later on CH Fund value change ensure that Fund corresponding mappinh is still the same
    $this->callAPISuccess('OptionValueCH', 'create', ['id' => $chFundMap['id'], 'financial_type_id' => $this->fund['id']]);
    $chFundMap = $this->callAPISuccess('OptionValueCH', 'getsingle', ['id' => $chFundMap['id']]);
     // 1. Ensure that fund is changed successfully
    $this->assertEquals($this->fund['id'], $chFundMap['financial_type_id']);
    // 2. Change the optionValue again and check that option value is changed but NOT the associated Fund
    $this->callAPISuccess('OptionValue', 'create', ['value' => 'CH+1000001', 'option_group_name' => 'ch_fund', 'id' => $chFund['id']]);
    // 2.1 Ensure that CH Fund value is changed
    $actualOptionValue = $this->callAPISuccess('OptionValueCH', 'getvalue', ['id' => $chFundMap['id'], 'return' => 'value']);
    $this->assertEquals('CH+1000001', $actualOptionValue);
    // 2.2 Ensure that Fund value is NOT changed
    $actualFund = $this->callAPISuccess('OptionValueCH', 'getvalue', ['id' => $chFundMap['id'], 'return' => 'financial_type_id']);
    $this->assertEquals($this->fund['id'], $actualFund);

    // delete created values
    $this->callAPISuccess('OptionValue', 'delete', ['id' => $chFund['id']]);
    $updatedMap = $this->callAPISuccess('OptionValueCH', 'get', []);
    $this->assertEmpty($updatedMap['values']);
  }

  /**
   * Test Contribution after a we update the OptionValue value
   *  1. Create a option Value
   *  2. Create a contribution with ch_fund custom field which uses same option value
   *  3. Update the optionvalue value from 'CH+99999' to 'CH1000000'
   *  4. Check contribution's CH Fund value to ensure that its value is updated
   *  5. Check mapping data to ensure the OptionValue value is also updated there
   */
  public function testContributionOnOptionvalueValueChange() {
    $chFund = $this->callAPISuccess('OptionValue', 'create', [
      'label' => 'Test Created CH Fund 1',
      'option_group_id' => 'ch_fund',
      'value' => 'CH+99999',
    ]);

    $contributionID = $this->callAPISuccess('CHContribution', 'create', [
      'receive_date' => date('Ymd'),
      'total_amount' => 100.00,
      'payment_instrument_id' => 1,
      'source' => 'SSF',
      'contribution_status_id' => 1,
      'ch_fund' => 'CH+99999',
      'contact_id' => $this->individualID,
    ])['id'];
    $contribution = $this->callAPISuccess('Contribution', 'getsingle', [
      'id' => $contributionID,
    ]);
    // ensure that contribution Fund is assigned to 'Unassigned CH Fund'
    $this->assertEquals($this->unallocatedFund['id'], $contribution['financial_type_id']);

    // change the Fund of contribution to test that changing a CH fund option value doesn't impact the associated contribution's fund
    $this->callAPISuccess('Contribution', 'create', [
      'id' => $contributionID,
      'financial_type_id' => $this->fund['id'],
    ]);
    $contribution = $this->callAPISuccess('Contribution', 'getsingle', [
      'id' => $contributionID,
    ]);
    // ensure that contribution Fund is changed to new financial type
    $this->assertEquals($this->fund['id'], $contribution['financial_type_id']);


    $chFundMap = $this->callAPISuccess('OptionValueCH', 'get', ['value' => 'CH+99999']);
    // change CH Fund option value
    $this->callAPISuccess('OptionValue', 'create', ['value' => 'CH+1000000', 'option_group_name' => 'ch_fund', 'id' => $chFund['id']]);

    // ensure that option value is changed
    $actualOptionValue = $this->callAPISuccess('OptionValueCH', 'getvalue', ['id' => $chFundMap['id'], 'return' => 'value']);
    $this->assertEquals('CH+1000000', $actualOptionValue);

    // ensure that contribution's CH Fund is also updated
    $contribution = $this->callAPISuccess('Contribution', 'getsingle', [
      'id' => $contributionID,
      'return' => ['custom_' . $this->customField['id'], 'financial_type_id'],
    ]);
    $this->assertEquals('CH+1000000', $contribution['custom_' . $this->customField['id']]);
    // ensure that financial type is not changed
    $this->assertEquals($this->fund['id'], $contribution['financial_type_id']);

    // delete created values
    $this->callAPISuccess('contribution', 'delete', ['id' => $contributionID]);
    $this->callAPISuccess('OptionValue', 'delete', ['id' => $chFund['id']]);
    $updatedMap = $this->callAPISuccess('OptionValueCH', 'get', []);
    $this->assertEmpty($updatedMap['values']);
  }

}
