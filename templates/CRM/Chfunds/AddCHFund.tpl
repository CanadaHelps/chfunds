  <div id="crm-admin-options-form-block-fund" style="margin: 10px 0% 0px -55px;">
    <span class="label">
      {$form.financial_type_id.label}
    </span>
    <span class="html-adjust">
      {$form.financial_type_id.html}
    </span>
  </div>

  <div id="crm-admin-options-form-block-is_enabled_in_ch" style="margin: 10px 0% 0px -150px;">
    <span class="label">
      {$form.is_enabled_in_ch.label}
    </span>
    <span class="html-adjust">
      {$form.is_enabled_in_ch.html}
    </span>
  </div>


{literal}
<script type="text/javascript">
CRM.$(function($) {
  $('#crm-admin-options-form-block-fund').insertAfter('#value');
  $('#crm-admin-options-form-block-is_enabled_in_ch').insertAfter('#weight');
});
</script>
{/literal}
