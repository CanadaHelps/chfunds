<?php

use CRM_Chfunds_Utils as E;

class CRM_ContributionPage_CreateAPIWrapper implements API_Wrapper {
  /**
   * the wrapper contains a method that allows you to alter the parameters of the api request (including the action and the entity)
   */
  public function fromApiInput($apiRequest) {
    if (!empty($apiRequest['params']['id'])) {
      E::updateContributionCampaign($apiRequest['params']['campaign_id'] ?? 0, $apiRequest['params']['id']);
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
