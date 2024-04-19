<?php
use CRM_Chfunds_ExtensionUtil as E;
use CRM_Chfunds_Utils as EC;
use CRM_Canadahelps_ExtensionUtils as CHE;

/**
 * Collection of upgrade steps.
 */
class CRM_Chfunds_Upgrader extends CRM_Chfunds_Upgrader_Base {

  public function upgrade_1100() {
    $this->ctx->log->info('Applying update 1.1');
    CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_option_value_ch ADD CONSTRAINT UI_fund_ch_fund UNIQUE KEY `UI_fund_ch_fund` (`financial_type_id`,`value`)');
    return TRUE;
  }

  public function upgrade_1200() {
    $this->ctx->log->info('Applying update 1.2');
    $sql = "
      CREATE TABLE IF NOT EXISTS `civicrm_ch_contribution_batch` (
         `id` INT NOT NULL AUTO_INCREMENT , `ch_fund` VARCHAR(10) NOT NULL ,
         `fund` INT(4) NOT NULL ,
         `contribution_id` INT(10) NOT NULL ,
        PRIMARY KEY (`id`)
      ) ENGINE = InnoDB;
    ";
    CRM_Core_DAO::executeQuery($sql);
    return TRUE;
  }

  public function upgrade_1300() {
    $this->ctx->log->info('Applying update 1.3');
    $sql = "
    ALTER TABLE `civicrm_ch_contribution_batch`
      CHANGE `ch_fund` `ch_fund` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
      CHANGE `fund` `fund` INT(11) NOT NULL,
      CHANGE `contribution_id` `contribution_id` INT(11) NOT NULL;
    ";
    CRM_Core_DAO::executeQuery($sql);
    return TRUE;
  }

  public function upgrade_1400() {
    $this->ctx->log->info('Applying update 1.4');
    $sql = "
      ALTER TABLE `civicrm_ch_contribution_batch` ADD COLUMN `campaign_id` INT NULL DEFAULT NULL,
      CHANGE `ch_fund` `ch_fund` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
      CHANGE `fund` `fund` INT(11) NULL DEFAULT NULL
    ";
    CRM_Core_DAO::executeQuery($sql);
    $sql = "
      ALTER TABLE `civicrm_ch_contribution_batch` ADD CONSTRAINT `unique_contribution` UNIQUE KEY (`contribution_id`)
    ";
    CRM_Core_DAO::executeQuery($sql);
    return TRUE;
  }

  public function upgrade_1500() {
    $this->ctx->log->info('Applying update 1.5');

    $revenueFinancialAccountTypeID = array_search('Revenue', CRM_Core_OptionGroup::values('financial_account_type', FALSE, FALSE, FALSE, NULL, 'name'));

    if ($revenueFinancialAccountTypeID) {
      $sql = "
        UPDATE civicrm_financial_account fa INNER JOIN (
        SELECT fa.id, ft.name
        FROM civicrm_financial_account fa
        INNER JOIN civicrm_entity_financial_account efa ON efa.financial_account_id = fa.id AND fa.financial_account_type_id = $revenueFinancialAccountTypeID
        INNER JOIN civicrm_financial_type ft ON ft.id = efa.entity_id AND efa.entity_table = 'civicrm_financial_type'
        WHERE fa.name='Donation' AND ft.name='General Fund' ) temp ON temp.id = fa.id
        SET fa.name = temp.name
        ";
        CRM_Core_DAO::executeQuery($sql);
    }

    return TRUE;
  }

  public function upgrade_1600() {
    $this->ctx->log->info('Applying update 1.6: Setting custom translation function to be the chfunds translation function for check to cheque renaming');
    Civi::settings()->set('customTranslateFunction', 'chfunds_translate');
    return TRUE;
  }

  public function upgrade_1700() {
    $this->ctx->log->info('Applying update 1.7');

    $sql = "UPDATE civicrm_financial_trxn
      SET fee_amount = (0 - fee_amount),
      net_amount = (total_amount - fee_amount)
      WHERE total_amount < 0
      AND fee_amount > 0
      AND to_financial_account_id IS NOT NULL
      AND from_financial_account_id IS NULL
      AND total_amount - fee_amount = net_amount";

    CRM_Core_DAO::executeQuery($sql);
    return TRUE;
  }

  public function upgrade_1800() {
    $this->ctx->log->info('CRM-1578-[DMS] - Duplicates of CH Funds Showing Under Administer > Funds');
    //Adding a new column 'parent_id' for dataTable `civicrm_option_value_ch`
    $sql = "ALTER TABLE `civicrm_option_value_ch` ADD COLUMN `parent_id` INT NULL DEFAULT NULL COMMENT 'parent id of CH option value' AFTER is_enabled_in_ch";
    CRM_Core_DAO::executeQuery($sql);
    return TRUE;

  }

  public function upgrade_1810() {
    
    //first get option group id for CHFund
    $optionValueGroupID = CRM_Core_DAO::singleValueQuery("SELECT `id` FROM `civicrm_option_group` WHERE `name` LIKE 'ch_fund'");
    //Get List of duplicate CH Funds from option value table
    $sql = "SELECT name,COUNT(name) AS count , label FROM `civicrm_option_value` WHERE option_group_id= $optionValueGroupID GROUP BY name HAVING count > 1";
    $results = CRM_Core_DAO::executeQuery($sql)->fetchAll();
    if($results) {
      foreach($results as $key=>$value) {
          //get list of all duplicate fund name in decending order based on label or name
          $getListofDuplicateFunds = CRM_Core_DAO::executeQuery("SELECT value FROM `civicrm_option_value` WHERE `option_group_id` = $optionValueGroupID AND `name` LIKE '".$value['name']."' ORDER BY id DESC")->fetchAll();
          //get first element to make it parent 
          $getLastElementOfArray = reset($getListofDuplicateFunds);
          $parentValueID = $getLastElementOfArray['value'];
          $getParentCHFundDetails = CRM_Core_DAO::executeQuery("SELECT id,value,financial_type_id FROM `civicrm_option_value_ch` WHERE `option_group_id` = $optionValueGroupID AND `value`= '".$parentValueID."' LIMIT 1")->fetchAll();
          //get parent's financial type id and id from civicrm_option_value_ch table
          $mainParentID = $getParentCHFundDetails[0]['id'];
          $mainParentFinancialTypeID = $getParentCHFundDetails[0]['financial_type_id'];
  
          //Now loop through all the duplicate fund and assign parent ID to duplicate one except parent id
          foreach($getListofDuplicateFunds as $dupliFund) {
            if($dupliFund['value'] !==  $parentValueID) {
              $duplicateFundValue = $dupliFund['value'];
              //Now update option_value ch  table with parent id for all old ch funds
              $sql = "UPDATE civicrm_option_value_ch SET parent_id = $mainParentID WHERE  `option_group_id` = $optionValueGroupID AND value= '" . $dupliFund['value']. "'";
              CRM_Core_DAO::executeQuery($sql);
              // do similar thing for contributions which are associated with old funds, update fund_13 table
              $additionalInfoColumn = CHE::getTableNameByName('Additional_info');
              $fundValuecolumn = CHE::getColumnNameByName('Fund');
              //first get contribution id for entity with duplicate ch fund
              $getentityIDContributions = CRM_Core_DAO::executeQuery("SELECT entity_id FROM $additionalInfoColumn WHERE $fundValuecolumn = '".$duplicateFundValue."'")->fetchAll();
              // do civicrm_contribution table update for financial_type_id value based on parent ch fund financial_type_id
              if($getentityIDContributions) {
                foreach($getentityIDContributions as $k=>$v) {
                  $entityID = $v['entity_id'] ;
                  $updatenodesql = "UPDATE `civicrm_contribution` SET financial_type_id = $mainParentFinancialTypeID WHERE id = $entityID";
                  CRM_Core_DAO::executeQuery($updatenodesql);
                  $updatenodesql = "UPDATE $additionalInfoColumn SET $fundValuecolumn = '$parentValueID' WHERE $fundValuecolumn = '$duplicateFundValue'";
                  CRM_Core_DAO::executeQuery($updatenodesql);
                }
              }
              //delete duplicate funds
              $sql = 'DELETE FROM `civicrm_option_value`  WHERE `value` = "'.$duplicateFundValue.'"';
              CRM_Core_DAO::executeQuery($sql);
            }
          }
      }
    }
    return TRUE;
  }

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed.
   *
  public function install() {
    $this->executeSqlFile('sql/myinstall.sql');
  }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   *
  public function postInstall() {
    $customFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'return' => array("id"),
      'name' => "customFieldCreatedViaManagedHook",
    ));
    civicrm_api3('Setting', 'create', array(
      'myWeirdFieldSetting' => array('id' => $customFieldId, 'weirdness' => 1),
    ));
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   *
  public function uninstall() {
   $this->executeSqlFile('sql/myuninstall.sql');
  }

  /**
   * Example: Run a simple query when a module is enabled.
   *
  public function enable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a simple query when a module is disabled.
   *
  public function disable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   *
  public function upgrade_4200() {
    $this->ctx->log->info('Applying update 4200');
    CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
    CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
    return TRUE;
  } // */


  /**
   * Example: Run an external SQL script.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4201() {
    $this->ctx->log->info('Applying update 4201');
    // this path is relative to the extension base dir
    $this->executeSqlFile('sql/upgrade_4201.sql');
    return TRUE;
  } // */


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4202() {
    $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

    $this->addTask(E::ts('Process first step'), 'processPart1', $arg1, $arg2);
    $this->addTask(E::ts('Process second step'), 'processPart2', $arg3, $arg4);
    $this->addTask(E::ts('Process second step'), 'processPart3', $arg5);
    return TRUE;
  }
  public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  public function processPart3($arg5) { sleep(10); return TRUE; }
  // */


  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4203() {
    $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

    $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
    $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
    for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
      $endId = $startId + self::BATCH_SIZE - 1;
      $title = E::ts('Upgrade Batch (%1 => %2)', array(
        1 => $startId,
        2 => $endId,
      ));
      $sql = '
        UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
        WHERE id BETWEEN %1 and %2
      ';
      $params = array(
        1 => array($startId, 'Integer'),
        2 => array($endId, 'Integer'),
      );
      $this->addTask($title, 'executeSql', $sql, $params);
    }
    return TRUE;
  } // */

}
