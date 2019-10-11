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


public static function getCHFundCustomID($chFundID) {
  return civicrm_api3('CustomField', 'getvalue', ['name' => 'Fund', 'return' => 'id']);
}

public static function getDefaultOptionValueCH($optionValueID) {
  $params = ['value' => civicrm_api3('OptionValue', 'getvalue', ['id' => $optionValueID, 'return' => 'value'])];
  CRM_Chfunds_BAO_OptionValueCH::retrieve($params, $defaults);

  return $defaults;
}


}
