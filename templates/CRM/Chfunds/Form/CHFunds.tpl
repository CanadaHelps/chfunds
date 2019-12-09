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
