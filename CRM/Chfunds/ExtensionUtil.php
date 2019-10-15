<?php

class CRM_Chfunds_ExtensionUtil {

public static function getFinancialTypeByCHFund($chFundID) {
  $values = civicrm_api3('OptionValueCH', 'get', [
    'value' => $chFundID,
    'sequential' => 1,
    'return' => ["financial_type_id"],
  ])['values'][0];

  return CRM_Utils_Array::value('financial_type_id', $values);
}


public static function getCHFundCustomID() {
  return civicrm_api3('CustomField', 'getvalue', ['name' => 'Fund', 'return' => 'id']);
}

public static function getDefaultOptionValueCH($optionValueID) {
  $params = ['value' => civicrm_api3('OptionValue', 'getvalue', ['id' => $optionValueID, 'return' => 'value'])];
  CRM_Chfunds_BAO_OptionValueCH::retrieve($params, $defaults);

  return $defaults;
}

public static function getCHFundsByFinancialType() {
  $optionValueCHFunds = civicrm_api3('OptionValueCH', 'get', ['options' => ['limit' => 0]])['values'];
  $CHFunds = [];

  foreach (civicrm_api3('OptionValue', 'get', ['option_group_id' => 'ch_fund'])['values'] as $chFund) {
    $CHFunds[$chFund['value']] = $chFund['label'];
  }
  $result = [];
  foreach ($optionValueCHFunds as $optionValueCHFund) {
    if (!empty($CHFunds[$optionValueCHFund['value']])) {
      $result[$optionValueCHFund['financial_type_id']][] = $CHFunds[$optionValueCHFund['value']];
    }
  }

  foreach ($result as $k => $v) {
    $result[$k] = implode(', ', $v);
  }

  return $result;
}

public static function getMappedItem($column, $condition = '') {
  return explode(',', CRM_Core_DAO::singleValueQuery("SELECT GROUP_CONCAT(DISTINCT {$column}) FROM civicrm_option_value_ch {$condition} "));
}

public static function filterFinancialTypes(&$financialTypes, $condition) {
  $mappedFinancialTypes = self::getMappedItem('financial_type_id', $condition);
  foreach ($financialTypes as $key => $label) {
    if (in_array($key, $mappedFinancialTypes)) {
      unset($financialTypes[$key]);
    }
  }
}

public static function updateContributions($params, $CHFund, $entity = 'Contribution') {
  $contributions = civicrm_api3('CHContribution', 'get', [
    'ch_fund' => $CHFund,
    'options' => ['limit' => 0],
  ])['values'];
  foreach ($contributions as $id => $value) {
    civicrm_api3($entity, 'create', array_merge(
        ['id' => $value['id']],
        $params
      )
    );
  }
}

}
