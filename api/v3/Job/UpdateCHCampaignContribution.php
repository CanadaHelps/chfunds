<?php
use CRM_Chfunds_ExtensionUtil as E;

/**
 * Job.UpdateCHCampaignContribution API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_job_update_c_h_campaign_contribution_spec(&$spec) {
  $spec['batch_size'] = [
    'title' => 'Batch Size',
    'type' => CRM_Utils_Type::T_INT,
  ];
}

/**
 * Job.UpdateCHCampaignContribution API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_job_update_c_h_campaign_contribution($params) {
  $batchSize = CRM_Utils_Array::value('batch_size', $params, 1000);
  $dao = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_ch_campaign_contribution_batch LIMIT 0, $batchSize ");
  while($dao->fetch()) {
    if (!empty($dao->campaign_id)) {
      civicrm_api3('Contribution', 'create', [
        'id' => $dao->contribution_id,
        'campaign_id' => $dao->campaign_id,
      ]);
    }
    else {
      // A Zero will represnt no campaign. 
      CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution SET campaign_id = NULL WHERE id = %1", [1 => [$dao->id, 'Positive']]);
    }
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_ch_campaign_contribution_batch WHERE id = %1", [1 => [$dao->id, 'Positive']]);
  }
  return civicrm_api3_create_success();
}
