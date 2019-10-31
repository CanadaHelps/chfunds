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
    //_restorCHOption();
    $rows = CRM_Core_Smarty::singleton()->get_template_vars('rows');
    $count = 1;
    $chFundLinks = $chFunds = [];
    $chFundsByFinancialType = E::getCHFundsByFinancialType();
    foreach ($rows as $id => $row) {
      $chFundLinks[$count] = CRM_Utils_System::url('civicrm/chfunds', 'reset=1&financial_type_id=' . $row['id']);
      $chFunds[$count] = CRM_Utils_Array::value($id, $chFundsByFinancialType, '');
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
  if ($formName == 'CRM_Admin_Form_Options' && $form->getVar('_gName') == 'ch_fund' && !($form->_action & CRM_Core_Action::DELETE)) {
    CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes($financialTypes, $form->_action);
    $condition = empty($form->getVar('_id')) ? '' : "WHERE value <> '" . civicrm_api3('OptionValue', 'getvalue', ['id' => $form->getVar('_id'), 'return' => 'value']) . "'";
    E::filterFinancialTypes($financialTypes, $condition);
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
      $defaults = E::getDefaultOptionValueCH($id);
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
      $optionValueCHID = E::getDefaultOptionValueCH($id)['id'];
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

function chfunds_civicrm_pre($op, $entityName, $objectID, &$params) {
  if ('edit' == $op && $entityName == 'OptionValueCH') {
    $oldFinancialTypeID = CRM_Core_DAO::singleValueQuery("SELECT financial_type_id FROM civicrm_option_value_ch WHERE id = $objectID LIMIT 1");
    $dao = new CRM_Chfunds_DAO_OptionValueCH();
    $dao->id = $objectID;
    $dao->find(TRUE);
    if (!empty($params['financial_type_id']) && $dao->financial_type_id != $params['financial_type_id']) {
      E::updateContributions(['financial_type_id' => $params['financial_type_id']], $dao->value);
    }
    elseif (!empty($params['value']) && $dao->value != $params['value']) {
      E::updateContributions(['ch_fund' => $params['value']], $dao->value, 'CHContribution');
    }
  }
}

function _restorCHOption() {
  $count = civicrm_api3('OptionValue', 'getcount', ['option_group_name' => 'ch_fund']);
  if ($count > 0) return;
  $chOptions = [
  ["Test fund","CH+1"                                              ],
  ["CH Fund","CH+Fund+121"                                         ],
  ["Give30","CH+10656"                                             ],
  ["CBC Turkey Drive","CH+15883"                                   ],
  ["Stuff-A-Bus ","CH+16206"                                       ],
  ["CBC Turkey Drive","CH+22719"                                   ],
  ["ETS Stuff A Bus","CH+22720"                                    ],
  ["Edmonton AM Bid-a-Thon","CH+22988"                             ],
  ["Radio Active Bid-a-Thon","CH+22989"                            ],
  ["CBC Edmonton News at Six-Bid-a-Thon","CH+22990"                ],
  ["K97 CHRISTMAS RIG","CH+23274"                                  ],
  ["IKEA Bed-In-For Food","CH+23514"                               ],
  ["Working Poor Diet","CH+24087"                                  ],
  ["Loblaws","CH+24942"                                            ],
  ["Kraft Hunger Challenge (KHC)","CH+26265"                       ],
  ["Heritage Festival Food Drive","CH+27142"                       ],
  ["Round Up for Hunger","CH+29111"                                ],
  ["CBC Turkey Drive","CH+29678"                                   ],
  ["Judy's Dash and Splash","CH+30887"                             ],
  ["Working Poor Diet","CH+31599"                                  ],
  ["Food Forever 2 By 2 Campaign","CH+33228"                       ],
  ["KRAFT HUNGER CHALLENGE","CH+33712"                             ],
  ["Shaw Edmonton","CH+35663"                                      ],
  ["CBC Turkey Drive","CH+36901"                                   ],
  ["Yeg Tweet-up","CH+37267"                                       ],
  ["Polar Dunk - Judy's Splash 'n Dash","CH+37942"                 ],
  ["Polar Dunk - Dunk Dylan ","CH+37954"                           ],
  ["Polar Dunk -Paul Brown-The Bear Radio ","CH+38123"             ],
  ["Slave Lake Fire Assistance","CH+40841"                         ],
  ["CBC Turkey Drive","CH+44091"                                   ],
  ["Covenant Health Legacy Day","CH+44333"                         ],
  ["Stuff-A-Bus","CH+44533"                                        ],
  ["Can Man Dan","CH+45522"                                        ],
  ["Cruise for a Cause","CH+48054"                                 ],
  ["SHAW Fill the Food Bank","CH+48182"                            ],
  ["Fare Fight for Food","CH+48335"                                ],
  ["CORUS Feeds Kids","CH+48610"                                   ],
  ["Can Man Dan","CH+49968"                                        ],
  ["Heritage Festival Fundraiser","CH+50443"                       ],
  ["Fare Fight for Food","CH+50966"                                ],
  ["Karen's Online Food Bank Fundraiser ","CH+51048"               ],
  ["B. ETS Stuff-A-Bus","CH+52374"                                 ],
  ["CDI College West Throw Down","CH+52447"                        ],
  ["Reeves College Throw Down","CH+52448"                          ],
  ["Annual CBC Turkey Drive","CH+52827"                            ],
  ["Hunger Awareness Week","CH+54306"                              ],
  ["Corus Feeds Kids","CH+57961"                                   ],
  ["Give 30.ca","CH+58554"                                         ],
  ["Heritage Festival","CH+58555"                                  ],
  ["2. Help Feed a Family","CH+59991"                              ],
  ["Drive Out Hunger","CH+61389"                                   ],
  ["CBC Turkey Drive","CH+62779"                                   ],
  ["Campus Charity Fundraiser ","CH+62916"                         ],
  ["Sponsor a Festive Hamper","CH+62918"                           ],
  ["Sponsor a Community Meal","CH+62919"                           ],
  ["ETS Stuff A Bus","CH+63181"                                    ],
  ["CBC Turkey Drive","CH+63182"                                   ],
  ["Friends of Dane","CH+64213"                                    ],
  ["Pedal Power","CH+64230"                                        ],
  ["Hunger Awareness Week","CH+69061"                              ],
  ["Five Hole For Food","CH+69250"                                 ],
  ["C. Give 30","CH+69944"                                         ],
  ["B. Heritage Festival Food Drive","CH+72181"                    ],
  ["3. Endowment Fund","CH+72293"                                  ],
  ["1. General","CH+171594"                                        ],
  ["Ivory Noir Salon - We Heart Cereal","CH+181719"                ],
  ["Guru Nanak Dev's Birthday Celebration","CH+184010"             ],
  ["E. Can Man Dan","CH+184349"                                    ],
  ["Guru Nanak Dev's Birthday Celebration ","CH+184365"            ],
  ["C.  Jean Cooper Fund - The Magical Mrs. Claus","CH+184439"     ],
  ["CBC Turkey Drive","CH+185013"                                  ],
  ["ETS Stuff A Bus","CH+185127"                                   ],
  ["Pedal Power","CH+185280"                                       ],
  ["In Memory of William Orr","CH+186887"                          ],
  ["Every Plate Full","CH+190493"                                  ],
  ["Every Plate Full","CH+190494"                                  ],
  ["Strategic Group Let`s Eat! Campaign against Hunger","CH+193669"],
  ["Five Hole for Food","CH+194368"                                ],
  ["Tower Garden Fund","CH+194443"                                 ],
  ["H. Jean Cooper Fund - The Magical Mrs. Claus","CH+197032"      ],
  ["G.AMA Fill Our Fleet","CH+197317"                              ],
  ["G. #GoGarrett","CH+197421"                                     ],
  ["H. #GoGarrette","CH+197422"                                    ],
  ["E. Think Outside the Chocolate Box","CH+197429"                ],
  ["A. CBC Turkey Drive","CH+197895"                               ],
  ["D. ETS Stuff A Bus","CH+197896"                                ],
  ["G. AMA Fill a Fleet","CH+197897"                               ],
  ["D. Pedal Power","CH+198094"                                    ],
  ["D. CTV Holiday Helping","CH+198660"                            ],
  ["Homes By Avi","CH+198957"                                      ],
  ["D. In Memory of Cheryl Anne Nattrass","CH+199360"              ],
  ["C. Phobruary","CH+200362"                                      ],
  ["1. CBC Turkey Drive","CH+200748"                               ],
  ["CDI College Massage-A-Thon","CH+201888"                        ],
  ["EveryPlateFull.ca  ($1 = 3 meals)","CH+202412"                 ],
  ["1. Fort McMurray Fire Support","CH+202778"                     ],
  ["Evacuee Support","CH+202781"                                   ],
  ["4. Give 30","CH+202980"                                        ],
  ["Strategic Group?s Let?s Eat! campaign against hunger","CH+203444"   ],
  ["Purolator Tackle Hunger","CH+204049"                           ],
  ["Heritage Festival Donation","CH+204816"                        ],
  ["ETS Stuff A Bus","CH+206335"                                   ],
  ["AMA Fill A Fleet","CH+206617"                                  ],
  ["CBC Turkey Drive","CH+206624"                                  ],
  ["Think Outside the Chocolate Box","CH+207032"                   ],
  ["in honour of Guru Nanak Dev`s Birthday Celebration","CH+207102"],
  ["Urban Sky Cares","CH+207146"                                   ],
  ["Jean Cooper Fund - The Magical Mrs. Claus","CH+207147"         ],
  ["The Gingerbread House Campaign","CH+207204"                    ],
  ["Can Man Dan's Moving Out Hunger","CH+207377"                   ],
  ["Sherwood Park Toyota's Pedal Power","CH+207433"                ],
  ["Evacuee Support: New Edmontonians & Ongoing Help","CH+208531"  ],
  ["Phobruary","CH+209337"                                         ],
  ["z200 kits for 200 kids - Edmonton Chinese Lions Club","CH+211088"   ],
  ["Take A Bite Out of Hunger","CH+211200"                         ],
  ["Every Plate Full","CH+212102"                                  ],
  ["z150 Dozen Eggs for CANADA 150","CH+212156"                    ],
  ["AER Holiday Food Drive","CH+217467"                            ],
  ["Gift Card \"Re-Gift\" from Loblaws","CH+219725"                  ],
  ["5. Beyond Food Program","CH+224414"                            ],
  ["Torbiak Open House","CH+227223"                                ],
  ["Matching Funds Available - EPCOR Street Ski","CH+227569"       ],
  ["Safeway [Sobeys [IGA Joy of Giving Campaign","CH+228313"     ],
  ["Candy Cane Lane","CH+229480"                                   ],
  ["Dance Moves","CH+229775"                                       ],
  ["Christmas and Every Day","CH+229872"                           ],
  ["In Memory of Gene Zwozdesky","CH+230438"                       ],
  ["In Memory of K. Janet Hughes","CH+231212"                      ],
  ["Lander Antonio's Birthday Fundraiser for the Food Bank","CH+231646"],
  ["6. Alberta Farm to Food Bank","CH+233834"                      ],
  ["Spring Into Action Fund Drive","CH+234517"                     ],
  ["7. Edmonton Marathon Fund","CH+237086"                         ],
  ["8. Hibco's Anniversary Fundraiser","CH+238502"                 ],
  ["Baby Bundle","CH+239141"                                       ],
  ["Feed a Family of 3","CH+239143"                                ],
  ["Feed a Family of 4","CH+239144"                                ],
  ["Feed a Family of 5-7","CH+239146"                              ],
  ["Turkey","CH+239147"                                            ],
  ["Fund Name","CH+2"                                              ],
  ];

  foreach ($chOptions[0] as $chOption) {
    civicrm_api3('OptionValue', 'create', [
      'label' => $chOption[0],
      'value' => $chOption[1],
      'option_group_id' => 'ch_fund',
      'is_active' => 1,
    ]);
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
