{* HEADER *}
<div class="crm-block crm-form-block crm-component-form-block">
<br/>
<table class="form-layout-compressed">
  <tbody>
    <tr>
      <td class="label"><label>CH Funds</label></td>
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

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
</div>
