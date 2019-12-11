{* HEADER *}
<div class="crm-block crm-form-block crm-component-form-block">
<br/>
<table class="form-layout-compressed">
  <tbody>
    <tr>
      <td>
      <table>
        <tbody>
          <tr>
            <td class="label"><label>Assign CH Funds</label></td>
            <td class="content">
              {$form.ch_funds_check_all.html}
              <ul class="crm-checkbox-list">
              {foreach from=$form.ch_funds item="ch_funds_val"}
                <li class="{cycle values="even-row,odd-row"}">
                  {$ch_funds_val.html}
                </li>
                {/foreach}
              </ul>
            </td>
          </tr>
        </tbody>
      </table>
    </td>
    <td>
      <table>
        <tbody>
          <tr>
            <td class="label"><label>Fund</label></td>
            <td class="content">
               {$form.financial_type_id.html}
            </td>
          </tr>
        </tbody>
      </table>
    </td>
    </tr>
  </tbody>
</table>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
</div>

{literal}
<script type="text/javascript">
CRM.$(function($) {
  $("#ch_funds_check_all").on('click', function() {
    $("input[name^='ch_funds\[']").prop('checked', $(this).prop("checked"));
    if ($(this).prop("checked")) {
      $("label[for='ch_funds_check_all']").text(ts('Uncheck all'));
    }
    else {
      $("label[for='ch_funds_check_all']").text(ts('Check all'));
    }
  });
});
</script>
{/literal}
