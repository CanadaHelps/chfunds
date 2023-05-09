<?php
use CRM_Chfunds_Utils as E;

class CRM_OptionValue_DeleteAPIWrapper implements API_Wrapper {
  /**
   * the wrapper contains a method that allows you to alter the parameters of the api request (including the action and the entity)
   */
  public function fromApiInput($apiRequest) {
    if ($apiRequest['entity'] == 'OptionValue' && !empty($apiRequest['params']['id'])) {
      $optionValue = civicrm_api3('OptionValue', 'getsingle', ['id' => $apiRequest['params']['id']]);
      $fundCustomFieldID = E::getCHFundCustomID();
      if (civicrm_api3('OptionGroup', 'getvalue', ['id' => $optionValue['option_group_id'], 'return' => 'name']) == 'ch_fund') {
        $count = civicrm_api3('Contribution', 'getcount', [
          'custom_' . $fundCustomFieldID => $optionValue['value'],
        ]);
        if ($count > 0) {
          throw new API_Exception("CH Fund cannot be deleted as it linked with {$count} contribution(s).");
        }
        else {
          foreach(civicrm_api3('OptionValueCH', 'get', ['value' => $optionValue['value']])['values'] as $value) {
            //CRM-1578 When removal optionValue CH parent value make sure to remove child CHoptionValues
            if(civicrm_api3('OptionValueCH', 'get', ['parent_id' => $value['id']])['values'])
            {
              foreach(civicrm_api3('OptionValueCH', 'get', ['parent_id' => $value['id']])['values'] as $kdel=>$vdel)
              {
                civicrm_api3('OptionValueCH', 'delete', ['id' => $vdel['id']]);
              }
            }
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
