<?php
use CRM_Chfunds_ExtensionUtil as E;

class CRM_Chfunds_BAO_OptionValueCH extends CRM_Chfunds_DAO_OptionValueCH {

  /**
   * Create a new OptionValueCH based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Chfunds_DAO_OptionValueCH|NULL
   *
  public static function create($params) {
    $className = 'CRM_Chfunds_DAO_OptionValueCH';
    $entityName = 'OptionValueCH';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  } */

}
