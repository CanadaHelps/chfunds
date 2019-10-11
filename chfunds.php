<?php

require_once 'chfunds.civix.php';
use CRM_Chfunds_ExtensionUtil as E;

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
      $fundID = _getDefaultOptionValueCH($id)['financial_type_id'];
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
    $chFundsByFinancialType = _getCHFundsByFinancialType();
    foreach ($rows as $id => $row) {
      $chFundLinks[$count] = CRM_Utils_System::url('civicrm/chfunds', 'reset=1&financial_type_id=' . $row['id']);
      $chFunds[$count] = CRM_Utils_Array::value($id, $chFundsByFinancialType, '');
      $count++;
    }
    $page->assign('chFundLinks', json_encode($chFundLinks));
    $page->assign('chFunds', json_encode($chFunds));

    CRM_Core_Region::instance('page-body')->add(array(
      'template' => "CRM/Chfunds/CHFundFinancialTypePage.tpl",
    ));
  }

}

function chfunds_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Admin_Form_Options' && $form->getVar('_gName') == 'ch_fund' && !($form->_action & CRM_Core_Action::DELETE)) {
    CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes($financialTypes, $form->_action);
    $form->add('select', 'financial_type_id',
      ts('Fund'),
      ['' => ts('- select -')] + $financialTypes,
      TRUE
    );
    $enabled = $form->add('checkbox', 'is_active', ts('Enabled?'));
    $enabled->freeze();

    $form->addYesNo('is_enabled_in_ch', ts('Enabled in CanadaHelps'), FALSE, TRUE);
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => "CRM/Chfunds/AddCHFund.tpl",
    ));

    if ($id = $form->getVar('_id')) {
      $defaults = _getDefaultOptionValueCH($id);
      $form->setDefaults($defaults);
    }
  }
  elseif ($formName == 'CRM_Contribute_Form_Contribution' && ($form->_action & CRM_Core_Action::UPDATE)) {
    $fundCustomFieldID = civicrm_api3('CustomField', 'getvalue', ['name' => 'Fund', 'return' => 'id']);
    $selector = 'custom_' . $fundCustomFieldID . '_' . civicrm_api3('Contribution', 'getcount', [
      'contact_id' => $form->_contactID,
      'id' => ['<=' => $form->_id],
      'custom_' . $fundCustomFieldID => ['IS NOT NULL' => 1]]) . '-row';
    CRM_Core_Resources::singleton()->addScript("
      CRM.$(function($) {
        $( document ).ajaxComplete(function(event, xhr, settings) {
          var urlParts = settings.url.split('&');
          if (urlParts[1].includes('subType=') && urlParts[0].includes('civicrm/custom')) {
            $('tr.$selector').insertAfter('tr.crm-contribution-form-block-financial_type_id');
          }
         });
       });
    ");
  }
}

function chfunds_civicrm_postProcess($formName, &$form) {
  if ($formName == 'CRM_Admin_Form_Options' && $form->getVar('_gName') == 'ch_fund') {
    $params = $form->exportValues();

    $optionValueCHID = NULL;
    if ($id = $form->getVar('_id')) {
      $optionValueCHID = _getDefaultOptionValueCH($id)['id'];
    }

    civicrm_api3('OptionValueCH', 'create', [
      'id' => $optionValueCHID,
      'option_group_id' => $form->getVar('_gid'),
      'value' => $params['value'],
      'financial_type_id' => $params['financial_type_id'],
      'is_enabled_in_ch' => $params['is_enabled_in_ch'],
    ]);
  }
}

function _getDefaultOptionValueCH($optionValueID) {
  $params = ['value' => civicrm_api3('OptionValue', 'getvalue', ['id' => $optionValueID, 'return' => 'value'])];
  CRM_Chfunds_BAO_OptionValueCH::retrieve($params, $defaults);

  return $defaults;
}

function _getCHFundsByFinancialType() {
  $optionValueCHFunds = civicrm_api3('OptionValueCH', 'get', ['options' => ['limit' => 0]])['values'];
  $CHFunds = [];

  foreach (civicrm_api3('OptionValue', 'get', ['option_group_id' => 'ch_fund'])['values'] as $chFund) {
    $CHFunds[$chFund['value']] = $chFund['label'];
  }
  $result = [];
  foreach ($optionValueCHFunds as $optionValueCHFund) {
    if (!empty($CHFunds[$optionValueCHFund['value']])) {
      $result[$optionValueCHFund['financial_type_id']][] = $CHFunds[$optionValueCHFund['value']];
    }
  }

  foreach ($result as $k => $v) {
    $result[$k] = implode(', ', $v);
  }

  return $result;
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

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function chfunds_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function chfunds_civicrm_navigationMenu(&$menu) {
  _chfunds_civix_insert_navigation_menu($menu, 'Mailings', array(
    'label' => E::ts('New subliminal message'),
    'name' => 'mailing_subliminal_message',
    'url' => 'civicrm/mailing/subliminal',
    'permission' => 'access CiviMail',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _chfunds_civix_navigationMenu($menu);
} // */
