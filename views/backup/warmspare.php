<div class="section-title" data-for="backup-warmspare">
  <h3>
    <i class="fa fa-minus"></i>
    <?php echo _("Warm Spare") ?>
  </h3>
</div>
<?php
//setting default API, untill SSH  is implimented
$warmsparewayofrestore = $warmsparewayofrestore ? $warmsparewayofrestore : 'API';
$button = '<button id="oauthbutton" class = "btn btn-default">'._("Get Warm Spare Token").'</a>';
?>
<div class="section" data-id="backup-warmspare">
  <!--Enable-->
  <div class="element-container">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspareenabled">
            <?php echo _("Enable") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspareenabled"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspareenabled" id="warmspareenabledyes" value="yes" <?php echo ($warmspareenabled=="yes" ?"CHECKED": "") ?>>
          <label for="warmspareenabledyes">
            <?php echo _("Yes");?>
          </label>
          <input type="radio" name="warmspareenabled" id="warmspareenabledno" <?php echo ($warmspareenabled=="yes" ? "": "CHECKED")?>>
          <label for="warmspareenabledno">
            <?php echo _("No");?>
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspareenabled-help" class="help-block fpbx-help-block">
          <?php echo _("Should the warm spare feature be enabled")?>
        </span>
      </div>
    </div>
  </div>
  <!--END Enable-->
  <!--Enable Remote Trunks-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remotetrunks">
            <?php echo _("Disable Remote Trunks") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remotetrunks"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio"  disabled>
          <label for="warmspare_remotetrunksyes">
            <?php echo _("Yes"); ?>
          </label>
          <input type="radio" disabled>
          <label for="warmspare_remotetrunksno">
            <?php echo _("No"); ?>
          </label> <?php echo _("This option has moved to Backup Items -> Core (Disable Trunks on Restore?) "); ?>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remotetrunks-help" class="help-block fpbx-help-block">
          <?php echo _("Should the remote trunks be Disabled") ?>
        </span>
      </div>
    </div>
  </div>
  <!--END Enable Remote Trunks-->
   <!--Exclude CERT settings-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_cert">
            <?php echo _("Exclude CERTIFICATE settings") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_cert"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspare_cert" id="warmspare_certyes" value="yes" <?php echo ($warmspare_cert == "yes" ? "CHECKED" : "") ?>>
          <label for="warmspare_certyes">
            <?php echo _("Yes"); ?>
          </label>
          <input type="radio" name="warmspare_cert" id="warmspare_certno" <?php echo ($warmspare_cert == "yes" ? "" : "CHECKED")?>>
          <label for="warmspare_certno">
            <?php echo _("No"); ?>
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_cert-help" class="help-block fpbx-help-block">
          <?php echo _("If this option is set to yes then certificate will not be restored which require HTTPS config to be rebuild manually with spare server certificate.") ?>
        </span>
      </div>
    </div>
  </div>
  <!--Exclude CERT settings-->
   
    <!--Exclude NAT settings-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remotenat">
            <?php echo _("Exclude NAT settings") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remotenat"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspare_remotenat" id="warmspare_remotenatyes" value="yes" <?php echo ($warmspare_remotenat == "yes" ? "CHECKED" : "") ?>>
          <label for="warmspare_remotenatyes">
            <?php echo _("Yes"); ?>
          </label>
          <input type="radio" name="warmspare_remotenat" id="warmspare_remotenatno" <?php echo ($warmspare_remotenat == "yes" ? "" : "CHECKED")?>>
          <label for="warmspare_remotenatno">
            <?php echo _("No"); ?>
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remotenat-help" class="help-block fpbx-help-block">
          <?php echo _("Explicitly exclude any machine-specific IP settings. This allows you to have a warm-spare machine with a different IP address.") ?>
        </span>
      </div>
    </div>
  </div>
  <!--Exclude NAT settings-->
    <!--Exclude Bind Address-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remotebind">
            <?php echo _("Exclude Bind Address") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remotebind"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspare_remotebind" id="warmspare_remotebindyes" value="yes" <?php echo ($warmspare_remotebind == "yes" ?
                                                                                                    "CHECKED" : "") ?>>
          <label for="warmspare_remotebindyes">
            <?php echo _("Yes"); ?>
          </label>
          <input type="radio" name="warmspare_remotebind" id="warmspare_remotebindno" <?php echo ($warmspare_remotebind == "yes" ? "" : "CHECKED")
                                                                                      ?>>
          <label for="warmspare_remotebindno">
            <?php echo _("No"); ?>
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remotebind-help" class="help-block fpbx-help-block">
          <?php echo _("Explicitly exclude any machine-specific Bindaddres. This allows you to have a warm-spare machine with a different IP address.") ?>
        </span>
      </div>
    </div>
  </div>
  <!--Exclude Bind Address-->
    <!--Exclude DNS-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remotedns">
            <?php echo _("Exclude DNS") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remotedns"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspare_remotedns" id="warmspare_remotednsyes" value="yes" <?php echo ($warmspare_remotedns == "yes" ? "CHECKED" : "") ?>>
          <label for="warmspare_remotednsyes">
            <?php echo _("Yes"); ?>
          </label>
          <input type="radio" name="warmspare_remotedns" id="warmspare_remotednsno" <?php echo ($warmspare_remotedns == "yes" ? "" : "CHECKED")?>>
          <label for="warmspare_remotednsno">
            <?php echo _("No"); ?>
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remotedns-help" class="help-block fpbx-help-block">
          <?php echo _("Explicitly exclude any machine-specific DNS. This allows you to have a warm-spare machine with a different DNS.") ?>
        </span>
      </div>
    </div>
  </div>
  <!--Exclude DNS-->
    <!--Exclude DNS-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remoteapply">
            <?php echo _("Apply Configs") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remoteapply"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspare_remoteapply" id="warmspare_remoteapplyyes" value="yes" <?php echo ($warmspare_remoteapply == "yes" ?"CHECKED" : "") ?>>
          <label for="warmspare_remoteapplyyes">
            <?php echo _("Yes"); ?>
          </label>
          <input type="radio" name="warmspare_remoteapply" id="warmspare_remoteapplyno" <?php echo ($warmspare_remoteapply == "yes" ? "" : "CHECKED")?>>
          <label for="warmspare_remoteapplyno">
            <?php echo _("No"); ?>
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remoteapply-help" class="help-block fpbx-help-block">
          <?php echo _("Equivalence of clicking the red button, will happen automatically after a restore on a Standby system") ?>
        </span>
      </div>
    </div>
  </div>
  <!--Apply Configs-->
  <!--Exclude Trunks-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
	  <label class="control-label" for="warmspare_excludetrunks">
	    <?php echo _("Exclude Trunks") ?>
	  </label>
	  <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_excludetrunks"></i>
	</div>
	<div class="col-md-9 radioset">
	  <input type="radio" name="warmspare_excludetrunks" id="warmspare_excludetrunksyes" value="yes" <?php echo ($warmspare_excludetrunks == "yes" ? "CHECKED" : "") ?>>
	  <label for="warmspare_excludetrunksyes">
	    <?php echo _("Yes"); ?>
	  </label>
	  <input type="radio" name="warmspare_excludetrunks" id="warmspare_excludetrunksno" <?php echo ($warmspare_excludetrunks == "yes" ? "" : "CHECKED")?>>
	  <label for="warmspare_excludetrunksno">
	    <?php echo _("No"); ?>
	  </label>
	</div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_excludetrunks-help" class="help-block fpbx-help-block">
	  <?php echo _("Should the trunks be Excluded") ?>
	</span>
      </div>
    </div>
  </div>
  <!--END Exclude Trunks-->
  <!-- there are two ways to do this 1.Legacy way using ssh And 2. Using Oauth2 API  -->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmsparewayofrestore">
            <?php  echo _("Connect Warm Spare Server Over") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmsparewayofrestore"></i>
        </div>
        <div class="col-md-9 radioset">
          <input  type="radio" name="warmsparewayofrestore" id="warmsparewayofrestoreapi" value="API" <?php echo ($warmsparewayofrestore=="API" ?"CHECKED": "") ?>>
          <label for="warmsparewayofrestoreapi">
            <?php echo _("API");?>
          </label>
          <input  type="radio" name="warmsparewayofrestore" id="warmsparewayofrestoressh" value="SSH" <?php echo ($warmsparewayofrestore=="SSH" ? "CHECKED": "") ?>  >
          <label for="warmsparewayofrestoressh">
            <?php echo _("SSH");?>
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmsparewayofrestore-help" class="help-block fpbx-help-block">
          <?php echo _("Way to Connect Warm spare Server")?>
        </span>
      </div>
    </div>
  </div>
  <!--END Enable-->
  <div class="element-container warmspare warmspareapi">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remoteapi_filestoreid">
            <?php echo _("Warm Spare Server") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remoteapi_filestoreid"></i>
        </div>
        <div class="col-md-9">
		<select id="warmspare_remoteapi_filestoreid" name="warmspare_remoteapi_filestoreid" class="form-control">
			<option value="" ><?php echo _("Select Warm Spare server")?> </option>
			<?php foreach($filestores as $servers) {?>
			<option value="<?php echo $servers['value']?>" <?php echo $servers['selected'] ? 'selected' : '' ?>><?php echo $servers['label']?> </option>
			<?php } ?>
			</select>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remoteapi_filestoreid-help" class="help-block fpbx-help-block">
          <?php echo _("Warm Spare server needs to add to filestore, And it should be a FreePBX 15 and Above")?>
        </span>
      </div>
    </div>
  </div>
   <!--Remote API key -->
  <div class="element-container warmspare warmspareapi">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remoteapi_accesstokenurl">
            <?php echo _("Access Token URL") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remoteapi_accesstokenurl"></i>
        </div>
        <div class="col-md-9">
          <input type="text" class="form-control" id="warmspare_remoteapi_accesstokenurl" name="warmspare_remoteapi_accesstokenurl" value="<?php echo isset($warmspare_remoteapi_accesstokenurl)?$warmspare_remoteapi_accesstokenurl:''?>">
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remoteapi_accesstokenurl-help" class="help-block fpbx-help-block">
          <?php echo _("Access Token URL of Warm Spare server.")?>
        </span>
      </div>
    </div>
  </div>
  <!--END Remote API-->
   <!--Remote client id -->
  <div class="element-container warmspare warmspareapi">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remoteapi_clientid">
            <?php echo _("Client ID") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remoteapi_clientid"></i>
        </div>
        <div class="col-md-9">
          <input type="text" class="form-control" id="warmspare_remoteapi_clientid" name="warmspare_remoteapi_clientid" value="<?php echo isset($warmspare_remoteapi_clientid)?$warmspare_remoteapi_clientid:''?>">
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remoteapi_clientid-help" class="help-block fpbx-help-block">
          <?php echo _("ClientID of the Warm Spare server.")?>
        </span>
      </div>
    </div>
  </div>
  <!--END ClientID-->
   <!--Remote API Secret -->
  <div class="element-container warmspare warmspareapi">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remoteapi_secret">
            <?php echo _("Client Secret") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remoteapi_secret"></i>
        </div>
        <div class="col-md-9">
          <input type="text" class="form-control" id="warmspare_remoteapi_secret" name="warmspare_remoteapi_secret" value="<?php echo isset($warmspare_remoteapi_secret)?$warmspare_remoteapi_secret:''?>">
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remoteapi_secret-help" class="help-block fpbx-help-block">
          <?php echo _("Client Secret of the Warm Spare server.")?>
        </span>
      </div>
    </div>
  </div>
  <!--END Secret-->
  <!--Remote GraphQL URL -->
  <div class="element-container warmspare warmspareapi">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remoteapi_gql">
            <?php echo _("GraphQL URL") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remoteapi_gql"></i>
        </div>
        <div class="col-md-9">
          <input type="text" class="form-control" id="warmspare_remoteapi_gql" name="warmspare_remoteapi_gql" value="<?php echo isset($warmspare_remoteapi_gql)?$warmspare_remoteapi_gql:''?>">
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remoteapi_gql-help" class="help-block fpbx-help-block">
          <?php echo _("GraphQL URL of the Warm Spare server.")?>
        </span>
      </div>
    </div>
  </div>
  <!--END GraphQL URL-->
  <!--Remote Refresh Token -->
  <div class="element-container warmspare warmspareapi">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remoteapi_refreshtoken">
            <?php echo _("Client Access Token") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remoteapi_accesstoken"></i>
		  <?php echo $button; ?>
        </div>
        <div class="col-md-9">
          <input type="text" class="form-control" id="warmspare_remoteapi_accesstoken" name="warmspare_remoteapi_accesstoken" value="<?php echo isset($warmspare_remoteapi_accesstoken)?$warmspare_remoteapi_accesstoken:''?>" readonly>
		  <input type="hidden" class="form-control" id="warmspare_remoteapi_accesstoken_expire" name="warmspare_remoteapi_accesstoken_expire" value="<?php echo isset($warmspare_remoteapi_accesstoken_expire)?$warmspare_remoteapi_accesstoken_expire:''?>" >
		</div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remoteapi_accesstoken-help" class="help-block fpbx-help-block">
          <?php echo _("Get OAUTH2 Token for the Warm Spare server API.")?>
        </span>
      </div>
    </div>
  </div>
  <!--END Refresh token -->
  <!--Remote IP-->
  <!-- hiding SSH-->
  <div class="element-container warmspare warmsparessh">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remotessh_filestoreid">
            <?php echo _("Warm Spare Server") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remotessh_filestoreid"></i>
        </div>
        <div class="col-md-9">
		<select id="warmspare_remotessh_filestoreid" name="warmspare_remotessh_filestoreid" class="form-control">
			<option value="" ><?php echo _("Select Warm Spare server")?> </option>
			<?php foreach($filestoressh as $servers) {?>
			<option value="<?php echo $servers['value']?>" <?php echo $servers['selected'] ? 'selected' : '' ?>><?php echo $servers['label']?> </option>
			<?php } ?>
			</select>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remotessh_filestoreid-help" class="help-block fpbx-help-block">
          <?php echo _("Warm Spare server needs to add to filestore, And it should be a FreePBX 15 and Above")?>
        </span>
      </div>
    </div>
  </div>
  <!--END Remote IP-->
</div>
