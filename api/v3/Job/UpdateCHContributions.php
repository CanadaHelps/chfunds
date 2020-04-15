<?php
use CRM_Chfunds_ExtensionUtil as E;

/**
 * Job.UpdateCHContributions API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_job_update_c_h_contributions_spec(&$spec) {
  $spec['batch_size'] = [
    'title' => 'Batch Size',
    'type' => CRM_Utils_Type::T_INT,
  ];
}

/**
 * Job.UpdateCHContributions API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_job_update_c_h_contributions($params) {
  $batchSize = CRM_Utils_Array::value('batch_size', $params, 1000);
  $dao = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_ch_contribution_batch LIMIT 0, $batchSize ");
  while($dao->fetch()) {
    $params = [
      'id' => $dao->contribution_id,
    ];
    if (!empty($dao->campaign_id)) {
      $params['campaign_id'] = $dao->campaign_id;
    }
    elseif ($dao->campaign_id == 0) {
      CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution SET campaign_id = NULL WHERE id = %1", [1 => [$dao->contribution_id, 'Positive']]);
    }
    if (!empty($dao->fund)) {
      $params['financial_type_id'] = $dao->fund;
    }
    civicrm_api3('Contribution', 'create', $params);
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_ch_contribution_batch WHERE id = " . $dao->id);
  }
  return civicrm_api3_create_success();
}
