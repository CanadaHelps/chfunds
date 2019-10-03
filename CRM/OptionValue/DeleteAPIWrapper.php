<?php
require_once __DIR__ . '/../../chfunds.constants.php';

class CRM_OptionValue_DeleteAPIWrapper implements API_Wrapper {
  /**
   * the wrapper contains a method that allows you to alter the parameters of the api request (including the action and the entity)
   */
  public function fromApiInput($apiRequest) {
    if ($apiRequest['entity'] == 'OptionValue' && !empty($apiRequest['params']['id'])) {
      $optionValue = civicrm_api3('OptionValue', 'getsingle', ['id' => $apiRequest['params']['id']]);
      if (civicrm_api3('OptionGroup', 'getvalue', ['id' => $optionValue['option_group_id'], 'return' => 'name']) == 'ch_fund') {
        $count = civicrm_api3('Contribution', 'getcount', [
          'custom_' . CH_FUND_CF_ID => $optionValue['value'],
        ]);
        if ($count > 0) {
          throw new API_Exception("CH Fund cannot be deleted as it linked with {$count} contribution(s).");
        }
        else {
          foreach(civicrm_api3('OptionValueCH', 'get', ['value' => $optionValue['value']])['values'] as $value) {
            civicrm_api3('OptionValueCH', 'delete', ['id' => $value['id']]);
          }
        }
      }
    }
    return $apiRequest;
  }
  /**
   * alter the result before returning it to the caller.
   */
  public function toApiOutput($apiRequest, $result) {
    return $result;
  }
}
