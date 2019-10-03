{literal}
<script type="text/javascript">
CRM.$(function($) {
  var funds = $.parseJSON('{/literal}{$funds}{literal}');
  $('#options th:nth-child(2)').after('<th>Fund</th>');
  $('#options tr').each(function(e) {
    if (e > 0) {
      $('td:nth-child(2)', this).after('<td>' + funds[e] + '</td>');
    }
  });
  $('a.new-option').hide();
});
</script>
{/literal}
