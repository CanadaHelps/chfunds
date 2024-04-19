<?php
class CRM_OptionValue_CreateAPIWrapper implements API_Wrapper {
  /**
   * the wrapper contains a method that allows you to alter the parameters of the api request (including the action and the entity)
   */
  public function fromApiInput($apiRequest) {
    if ($apiRequest['entity'] == 'OptionValue' && (!empty($apiRequest['params']['option_group_id']) || !empty($apiRequest['params']['option_group_name']))) {
      $optionGroupID = CRM_Utils_Array::value('option_group_id', $apiRequest['params']) ?: $apiRequest['params']['option_group_name'];
      if ((is_string($optionGroupID) && $optionGroupID == 'ch_fund') ||
        (is_int($optionGroupID) &&
          (civicrm_api3('OptionGroup', 'getvalue', ['id' => $optionGroupID, 'return' => 'name']) == 'ch_fund')
        )
      ) {
        // $params array hold the parameter of the corresponsing OptionValueCH relationship to created if a ch_fund option value is created OR
        //   OptionValueCH relationship to updated if the ch_fund option value is updated
        $params = [
          'option_group_id' => is_int($optionGroupID) ? $optionGroupID : civicrm_api3('OptionGroup', 'getvalue', ['name' => 'ch_fund', 'return' => 'id']),
          'value' => $apiRequest['params']['value'],
          'is_enabled_in_ch' => 0,
        ];
        // present of 'id' in OptionValue.create API call decides wether its an edit or not
        if (!empty($apiRequest['params']['id'])) {
          // if its a OptionValue value update then fetch the corresponding OptionValueCH mapping and use its ID to be set the in the parameter,
          //  so that corresponsing relationship gets updated by updating the value
          $OVCHid = civicrm_api3('OptionValueCH', 'getsingle', [
            'value' => civicrm_api3('OptionValue', 'getsingle', ['id' => $apiRequest['params']['id']])['value'], // based on OptionValue ID fetch the old option value and use it as a filter to fetch respective OptionValueCH relationship
            'options' => [
              'limit' => 1,
            ],
          ])['id'];
          $params['id'] = $OVCHid;
        }
        else {
          $duplicateOptionValue = false;
          //CRM-1578 identify duplicate option value for CH fund based on name
          if(isset($apiRequest['params']['label']) &&!empty($apiRequest['params']['label']))
          {
            $optionValueName = $apiRequest['params']['label'];
            // Allocate newly created CH Fund option to 'General Fund'
            $defaultFund = civicrm_api3('FinancialType', 'get', ['name' => 'General Fund', 'return' => 'id']);
            if(!$defaultFund['id']) {
              $defaultFund = civicrm_api3('FinancialType', 'get', ['name' => 'Unassigned CH Fund', 'return' => 'id']);
            }
            $params['financial_type_id'] = $defaultFund['id'];
            //Here we will check if fund is active or not ?
            if(isset($apiRequest['params']['is_active'])) {
              $optionValueActiveFund = $apiRequest['params']['is_active'];
            }

            if($optionValueActiveFund) {
              $params['label'] = $optionValueName;
              $params['createOptionValCH'] = true;
              
              //check if duplicate OptionValue present with the same label
              $id = civicrm_api3('OptionValue', 'get', [
                'label' => $optionValueName,
                'option_group_id' => $apiRequest['params']['option_group_id'],
                'return' => 'value,id',
              ]);
              if($id['values'] && $id['count'] >0) {
                foreach($id['values'] as $index => $chfund){
                  $option_group_id = $apiRequest['params']['option_group_id'];
                  //fetch OptionValueCH fund  which is a parent ch fund and newly created OptionValueCH fund would have this same parent
                  $getOptionValueCHFundDetails = civicrm_api3('OptionValueCH', 'get', [
                    'value' => $id['values'][$chfund['id']]['value'],
                    'option_group_id' => is_int($option_group_id) ? $option_group_id : civicrm_api3('OptionGroup', 'getvalue', ['name' => 'ch_fund', 'return' => 'id']),
                    'parent_id' => ['IS NULL' => 1],
                    'return' => 'id,financial_type_id'
                  ]);
                  
                  if($getOptionValueCHFundDetails['values'] && $getOptionValueCHFundDetails['count'] >0) {
                    //Assigning parent_id,financial_type_id to this newly to be created OptionValueCH fund 
                    $params['financial_type_id'] = $getOptionValueCHFundDetails['values'][$getOptionValueCHFundDetails['id']]['financial_type_id'];
                    $params['parent_id'] = $getOptionValueCHFundDetails['id'];

                    $duplicateOptionValue = true;
                    $alreadyExistingFundId = $chfund['id'];
                  }
                }
              }
            }else{
              $params['parent_id'] = NULL;
            }

           // always enusre that whenever a new ch_fund optionValue is created its always reserved so that it cant be deleted from UI
           $apiRequest['params']['is_reserved'] = 1;
          }
          //If OptionValue is alreadt exist , we prevent this createAPI and change action to get and provide alredy existing optionValue data
          if($duplicateOptionValue === true)
          {
            $apiRequest['action'] = 'get';
            $version_value = $apiRequest['params']['version'];
            $option_group_id = $apiRequest['params']['option_group_id'];
            $option_value_name = $apiRequest['params']['label'];
            unset($apiRequest['params']);
            $newParams = [
              'id' => $alreadyExistingFundId,
              'version' => $version_value,
              'sequential' => 1,
              'option_group_id' => $option_group_id,
              'prettyprint' => 1,
              'check_permissions' => 1,
              'label' => $option_value_name
            ];
            $apiRequest['params']= $newParams;
            $apiRequest['function']= 'civicrm_api3_option_value_get';
          }
        }
        // create or update OptionValueCH relationship
        civicrm_api3('OptionValueCH', 'create', $params);
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
