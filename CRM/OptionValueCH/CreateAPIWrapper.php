<?php
use CRM_Chfunds_ExtensionUtil as E;

class CRM_OptionValueCH_CreateAPIWrapper implements API_Wrapper {
  /**
   * the wrapper contains a method that allows you to alter the parameters of the api request (including the action and the entity)
   */
  public function fromApiInput($apiRequest) {
    // if its a OptionValueCH edit identified by $apiRequest['params']['id']
    if ($apiRequest['entity'] == 'OptionValueCH' && !empty($apiRequest['params']['id'])) {
      $values = civicrm_api3('OptionValueCH', 'getsingle', ['id' => $apiRequest['params']['id']]);
      if (!empty($apiRequest['params']['option_group_id'])) {
        $optionGroupID = $apiRequest['params']['option_group_id'];
      }
      else {
        $optionGroupID = FALSE;
      }
      // if you are changing the financial type then update the associated contribution's Fund too
      if (!empty($apiRequest['params']['financial_type_id']) && $apiRequest['params']['financial_type_id'] != $values['financial_type_id']) {
        E::updateCHContribution($apiRequest['params']['financial_type_id'], $values['value']);
      }
      if (!empty($apiRequest['params']['value']) && $apiRequest['params']['value'] != $values['value']) {
        // if you are changing the value then update the associated option value too
        $id = civicrm_api3('OptionValue', 'getvalue', [
          'value' => $values['value'],
          'option_group_id' => is_int($optionGroupID) ? $optionGroupID : civicrm_api3('OptionGroup', 'getvalue', ['name' => 'ch_fund', 'return' => 'id']),
          'return' => 'id',
        ]);
        CRM_Core_DAO::executeQuery(sprintf("UPDATE civicrm_option_value SET `value` = '%s' WHERE id = %d", $apiRequest['params']['value'], $id));
        CRM_Core_BAO_CustomOption::updateValue($id, $apiRequest['params']['value']);
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
