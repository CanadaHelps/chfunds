<?php
use CRM_Chfunds_ExtensionUtil as E;

/**
 * ChContribution.Create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_ch_contribution_Create_spec(&$params) {
  $params['contact_id']['api.required'] = 1;
  $params['total_amount']['api.required'] = 1;
  $params['payment_instrument_id']['api.aliases'] = ['payment_instrument'];
  $params['receive_date']['api.default'] = 'now';
  $params['receive_date']['api.required'] = TRUE;
  $params['payment_processor'] = [
    'name' => 'payment_processor',
    'title' => 'Payment Processor ID',
    'description' => 'ID of payment processor used for this contribution',
    // field is called payment processor - not payment processor id but can only be one id so
    // it seems likely someone will fix it up one day to be more consistent - lets alias it from the start
    'api.aliases' => ['payment_processor_id'],
    'type' => CRM_Utils_Type::T_INT,
  ];
  $params['ch_fund'] = [
    'api.required' => 1,
    'name' => 'ch_fund',
    'title' => 'CH Fund',
    'description' => 'Value of CH Fund used to fetch corresponding financial type',
    // field is called payment processor - not payment processor id but can only be one id so
    // it seems likely someone will fix it up one day to be more consistent - lets alias it from the start
    'api.aliases' => ['ch_fund_id'],
    'type' => CRM_Utils_Type::T_INT,
  ];
  $params['note'] = [
    'name' => 'note',
    'uniqueName' => 'contribution_note',
    'title' => 'note',
    'type' => 2,
    'description' => 'Associated Note in the notes table',
  ];
  $params['soft_credit_to'] = [
    'name' => 'soft_credit_to',
    'title' => 'Soft Credit contact ID (legacy)',
    'type' => CRM_Utils_Type::T_INT,
    'description' => 'ID of Contact to be Soft credited to (deprecated - use contribution_soft api)',
    'FKClassName' => 'CRM_Contact_DAO_Contact',
  ];
  $params['honor_contact_id'] = [
    'name' => 'honor_contact_id',
    'title' => 'Honoree contact ID (legacy)',
    'type' => CRM_Utils_Type::T_INT,
    'description' => 'ID of honoree contact (deprecated - use contribution_soft api)',
    'FKClassName' => 'CRM_Contact_DAO_Contact',
  ];
  // note this is a recommended option but not adding as a default to avoid
  // creating unnecessary changes for the dev
  $params['skipRecentView'] = [
    'name' => 'skipRecentView',
    'title' => 'Skip adding to recent view',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'description' => 'Do not add to recent view (setting this improves performance)',
  ];
  $params['skipLineItem'] = [
    'name' => 'skipLineItem',
    'title' => 'Skip adding line items',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.default' => 0,
    'description' => 'Do not add line items by default (if you wish to add your own)',
  ];
  $params['batch_id'] = [
    'title' => 'Batch',
    'type' => CRM_Utils_Type::T_INT,
    'description' => 'Batch which relevant transactions should be added to',
  ];
  $params['refund_trxn_id'] = [
    'title' => 'Refund Transaction ID',
    'type' => CRM_Utils_Type::T_STRING,
    'description' => 'Transaction ID specific to the refund taking place',
  ];
}

/**
 * ChContribution.Create API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_ch_contribution_Create($params) {
  $chFund = CRM_Utils_Array::value('ch_fund', $params, CRM_Utils_Array::value('ch_fund_id', $params));
  $params['financial_type_id'] = E::getFinancialTypeByCHFund($params['ch_fund']);
  return civicrm_api3('Contribution', 'create', $params);
}
