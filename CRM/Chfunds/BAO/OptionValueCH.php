<?php
use CRM_Chfunds_ExtensionUtil as E;

class CRM_Chfunds_BAO_OptionValueCH extends CRM_Chfunds_DAO_OptionValueCH {

  /**
   * Create a new OptionValueCH based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Chfunds_DAO_OptionValueCH|NULL
   *
   */
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
  }

  public static function retrieve(&$params, &$defaults = [], &$ids = []) {
    $optionValueCH = CRM_Chfunds_BAO_OptionValueCH::getValues($params, $defaults, $ids);
    return $optionValueCH;
  }

  /**
   * Fetch the object and store the values in the values array.
   *
   * @param array $params
   *   Input parameters to find object.
   * @param array $values
   *   Output values of the object.
   * @param array $ids
   *   The array that holds all the db ids.
   *
   * @return CRM_Chfunds_BAO_OptionValueCH|null
   *   The found object or null
   */
  public static function getValues($params, &$values = [], &$ids = []) {
    if (empty($params)) {
      return NULL;
    }
    $optionValueCH = new CRM_Chfunds_BAO_OptionValueCH();

    $optionValueCH->copyValues($params);

    if ($optionValueCH->find(TRUE)) {
      $ids['optionvaluech'] = $optionValueCH->id;

      CRM_Core_DAO::storeValues($optionValueCH, $values);

      return $optionValueCH;
    }
    // return by reference
    $null = NULL;
    return $null;
  }

}
