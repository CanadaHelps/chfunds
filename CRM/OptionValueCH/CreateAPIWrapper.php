<?php
use CRM_Chfunds_Utils as E;
use CRM_Canadahelps_ExtensionUtils as CHE;

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
        CRM_Core_BAO_OptionValue::create([
          'id' => $id,
          'value' => $apiRequest['params']['value'],
        ]);
      }

    }
    return $apiRequest;
  }
  /**
   * alter the result before returning it to the caller.
   */
  public function toApiOutput($apiRequest, $result) {

    if(isset($apiRequest['params'])) {
      if (array_key_exists('createOptionValCH', $apiRequest['params'])){ 
        $parent_id = $result['id'];
        $chFundLabelValue = $apiRequest['params']['label'];
         $id = civicrm_api3('OptionValue', 'get', [
          'label' => $chFundLabelValue,
          'option_group_id' => $apiRequest['params']['option_group_id'],
          'return' => 'value,id',
        ]);
        if($id['values'] && $id['count'] >0) {
          $duplicatefundToMergeID = [];
          $duplicatefundToMergeValue = [];
          foreach($id['values'] as $index => $chfund){

            //additional check if duplicate ch fund values are being assigned to any fund as parent id?
            $getOptionValueCHFundDetails = civicrm_api3('OptionValueCH', 'get', [
              'value' => $id['values'][$chfund['id']]['value'],
              'option_group_id' => $apiRequest['params']['option_group_id'],
              'return' => 'id'
            ]);
            if($getOptionValueCHFundDetails['values'] && $getOptionValueCHFundDetails['count'] >0) {
              $duplicateFundWithParentID = civicrm_api3('OptionValueCH', 'get', [
              'parent_id'=>$getOptionValueCHFundDetails['id'],
              'return' => 'value,id',
              ]);
              if($duplicateFundWithParentID['values'] && $duplicateFundWithParentID['count'] >0) {
                $duplicatefundToMergeID = array_column($duplicateFundWithParentID['values'], 'id');
                $duplicatefundToMergeValue = array_column($duplicateFundWithParentID['values'], 'value');
              }
            }
          }
          $duplicateOptionValueData = array_merge(array_column($id['values'], 'value'),$duplicatefundToMergeValue);
          $duplicateOptionValueID = array_merge(array_column($id['values'], 'id'),$duplicatefundToMergeID);
          if($duplicateOptionValueData) {
            foreach($duplicateOptionValueData as $k => $val) {
              $values = civicrm_api3('OptionValueCH', 'getsingle', ['value' => $val, 'return' => 'id,value']);
              $optionValueCHID = $values['id'];
              $optionValueCHValue = $values['value'];
              $values = civicrm_api3('OptionValueCH', 'create', ['id' => $optionValueCHID, 'parent_id' => $parent_id]);

              //update value of associated contributions
              E::updateCHContribution($apiRequest['params']['financial_type_id'], $optionValueCHValue);

              //update values for  additional info (fund_13) table values
              $additionalInfoColumn = CHE::getTableNameByName('Additional_info');
              $fundValuecolumn = CHE::getColumnNameByName('Fund');
              $chfundToBeReplaced  = $apiRequest['params']['value'];
              $updatenodesql = "UPDATE $additionalInfoColumn SET $fundValuecolumn = '$chfundToBeReplaced' WHERE $fundValuecolumn = '$optionValueCHValue'";
              CRM_Core_DAO::executeQuery($updatenodesql);
              //delete other Duplicate funds
              CRM_Core_BAO_OptionValue::del($duplicateOptionValueID[$k]);
            }
          }
        }
      }
    }
    return $result;
  }
}
