<?php

class CRM_Chfunds_Utils {

  public static function getFinancialTypeByCHFund($chFundID) {
    $values = civicrm_api3('OptionValueCH', 'get', [
      'value' => $chFundID,
      'sequential' => 1,
      'return' => ["financial_type_id"],
    ])['values'][0];

    return CRM_Utils_Array::value('financial_type_id', $values);
  }
  //CRM-1578- create function to get orginal option value for child CH fund which can associate with contribution
  public static function getContributionCHFundValue($chFundID,$params) {
    $paramData = array();
    $values = civicrm_api3('OptionValue', 'get', [
      'value' => $chFundID,
      'sequential' => 1
    ])['values'][0];
    if(empty($values))
    {
      $OptionCHvalues = civicrm_api3('OptionValueCH', 'get', [
        'value' => $chFundID,
        'return' => ["value", "parent_id"],
        'api.OptionValueCH.getsingle' =>  [
          'id' => "\$value.parent_id",
          'return' => ["value", "parent_id"],
        ],
      ]);
      if(isset($OptionCHvalues['values'][$OptionCHvalues['id']]['api.OptionValueCH.getsingle']) && !empty($OptionCHvalues['values'][$OptionCHvalues['id']]['api.OptionValueCH.getsingle']))
      {
        $params['ch_fund'] = $OptionCHvalues['values'][$OptionCHvalues['id']]['api.OptionValueCH.getsingle']['value'];
      }
    }
    return CRM_Utils_Array::value('ch_fund', $params, CRM_Utils_Array::value('ch_fund_id', $params));
  }


  public static function getCHFundCustomID() {
    return civicrm_api3('CustomField', 'getvalue', ['name' => 'Fund', 'return' => 'id']);
  }

  public static function getDefaultOptionValueCH($optionValueID) {
    $params = ['value' => civicrm_api3('OptionValue', 'getvalue', ['id' => $optionValueID, 'return' => 'value'])];
    CRM_Chfunds_BAO_OptionValueCH::retrieve($params, $defaults);

    return $defaults;
  }

  public static function getCHFundsByFinancialType() {
    $optionValueCHFunds = civicrm_api3('OptionValueCH', 'get', ['options' => ['limit' => 0]])['values'];
    $CHFunds = [];

    foreach (civicrm_api3('OptionValue', 'get', ['option_group_id' => 'ch_fund', 'options' => ['limit' => 0]])['values'] as $chFund) {
      $CHFunds[$chFund['value']] = $chFund['label'];
    }
    $result = [];
    foreach ($optionValueCHFunds as $optionValueCHFund) {
      if (!empty($CHFunds[$optionValueCHFund['value']])) {
        $result[$optionValueCHFund['financial_type_id']][] = $CHFunds[$optionValueCHFund['value']];
      }
    }

    foreach ($result as $k => $v) {
      asort($v);
      $result[$k] = implode(', ', $v);
    }

    return $result;
  }

  public static function getMappedItem($column, $condition = '') {
    return explode(',', CRM_Core_DAO::singleValueQuery("SELECT GROUP_CONCAT(DISTINCT {$column}) FROM civicrm_option_value_ch {$condition} "));
  }

  public static function filterFinancialTypes(&$financialTypes, $condition, $financialTypeID = NULL) {
    $mappedFinancialTypes = self::getMappedItem('financial_type_id', $condition);
    foreach ($financialTypes as $key => $label) {
      if (in_array($key, $mappedFinancialTypes) && !empty($financialTypeID) && $key != $financialTypeID) {
        unset($financialTypes[$key]);
      }
    }
  }

  public static function updateContributions($params, $CHFund, $entity = 'Contribution') {
    $contributions = civicrm_api3('CHContribution', 'get', [
      'ch_fund' => $CHFund,
      'options' => ['limit' => 0],
    ])['values'];
    foreach ($contributions as $id => $value) {
      civicrm_api3($entity, 'create', array_merge(
          ['id' => $value['id']],
          $params
        )
      );
    }
  }

  public static function updateCHContribution($financialTypeID, $chFund) {
    $params = [
      'custom_' . self::getCHFundCustomID() => $chFund,
    ];
    $totalContributions = civicrm_api3('Contribution', 'getcount', $params);
    if ($totalContributions > 0) {
      $batchSize = 1000;
      $offset = $limit = 0;
      while ($limit < $totalContributions) {
        $limit += $batchSize;
        $contributions = civicrm_api3('Contribution', 'get', array_merge($params, [
          'options' => [
            'limit' => $limit,
            'offset' => $offset,
          ],
          "return" => ["id", "financial_type_id"],
        ]))['values'];
        $values = [];
        foreach ($contributions as $id => $value) {
          if ($value['financial_type_id'] != $financialTypeID) {
            $values[] = sprintf(
              " ( '%s', %d, %d ) ",
              $chFund,
              $financialTypeID,
              $value['id']
            );
          }
        }
        if (!empty($values)) {
          $sql = "INSERT INTO civicrm_ch_contribution_batch(`ch_fund`, `fund`, `contribution_id`) VALUES " . implode(', ', $values) . " ON DUPLICATE KEY UPDATE ch_fund=VALUES(ch_fund), fund=VALUES(fund);";
          CRM_Core_DAO::executeQuery($sql);
        }
        $offset += $batchSize + 1;
      }
    }
  }

  /**
   * Updates all contributions assigned to a Contribution Page (Campagin) to newly mapped CiviCampaign (CampaignGroup)
   */
  public static function updateContributionCampaign($campaignId, $contributionPageId) {
    $params = [
      'contribution_page_id' => $contributionPageId,
    ];
    $totalContributions = civicrm_api3('Contribution', 'getcount', $params);
    if ($totalContributions > 0) {
      $batchSize = 1000;
      $offset = $limit = 0;
      while ($limit < $totalContributions) {
        $limit += $batchSize;
        $contributions = civicrm_api3('Contribution', 'get', array_merge($params, [
          'options' => [
            'limit' => $limit,
            'offset' => $offset,
          ],
          "return" => ["id", "campaign_id"],
        ]))['values'];
        $values = [];
        foreach ($contributions as $id => $value) {
          if (!empty($value['campaign_id'])) {
            if ($value['campaign_id'] != $campaignId) {
              $values[] = sprintf(
                " ( %d, %d ) ",
                $value['id'],
                $campaignId
              );
            }
          }
          else {
            $values[] = sprintf(
              " ( %d, %d ) ",
              $value['id'],
              $campaignId
            );
          }
        }
        if (!empty($values)) {
          $sql = "INSERT INTO civicrm_ch_contribution_batch(`contribution_id`, `campaign_id`) VALUES " . implode(', ', $values) . " ON DUPLICATE KEY UPDATE campaign_id=VALUES(campaign_id);";
          CRM_Core_DAO::executeQuery($sql);
        }
        $offset += $batchSize + 1;
      }
    }
  }

  public static function removeCoSAssignmentFromFund($financialTypeID) {
    $CoSfinancialAccountTypeID = array_search('Cost of Sales', CRM_Core_OptionGroup::values('financial_account_type', FALSE, FALSE, FALSE, NULL, 'name'));
    if ($CoSfinancialAccountTypeID) {
      CRM_Core_DAO::executeQuery("DELETE efa.* FROM civicrm_entity_financial_account efa
        INNER JOIN civicrm_financial_account fa ON fa.id = efa.financial_account_id
        WHERE efa.entity_table = 'civicrm_financial_type' AND fa.financial_account_type_id = %1 AND efa.entity_id = %2", [
        1 => [$CoSfinancialAccountTypeID, 'Positive'],
        2 => [$financialTypeID, 'Positive'],
      ]);
    }
  }

}
