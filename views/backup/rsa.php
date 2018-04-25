<h1><i class="fa fa-refresh fa-spin"></i>&nbsp;<?php echo _("Generating Key Pair please standby")?></h1>
<script>
$( document ).ready(function() {
    $.get( ajaxurl, {module: 'backup', command:"generateRSA"})
    .done(function(data){
        window.history.back();
    });
});
</script>
