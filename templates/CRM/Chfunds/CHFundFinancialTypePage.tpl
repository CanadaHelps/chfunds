{literal}
<script type="text/javascript">
CRM.$(function($) {
  $('table th:nth-child(3)').after('<th>CH Funds</th>');

  var chFundLinks = $.parseJSON('{/literal}{$chFundLinks}{literal}');
  var chFunds = $.parseJSON('{/literal}{$chFunds}{literal}');
  $('table tr').each(function(e) {
    if (e > 0) {
      if (chFunds[e] === null) {
        $('td:nth-child(3)', this).after('<td>&nbsp;&nbsp;</td>');
      }
      else {
        $('td:nth-child(3)', this).after('<td>' + chFunds[e] + '</td>');
        if (chFundLinks[e]) {
          $('td:nth-child(8)', this).prepend('<a class="action-item crm-hover-button" title="' + ts('CH Funds') + '" href="' + chFundLinks[e] + '">Assign CH Funds</a>&nbsp;&nbsp;');
        }
      }
    }
  });

});
</script>
{/literal}
