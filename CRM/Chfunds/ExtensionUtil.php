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

}
