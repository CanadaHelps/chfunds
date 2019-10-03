<?php
class CRM_OptionValue_CreateAPIWrapper implements API_Wrapper {
  /**
   * the wrapper contains a method that allows you to alter the parameters of the api request (including the action and the entity)
   */
  public function fromApiInput($apiRequest) {
    if ($apiRequest['entity'] == 'OptionValue' && !empty($apiRequest['params']['option_group_id'])) {
      $optionGroupID = $apiRequest['params']['option_group_id'];
      if ((is_string($optionGroupID) && $optionGroupID == 'ch_fund') ||
        (is_int($optionGroupID) &&
          (civicrm_api3('OptionGroup', 'getvalue', ['id' => $optionGroupID, 'return' => 'name']) == 'ch_fund')
        )
      ) {
        $apiRequest['params']['is_reserved'] = 1;
        civicrm_api3('OptionValueCH', 'create', [
          'option_group_id' => is_int($optionGroupID) ? $optionGroupID : civicrm_api3('OptionGroup', 'getvalue', ['name' => 'ch_fund', 'return' => 'id']),
          'financial_type_id' => civicrm_api3('FinancialType', 'getvalue', ['name' => 'Unassigned CH Fund', 'return' => 'id']),
          'value' => $apiRequest['params']['value'],
          'is_enabled_in_ch' => 0,
        ]);
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
