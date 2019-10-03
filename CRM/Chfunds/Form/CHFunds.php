<?php

use CRM_Chfunds_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Chfunds_Form_CHFunds extends CRM_Core_Form {
  protected $_financial_type_id;
  protected $_chFunds;

  public function buildQuickForm() {
    parent::buildQuickForm();
    $this->_financial_type_id = CRM_Utils_Request::retrieve('financial_type_id', 'Positive');

    $financialType = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', $this->_financial_type_id, 'name');
    CRM_Utils_System::setTitle(ts('%1 - CH Funds', [1 => $financialType]));
    $optionValues = civicrm_api3('OptionValue', 'get', ['option_group_id' => 'ch_fund'])['values'];
    $chFunds = [];
    foreach ($optionValues as $value) {
      $chFunds[$value['value']] = $value['label'];
    }
    foreach ($chFunds as $chFund => $label) {
      $this->addElement('checkbox', "ch_funds[$chFund]", NULL, $label);
    }

    $this->addButtons([
      [
        'type' => 'upload',
        'name' => ts('Done'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ],
    ]);
  }

  /**
   * @return array
   */
  public function setDefaultValues() {
    $defaults = $statusVals = [];
    $values = civicrm_api3('OptionValueCH', 'get', [
      'financial_type_id' => $this->_financial_type_id,
    ])['values'];
    foreach ($values as $value) {
      $defaults['ch_funds'][$value['value']] = 1;
    }

    $this->_chFunds = array_keys($defaults['ch_funds']);
    return $defaults;
  }

  public function postProcess() {
    $values = $this->exportValues();
    $chFundSubmittedValues = array_keys($values['ch_funds']);
    $gid = civicrm_api3('OptionGroup', 'getvalue', ['name' => 'ch_fund', 'return' => 'id']);

    foreach ($chFundSubmittedValues as $chFund) {
      if (!in_array($chFund, $this->_chFunds)) {
        civicrm_api3('OptionValueCH', 'create', [
          'option_group_id' => $gid,
          'financial_type_id' => $this->_financial_type_id,
          'value' => $chFund,
          'is_enabled_in_ch' => 0,
        ]);
      }
    }

    foreach ($this->_chFunds as $chFund) {
      if (!in_array($chFund, $chFundSubmittedValues)) {
        $result = civicrm_api3('OptionValueCH', 'get', [
          'financial_type_id' => $this->_financial_type_id,
          'value' => $chFund,
          'sequential' => 1,
        ])['values'][0];
        civicrm_api3('OptionValueCH', 'delete', ['id' => $result['id']]);
      }
    }

    parent::postProcess();
  }

}
