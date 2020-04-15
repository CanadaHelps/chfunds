<?php
use CRM_Chfunds_Utils as E;

/**
 * ChContribution.Get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_c_h_contribution_Get_spec(&$params) {
  $params['contribution_test'] = [
    'api.default' => 0,
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'title' => 'Get Test Contributions?',
    'api.aliases' => ['is_test'],
  ];
  $params['financial_type_id']['api.aliases'] = ['contribution_type_id'];
  $params['payment_instrument_id']['api.aliases'] = ['contribution_payment_instrument', 'payment_instrument'];
  $params['contact_id'] = CRM_Utils_Array::value('contribution_contact_id', $params);
  $params['contact_id']['api.aliases'] = ['contribution_contact_id'];
  unset($params['contribution_contact_id']);
  $params['ch_fund'] = [
    'name' => 'ch_fund',
    'title' => 'CH Fund',
    'description' => 'Value of CH Fund used to fetch corresponding financial type',
    // field is called payment processor - not payment processor id but can only be one id so
    // it seems likely someone will fix it up one day to be more consistent - lets alias it from the start
    'api.aliases' => ['ch_fund_id'],
    'type' => CRM_Utils_Type::T_STRING,
  ];
}

/**
 * ChContribution.Get API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_get_success
 * @see civicrm_api3_get_error
 * @throws API_Exception
 */
function civicrm_api3_c_h_contribution_Get($params) {
  $chFund = CRM_Utils_Array::value('ch_fund', $params, CRM_Utils_Array::value('ch_fund_id', $params));
  if ($chFund) {
    $params['financial_type_id'] = E::getFinancialTypeByCHFund($chFund);
    $params['custom_' . E::getCHFundCustomID()] = $chFund;
  }
  return civicrm_api3('Contribution', 'get', $params);
}
