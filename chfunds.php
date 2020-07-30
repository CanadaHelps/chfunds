<?php

require_once 'chfunds.civix.php';
require_once 'chfunds.permitted-roles.php';

use CRM_Chfunds_Utils as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function chfunds_civicrm_config(&$config) {
  _chfunds_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function chfunds_civicrm_xmlMenu(&$files) {
  _chfunds_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function chfunds_civicrm_install() {
  _chfunds_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function chfunds_civicrm_postInstall() {
  _chfunds_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function chfunds_civicrm_uninstall() {
  _chfunds_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function chfunds_civicrm_enable() {
  _chfunds_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function chfunds_civicrm_disable() {
  _chfunds_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function chfunds_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _chfunds_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function chfunds_civicrm_managed(&$entities) {
  _chfunds_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function chfunds_civicrm_caseTypes(&$caseTypes) {
  _chfunds_civix_civicrm_caseTypes($caseTypes);
}

function chfunds_civicrm_permission(&$permissions) {
  $permissions['CH admin miscellaneous'] = [ts('CiviCRM: CH admin miscellaneous')];
  $permissions['assign CH Fund'] = [ts('CiviCRM: assign CH Fund')];

  __addCHFundPermssionToDrupalRole();
}

function __addCHFundPermssionToDrupalRole() {
  // ensure that its a drupal site and user module is enabled
  if (CRM_Core_Config::singleton()->userFramework != 'Drupal' || !module_exists('user')) {
    return;
  }

  $settings = unserialize(DRUPAL_ROLE_PERMISSIONS);

  foreach (user_roles() as $rid => $name) {
    if (in_array(strtolower($name), array_keys($settings))) {
      foreach ($settings[strtolower($name)] as $permission) {
        $result = db_query("SELECT * FROM {role_permission} where rid = $rid AND permission = '$permission'");
        $found = FALSE;
        foreach ($result as $row) {
          $found = ($row->permission == $permission);
        }
        if (!$found) {
          // delete all permission assigned to the other role
          db_delete('role_permission')
            ->condition('permission', $permission)
            ->execute();

          // assign permission to specified role
          db_merge('role_permission')->key(
            [
              'rid' => $rid,
              'permission' => $permission,
            ]
          )->fields(['module' => 'civicrm'])
          ->execute();

          // Clear the user access cache.
          drupal_static_reset('user_access');
          drupal_static_reset('user_role_permissions');
        }
      }
    }
  }
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function chfunds_civicrm_angularModules(&$angularModules) {
  _chfunds_civix_civicrm_angularModules($angularModules);
}

function chfunds_civicrm_apiWrappers(&$wrappers, $apiRequest) {
  if ($apiRequest['entity'] == 'OptionValue') {
    if ($apiRequest['action'] == 'create') {
      $wrappers[] = new CRM_OptionValue_CreateAPIWrapper();
    }
    elseif ($apiRequest['action'] == 'delete') {
      $wrappers[] = new CRM_OptionValue_DeleteAPIWrapper();
    }
  }
  if ($apiRequest['entity'] == 'OptionValueCH' && $apiRequest['action'] == 'create') {
    $wrappers[] = new CRM_OptionValueCH_CreateAPIWrapper();
  }
  if ($apiRequest['entity'] == 'ContributionPage' && $apiRequest['action'] == 'create') {
    $wrappers[] = new CRM_ContributionPage_CreateAPIWrapper();
  }
}

function chfunds_civicrm_pageRun(&$page) {
  if (get_class($page) == 'CRM_Admin_Page_Options' &&
    CRM_Utils_Array::value('gid', $_GET) == civicrm_api3('OptionGroup', 'getvalue', ['name' => 'ch_fund', 'return' => 'id'])
  ) {
    $rows = CRM_Core_Smarty::singleton()->get_template_vars('rows');
    CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes($financialTypes);
    $count = 1;
    $funds = [];
    foreach ($rows as $id => $row) {
      $fundID = E::getDefaultOptionValueCH($id)['financial_type_id'];
      $funds[$count] = CRM_Utils_Array::value($fundID, $financialTypes, '');
      $count++;
    }
    $page->assign('funds', json_encode($funds));
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => "CRM/Chfunds/CHFundOptionPage.tpl",
    ));
  }
  elseif (get_class($page) == 'CRM_Financial_Page_FinancialType') {
    $rows = CRM_Core_Smarty::singleton()->get_template_vars('rows');
    $count = 1;
    $chFundLinks = $chFunds = [];
    $chFundsByFinancialType = E::getCHFundsByFinancialType();
    foreach ($rows as $id => $row) {
      $chFunds[$count] = CRM_Utils_Array::value($id, $chFundsByFinancialType, '');
      if (CRM_Core_Permission::check('assign CH Fund') || $row['name'] == 'Unassigned CH Fund') {
        $chFundLinks[$count] = CRM_Utils_System::url('civicrm/chfunds', 'reset=1&financial_type_id=' . $row['id']);
      }

      $count++;
    }
    $page->assign('chFundLinks', json_encode($chFundLinks));
    $page->assign('chFunds', str_replace('"', '\"', str_replace("'", "\'", json_encode($chFunds))));

    CRM_Core_Region::instance('page-body')->add(array(
      'template' => "CRM/Chfunds/CHFundFinancialTypePage.tpl",
    ));
  }

}

function chfunds_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Financial_Form_FinancialAccount' && !($form->_action & CRM_Core_Action::DELETE)) {
    if ($form->_action & CRM_Core_Action::UPDATE) {
      $form->setDefaults(['contact_id' => CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Domain', CRM_Core_Config::domainID(), 'contact_id')]);
    }
    $postHelpFT = ts('These Account Types are used to help validate the Assignment of GL Accounts to Funds according to various relationships. For example, only GL Accounts that are of GL Account Type = "Asset" can be set up as "Accounts Receivable Account is".');
    $postHelGLCode = ts('Optionally enter the corresponding account code used in your accounting system. This code will be available for contribution export, and included in accounting batch exports.');
    $postHelGLTypeCode = ts('Optionally enter an account type code for this account. Account type codes are required for QuickBooks integration and will be included in all accounting batch exports.');

    CRM_Core_Resources::singleton()->addScript(
      "CRM.$(function($) {
        $('.crm-contribution-form-block-organisation_name').addClass('hiddenElement');
        $('.crm-contribution-form-block-tax_rate').addClass('hiddenElement');
        $('.crm-contribution-form-block-financial_account_type_id .html-adjust select').after('<br/><span class=\"description\">$postHelpFT</span>');
        $('.crm-contribution-form-block-accounting_code .html-adjust .description').html('$postHelGLCode');
        $('.crm-contribution-form-block-account_type_code .html-adjust .description').html('$postHelGLTypeCode');
      });
    ");
  }
  if ($formName == 'CRM_Financial_Form_FinancialTypeAccount' && !($form->_action & CRM_Core_Action::DELETE)) {
    $params['orderColumn'] = 'label';
    $AccountTypeRelationship = array_flip(CRM_Core_PseudoConstant::get('CRM_Financial_DAO_EntityFinancialAccount', 'account_relationship', $params));
    foreach ([
      'Accounts Receivable Account is',
      'Cost of Sales Account is',
      'Credit/Contra Revenue Account is',
      'Deferred Revenue Account is',
      'Discount Account is',
      'Premium Inventory Account is',
    ] as $type) {
      unset($AccountTypeRelationship[$type]);
    }
    $AccountTypeRelationship = array_flip($AccountTypeRelationship);
    $isARFlag = $form->getVar('_isARFlag');
    $element = $form->add('select',
      'account_relationship',
      ts('Financial Account Relationship'),
      ['select' => ts('- Select Financial Account Relationship -')] + $AccountTypeRelationship,
      TRUE
    );

    if ($isARFlag) {
      $element->freeze();
    }
  }
  if ($formName == 'CRM_Admin_Form_Options' && $form->getVar('_gName') == 'ch_fund' && !($form->_action & CRM_Core_Action::DELETE)) {

    CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes($financialTypes, $form->_action);
    if ($id = $form->getVar('_id')) {
      $defaults = E::getDefaultOptionValueCH($id);
      $form->setDefaults($defaults);
    }
    $condition = empty($form->getVar('_id')) ? '' : "WHERE value <> '" . civicrm_api3('OptionValue', 'getvalue', ['id' => $form->getVar('_id'), 'return' => 'value']) . "'";
    E::filterFinancialTypes($financialTypes, $condition, CRM_Utils_Array::value('financial_type_id', $defaults));
    $form->add('select', 'financial_type_id',
      ts('Fund'),
      ['' => ts('- select -')] + $financialTypes,
      TRUE
    );

    $form->add('checkbox', 'is_active', ts('Enabled?'))->freeze();

    if (!CRM_Core_Permission::check('CH admin miscellaneous')) {
      $form->add('text',
        'label',
        ts('Label'),
        CRM_Core_DAO::getAttribute('CRM_Core_DAO_OptionValue', 'label'),
        TRUE
      )->freeze();
    }

    $choice = [
      $form->createElement('radio', NULL, '11', ts('Yes'), '1', ['id_suffix' => 'is_enabled_in_ch']),
      $form->createElement('radio', NULL, '11', ts('No'), '0', ['id_suffix' => 'is_enabled_in_ch']),
    ];
    $form->addGroup($choice, 'is_enabled_in_ch', ts('Enabled in CanadaHelps'))->freeze();

    CRM_Core_Region::instance('page-body')->add(array(
      'template' => "CRM/Chfunds/AddCHFund.tpl",
    ));

    $value = $form->add('text',
      'value',
      ts('Value'),
      CRM_Core_DAO::getAttribute('CRM_Core_DAO_OptionValue', 'value'),
      TRUE
    );
    $value->freeze();
  }
}

function chfunds_civicrm_postProcess($formName, &$form) {
  if ($formName == 'CRM_Admin_Form_Options' && $form->getVar('_gName') == 'ch_fund') {
    $params = $form->exportValues();

    $optionValueCHID = NULL;
    if ($id = $form->getVar('_id')) {
      $optionValueCHID = E::getDefaultOptionValueCH($id)['id'];
    }

    civicrm_api3('OptionValueCH', 'create', [
      'id' => $optionValueCHID,
      'option_group_id' => $form->getVar('_gid'),
      'value' => $params['value'],
      'financial_type_id' => $params['financial_type_id'],
      'is_enabled_in_ch' => $params['is_enabled_in_ch'],
    ]);
    E::updateCHContribution($params['financial_type_id'], $params['value']);
  }
  if ($formName == 'CRM_Contribute_Form_ContributionPage_Settings') {
    $params = $form->exportValues();
    E::updateContributionCampaign($params['campaign_id'] ?? 0, $form->getVar('_id'));
  }
  if ('CRM_Financial_Form_FinancialType' == $formName && empty($form->getVar('_id'))) {
    if ($fundName = CRM_Utils_Array::value('name', $form->exportValues())) {
      $financialTypeID = civicrm_api3('FinancialType', 'getvalue', ['name'  =>  $fundName, 'options' =>  ['limit' => 1], 'return' => 'id']);
      if ($financialTypeID) {
        E::removeCoSAssignmentFromFund($financialTypeID);
      }
    }
  }
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function chfunds_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _chfunds_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function chfunds_civicrm_entityTypes(&$entityTypes) {
  _chfunds_civix_civicrm_entityTypes($entityTypes);
}

function chfunds_civicrm_pre($op, $objectName, $id, &$params) {
  if ($objectName === 'Contribution' && $op == 'create') {
    if (!empty($params['contribution_page_id'])) {
      $params['campaign_id'] = CRM_Utils_Array::value('campaign_id', $params, CRM_Utils_Array::value('campaign_id', civicrm_api3('ContributionPage', 'getsingle', ['id' => $params['contribution_page_id']])));
    }
  }
}

function chfunds_translate($text, $params) {
  switch ($text) {
    case 'unique transaction id. may be processor id, bank id + trans id, or account number + check number... depending on payment_method':
      $text = 'unique transaction id. may be processor id, bank id + trans id, or account number + cheque number... depending on payment_method';
      break;

    case 'Check Number':
      $text = 'Cheque Number';
      break;

    case 'This Transaction ID already exists in the database. Include the account number for checks.':
      $text = 'This Transaction ID already exists in the database. Include the account number for cheques.';
      break;

    case 'Instructions added to Confirmation and Thank-you pages, as well as the confirmation email, when the user selects the \'pay later\' option (e.g. \'Mail your check to ... within 3 business days.\').':
      $text = 'Instructions added to Confirmation and Thank-you pages, as well as the confirmation email, when the user selects the \'pay later\' option (e.g. \'Mail your cheque to ... within 3 business days.\').';
      break;

    case 'Record Contribution (Check, Cash, EFT ...)':
      $text = 'Record Contribution (Cheque, Cash, EFT ...)';
      break;

    case 'I will send payment by check':
      $text = 'I will send payment by cheque';
      break;

    case 'Check this box if you want to give users the option to submit payment offline (e.g. mail in a check, call in a credit card, etc.).':
      $text = 'Check this box if you want to give users the option to submit payment offline (e.g. mail in a cheque, call in a credit card, etc.).';
      break;

    case "NOTE: Alternatively, you can enable the <strong>Pay Later</strong> feature below without setting up a payment processor. All users will then be asked to submit payment offline (e.g. mail in a check, call in a credit card, etc.).":
      $text = 'NOTE: Alternatively, you can enable the <strong>Pay Later</strong> feature below without setting up a payment processor. All users will then be asked to submit payment offline (e.g. mail in a cheque, call in a credit card, etc.).';
      break;

  }
  return CRM_Core_I18n::singleton()->crm_translate($text, $params);
}

/**
 * Implements hook_civicrm_validateForm().
 *
 * @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_validateForm/
 */
function chfunds_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ($formName === 'CRM_Contribute_Form_ContributionPage_Settings') {
    if (array_key_exists('title', $form->_errors)) {
      if ($form->_errors['title'] === 'Please do not use \'/\' in Title') {
        unset($form->_errors['title']);
      }
    }
  }
}
