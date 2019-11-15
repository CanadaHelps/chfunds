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
function _civicrm_api3_job_UpdateCHContributions_spec(&$spec) {}

/**
 * Job.UpdateCHContributions API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_job_UpdateCHContributions($params) {
  $totalContributions = CRM_Core_DAO::singleValueQuery("SELECT COUNT(id) FROM civicrm_ch_contribution_batch");
  $batchSize = 1000;
  $offset = $limit = 0;
  while ($limit < $totalContributions) {
      $limit += $batchSize;
      $sql = "SELECT * FROM civicrm_ch_contribution_batch LIMIT $offset, $limit ;";
      $dao = CRM_Core_DAO::executeQuery($sql);
      while($dao->fetch()) {
        civicrm_api3('Contribution', 'create', [
          'id' => $dao->contribution_id,
          'financial_type_id' => $dao->fund,
        ]);
      }
      $offset += $batchSize + 1;
  }
  return civicrm_api3_create_success();
}
