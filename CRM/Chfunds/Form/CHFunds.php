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

  public function preProcess() {
    $this->_financial_type_id = CRM_Utils_Request::retrieve('financial_type_id', 'Positive', $this, FALSE, NULL, 'GET');
  }

  public function buildQuickForm() {
    parent::buildQuickForm();

    $financialType = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', $this->_financial_type_id, 'name');
    CRM_Utils_System::setTitle(ts('%1 - Assign CH Funds', [1 => $financialType]));
    $optionValues = civicrm_api3('OptionValue', 'get', ['option_group_id' => 'ch_fund', 'options' => ['limit' => 0]])['values'];

    $values = civicrm_api3('OptionValueCH', 'get', [
      'financial_type_id' => $this->_financial_type_id,
      'options' => ['limit' => 0],
    ])['values'];
    $selectedValues = CRM_Utils_Array::collect('value', $values);

    $chFunds = [];
    foreach ($optionValues as $value) {
      if (in_array($value['value'], $selectedValues)) {
        $chFunds[$value['value']] = $value['label'];
      }
    }

    $this->addElement('checkbox', "ch_funds_check_all", NULL, ts('Check all'));
    foreach ($chFunds as $chFund => $label) {
      $this->addElement('checkbox', "ch_funds[$chFund]", NULL, $label);
    }

    $financialTypes = [];
    CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes($financialTypes, CRM_Core_Action::ADD);

    $this->add('select', 'financial_type_id',
      ts('Financial Type'),
      $financialTypes,
      TRUE
    );

    $this->addFormRule(array(__CLASS__, 'formRule'), $this);

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

  public static function formRule($fields, $files, $self) {
    $errors = [];
    /*
    $mappedValues = E::getMappedItem('value', "WHERE financial_type_id <> $self->_financial_type_id ");
    foreach (array_keys($fields['ch_funds']) as $fundID) {
      if (in_array($fundID, $mappedValues)) {
        $errors['_qf_default'] = ts('The CH Fund - %1 is already used for other financial type. Please choose any other option.', [1 => $fundID]);
        break;
      }
    }
    */

    return $errors;
  }

  /**
   * @return array
   */
  public function setDefaultValues() {
    $defaults = [];
    $values = civicrm_api3('OptionValueCH', 'get', [
      'financial_type_id' => $this->_financial_type_id,
      'options' => ['limit' => 0],
    ])['values'];
    foreach ($values as $value) {
      $this->_chFunds[] = $value['value'];
    }
    $defaults['financial_type_id'] = $this->_financial_type_id;

    return $defaults;
  }

  public function postProcess() {
    $values = $this->exportValues();
    $chFundSubmittedValues = array_keys($values['ch_funds']);
    $gid = civicrm_api3('OptionGroup', 'getvalue', ['name' => 'ch_fund', 'return' => 'id']);

    if ($this->_financial_type_id != $values['financial_type_id'] && !empty($chFundSubmittedValues)) {
      foreach ($chFundSubmittedValues as $chFund) {
        CRM_Core_DAO::executeQuery(" DELETE FROM civicrm_option_value_ch WHERE financial_type_id  = $this->_financial_type_id AND value = '$chFund'");
        civicrm_api3('OptionValueCH', 'create', [
          'option_group_id' => $gid,
          'financial_type_id' => $values['financial_type_id'],
          'value' => $chFund,
          'is_enabled_in_ch' => 0,
        ]);

        $transaction = new CRM_Core_Transaction();
        E::updateCHContribution($values['financial_type_id'], $chFund);
        $transaction->commit();
      }
    }

    parent::postProcess();
  }

}
