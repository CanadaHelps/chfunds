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
   * Contact ID
   * @var int
   */
  protected $individualID;

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
        'html_type' => 'Select',
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
    $this->callAPISuccess('FinancialType', 'get', ['id' => $this->fund['id'], 'api.FinancialType.delete' => '"id":"$value.id"']);
    $this->callAPISuccess('Contact', 'delete', ['id' => $this->individualID]);
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

  /**
   * Test OptionValue if its value is updated if the OptionValueCH mapping is changed by updating the optionValue value
   *  1. Create a option Value
   *  2. Update the associated OptionValueCH mapping by changing its value from 'CH+99999' to 'CH1000000'
   *  3. Check corresponding OptionValue value if its updated or not
   */
  public function testCreateOptionValueChange() {
    $chFund = $this->callAPISuccess('OptionValue', 'create', [
      'label' => 'Test Created CH Fund 1',
      'option_group_id' => 'ch_fund',
      'value' => 'CH+99999',
    ]);
    $chFundMap = $this->callAPISuccess('OptionValueCH', 'get', ['value' => 'CH+99999']);
    // change CH Fund option value
    $this->callAPISuccess('OptionValueCH', 'create', ['value' => 'CH+1000000', 'id' => $chFundMap['id']]);

    // ensure that option value is changed
    $actualOptionValue = $this->callAPISuccess('OptionValue', 'getvalue', ['id' => $chFund['id'], 'return' => 'value']);
    $this->assertEquals('CH+1000000', $actualOptionValue);

    // delete created values
    $this->callAPISuccess('OptionValue', 'delete', ['id' => $chFund['id']]);
    $updatedMap = $this->callAPISuccess('OptionValueCH', 'get', []);
    $this->assertEmpty($updatedMap['values']);
  }

  /**
   * Test Contribution after a we update the OptionValue-Fund mapping by changing the OptionValue
   *  1. Create a option Value
   *  2. Create a contribution with ch_fund custom field which uses same option value
   *  3. Update the mapping by changing the optionvalue from 'CH+99999' to 'CH1000000'
   *  4. Check contribution's CH Fund value to ensure that its value is updated
   *  5. Check contribution's financial item and line item to ensure that no additional entry are made for optionValue change
   */
  public function testContributionOnOptionvalueCHValueChange() {
    $chFund = $this->callAPISuccess('OptionValue', 'create', [
      'label' => 'Test Created CH Fund 1',
      'option_group_id' => 'ch_fund',
      'value' => 'CH+99999',
    ]);
    $chFundMap = $this->callAPISuccess('OptionValueCH', 'getsingle', ['value' => 'CH+99999']);
    $this->assertEquals($this->unallocatedFund['id'], $chFundMap['financial_type_id']);
    // change the OptionValueCH mapping, by updating the financial_type_id
    $this->callAPISuccess('OptionValueCH', 'create', ['financial_type_id' => $this->fund['id'], 'id' => $chFundMap['id']]);

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
    // ensure that contribution Fund is assigned to 'Test Created Fund'
    $this->assertEquals($this->fund['id'], $contribution['financial_type_id']);

    // change CH Fund option value
    $this->callAPISuccess('OptionValueCH', 'create', ['value' => 'CH+1000000', 'id' => $chFundMap['id']]);

    // ensure that option value is changed
    $actualOptionValue = $this->callAPISuccess('OptionValue', 'getvalue', ['id' => $chFund['id'], 'return' => 'value']);
    $this->assertEquals('CH+1000000', $actualOptionValue);

    // ensure that contribution's CH Fund is also updated
    $contribution = $this->callAPISuccess('Contribution', 'getsingle', [
      'id' => $contributionID,
      'return' => ['custom_' . $this->customField['id'], 'financial_type_id'],
    ]);
    // ensure that the value of the custom field is changed
    $this->assertEquals('CH+1000000', $contribution['custom_' . $this->customField['id']]);
    // ensure that the value of financial type is not changed
    $this->assertEquals($this->fund['id'], $contribution['financial_type_id']);
    $lineItem = $this->callAPISuccess('lineItem', 'get', [
      'contribution_id' => $contributionID,
      'sequential' => 1,
    ]);
    // ensure that one line-item is present
    $this->assertEquals(1, $lineItem['count']);
    // ensure that associated line-item has correct financial item linked to it
    $this->assertEquals($this->fund['id'], $lineItem['values'][0]['financial_type_id']);
    $totalFinancialItem = $this->callAPISuccess('FinancialItem', 'getcount', [
      'entity_table' => 'civicrm_line_item',
      'entity_id' => $lineItem['id'],
    ]);
    $this->assertEquals(1, $totalFinancialItem);

    // delete created values
    $this->callAPISuccess('contribution', 'delete', ['id' => $contributionID]);
    $this->callAPISuccess('OptionValue', 'delete', ['id' => $chFund['id']]);
    $updatedMap = $this->callAPISuccess('OptionValueCH', 'get', []);
    $this->assertEmpty($updatedMap['values']);
  }

  /**
   * Test Contribution after a we update the OptionValue-Fund mapping by changing the Fund
   *  1. Create a option Value
   *  2. Create a contribution with ch_fund custom field which uses same option value and 'Unassigned CH Fund'
   *  3. Update the mapping by changing the fund from 'Unassigned CH Fund' to 'Test Created Fund'
   *  4. Check batch table to ensure that contribution is in queue, so that can be later processed to update its Fund to 'Test Created Fund'
   */
  public function testContributionOnOptionvalueCHFundChange() {
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

    $result = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_ch_contribution_batch ")->fetchAll();
    // ensure that the queue is empty before updating the mapping by changing the fund value
    $this->assertEmpty($result);

    $chFundMap = $this->callAPISuccess('OptionValueCH', 'getsingle', ['value' => 'CH+99999']);
    // change Fund value in the mapping
    $result = $this->callAPISuccess('OptionValueCH', 'create', ['financial_type_id' => $this->fund['id'], 'id' => $chFundMap['id']]);

    // check there is entry in contribution batch table to mark this change in fund value
    $result = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_ch_contribution_batch ")->fetchAll();
    $this->assertNotEmpty($result);
    $this->assertEquals(1, count($result));
    // ensure the contribution which needs to be updated is present in the queue
    $this->assertEquals($contributionID, $result[0]['contribution_id']);
    $this->assertEquals($this->fund['id'], $result[0]['fund']);

    // delete created values
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_ch_contribution_batch WHERE contribution_id = $contributionID");
    $this->callAPISuccess('contribution', 'delete', ['id' => $contributionID]);
    $this->callAPISuccess('OptionValue', 'delete', ['id' => $chFund['id']]);
    $updatedMap = $this->callAPISuccess('OptionValueCH', 'get', []);
    $this->assertEmpty($updatedMap['values']);
  }

}
