<?php

use CRM_Chfunds_Utils as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Chfunds_Form_CHFunds extends CRM_Core_Form
{
  protected $_financial_type_id;
  protected $_chFunds;

  public function preProcess()
  {
    $this->_financial_type_id = CRM_Utils_Request::retrieve('financial_type_id', 'Positive', $this, FALSE, NULL, 'GET');
  }

  public function buildQuickForm()
  {
    parent::buildQuickForm();

    $financialType = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', $this->_financial_type_id, 'name');
    CRM_Utils_System::setTitle(ts('%1 - Assign CH Funds', [1 => $financialType]));
    $optionValues = civicrm_api3('OptionValue', 'get', ['option_group_id' => 'ch_fund', 'options' => ['limit' => 0]])['values'];
    $chFunds = [];
    foreach ($optionValues as $value) {
      $chFunds[$value['value']] = $value['label'];
    }
    asort($chFunds);

    $this->addElement('checkbox', "ch_funds_check_all", NULL, ts('Check all'));
    foreach ($chFunds as $chFund => $label) {
      $this->addElement('checkbox', "ch_funds[$chFund]", NULL, $label);
    }

    $financialTypes = [];
    CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes($financialTypes, CRM_Core_Action::ADD);

    $this->add(
      'select',
      'financial_type_id',
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

  public static function formRule($fields, $files, $self)
  {
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
  public function setDefaultValues()
  {
    $defaults = [];
    $values = civicrm_api3('OptionValueCH', 'get', [
      'financial_type_id' => $this->_financial_type_id,
      'options' => ['limit' => 0],
    ])['values'];

    foreach ($values as $value) {
      $this->_chFunds[] = $value['value'];
      $defaults["ch_funds[{$value['value']}]"] = 1;
    }

    $defaults['financial_type_id'] = $this->_financial_type_id;

    return $defaults;
  }

  public function postProcess()
  {
    $values = $this->exportValues();
    $gid = civicrm_api3('OptionGroup', 'getvalue', ['name' => 'ch_fund', 'return' => 'id']);

    // Funds that were submitted as checked
    $chFundSubmittedValues = !empty($values['ch_funds']) ? array_keys($values['ch_funds']) : [];

    // Funds that were previously assigned to this financial type
    $previouslyAssigned = $this->_chFunds ?? [];

    // Funds that were unchecked (previously assigned but not in submitted)
    $uncheckedFunds = array_diff($previouslyAssigned, $chFundSubmittedValues);

    // Funds that are newly checked or re-assigned
    $checkedFunds = $chFundSubmittedValues;

    // Resolve the fallback financial_type_id for unassignment
    $unassignedFallbackId = $this->_resolveUnassignedFallback();

    // --- Handle UNCHECKED funds (unassign them) ---
    if (!empty($uncheckedFunds) && $unassignedFallbackId) {
      foreach ($uncheckedFunds as $chFund) {
        // Find child OptionValueCH records linked to this one
        $existing = civicrm_api3('OptionValueCH', 'getsingle', [
          'financial_type_id' => $this->_financial_type_id,
          'value' => $chFund,
          'return' => 'id',
        ]);
        $existingId = $existing['id'] ?? NULL;

        $children = [];
        if ($existingId) {
          $childResult = civicrm_api3('OptionValueCH', 'get', [
            'parent_id' => $existingId,
            'return' => 'id',
          ]);
          $children = array_keys($childResult['values']);
        }

        // Delete the existing mapping
        CRM_Core_DAO::executeQuery(
          "DELETE FROM civicrm_option_value_ch WHERE financial_type_id = %1 AND value = %2",
          [1 => [$this->_financial_type_id, 'Integer'], 2 => [$chFund, 'String']]
        );

        // Re-create under the fallback financial type
        $newRecord = civicrm_api3('OptionValueCH', 'create', [
          'option_group_id' => $gid,
          'financial_type_id' => $unassignedFallbackId,
          'value' => $chFund,
          'is_enabled_in_ch' => 0,
        ]);

        // Update children to point to the new parent and financial type
        foreach ($children as $childId) {
          civicrm_api3('OptionValueCH', 'create', [
            'id' => $childId,
            'option_group_id' => $gid,
            'financial_type_id' => $unassignedFallbackId,
            'parent_id' => $newRecord['id'],
          ]);
        }

        $transaction = new CRM_Core_Transaction();
        E::updateCHContribution($unassignedFallbackId, $chFund);
        $transaction->commit();
      }
    }

    // --- Handle CHECKED funds (assign/reassign to selected financial type) ---
    $targetFinancialTypeId = $values['financial_type_id'];
    foreach ($checkedFunds as $chFund) {
      // Skip if already correctly assigned
      $alreadyAssigned = civicrm_api3('OptionValueCH', 'get', [
        'financial_type_id' => $targetFinancialTypeId,
        'value' => $chFund,
      ]);
      if ($alreadyAssigned['count'] > 0) {
        continue;
      }

      // Get the existing record (could be under any financial type)
      $existingRecords = civicrm_api3('OptionValueCH', 'get', [
        'value' => $chFund,
        'return' => ['id', 'financial_type_id'],
      ]);

      foreach ($existingRecords['values'] as $existing) {
        $existingId = $existing['id'];
        $oldFinancialTypeId = $existing['financial_type_id'];

        $childResult = civicrm_api3('OptionValueCH', 'get', [
          'parent_id' => $existingId,
          'return' => 'id',
        ]);
        $children = array_keys($childResult['values']);

        CRM_Core_DAO::executeQuery(
          "DELETE FROM civicrm_option_value_ch WHERE financial_type_id = %1 AND value = %2",
          [1 => [$oldFinancialTypeId, 'Integer'], 2 => [$chFund, 'String']]
        );

        $newRecord = civicrm_api3('OptionValueCH', 'create', [
          'option_group_id' => $gid,
          'financial_type_id' => $targetFinancialTypeId,
          'value' => $chFund,
          'is_enabled_in_ch' => 0,
        ]);

        foreach ($children as $childId) {
          civicrm_api3('OptionValueCH', 'create', [
            'id' => $childId,
            'option_group_id' => $gid,
            'financial_type_id' => $targetFinancialTypeId,
            'parent_id' => $newRecord['id'],
          ]);
        }

        $transaction = new CRM_Core_Transaction();
        E::updateCHContribution($targetFinancialTypeId, $chFund);
        $transaction->commit();
      }
    }

    parent::postProcess();
  }

  /**
   * Resolve the financial_type_id to use when unassigning a CH Fund.
   * Priority: "Unassigned CH Fund" → "General Fund" → NULL (cannot unassign)
   */
  private function _resolveUnassignedFallback(): ?int
  {
    // Try "Unassigned CH Fund" first
    $result = CRM_Core_DAO::executeQuery(
      "SELECT id FROM civicrm_financial_type WHERE name = 'Unassigned CH Fund' AND is_active = 1 LIMIT 1"
    );
    if ($result->fetch()) {
      return (int) $result->id;
    }

    // Fallback to "General Fund"
    $result = CRM_Core_DAO::executeQuery(
      "SELECT id FROM civicrm_financial_type WHERE name = 'General Fund' AND is_active = 1 LIMIT 1"
    );
    if ($result->fetch()) {
      return (int) $result->id;
    }

    return NULL;
  }
}
