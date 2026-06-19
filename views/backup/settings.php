<ul class="nav nav-tabs mt-1" role="tablist">
	<li role="presentation" data-name="areminderov" class="active">
		<a href="#areminderov" aria-controls="areminderov" role="tab" data-toggle="tab">
			<?php echo _("Public key of this system")?>
		</a>
	</li>
	<li role="presentation" data-name="aremindgset" class="change-tab">
		<a href="#aremindgset" aria-controls="aremindgset" role="tab" data-toggle="tab">
			<?php echo _("Public key of other system")?>
		</a>
	</li>
</ul>
<div class="tab-content display">
	<div role="tabpanel" id="areminderov" class="tab-pane active"><br/>
			<div class="element-container">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="publickey"><?php echo _("Public Key") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="publickey"></i>
						</div>
						<div class="col-md-9">
							<textarea disabled id="publickey" class="form-control" rows='8'><?php echo $publickey?></textarea>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<span id="publickey-help" class="help-block fpbx-help-block"><?php echo _("Public SSH key to allow other servers to connect. Only ECDSA SSH key is supported")?></span>
					</div>
				</div>
			</div>
	</div>
	<div role="tabpanel" id="aremindgset" class="tab-pane">
			<button type="button" id="addFieldsButton" class="btn btn-primary" data-toggle="modal" data-target="#addPublicKeyModal">
				<i class="fa fa-plus"></i> <?php echo _("Add Public Key") ?>
			</button><br /><br />
			<table class="table" id="serverTable">
				<thead>
					<tr>
						<th><?php echo _("Server Name") ?></th>
						<th><?php echo _("Public Key of Asterisk User") ?></th>
						<th><?php echo _("SSH Restrictions") ?></th>
						<th><?php echo _("Actions") ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if (isset($publickeyAsteriskUser) && is_array($publickeyAsteriskUser) && count($publickeyAsteriskUser) > 0) {
						foreach ($publickeyAsteriskUser as $v) {
							$servername = htmlspecialchars($v['servername'] ?? '', ENT_QUOTES, 'UTF-8');
							$authorizedLine = $v['publickeyAsteriskUser'] ?? '';
							$displayKey = htmlspecialchars($v['publickey'] ?? $authorizedLine, ENT_QUOTES, 'UTF-8');
							$restrictions = htmlspecialchars($v['restrictionsSummary'] ?? _('None'), ENT_QUOTES, 'UTF-8');
							$authorizedAttr = htmlspecialchars($authorizedLine, ENT_QUOTES, 'UTF-8');
							echo '<tr data-authorized-line="' . $authorizedAttr . '">'
								. '<td><input name="servername[]" class="form-control" value="' . $servername . '" readonly /></td>'
								. '<td><textarea name="publickeyAsteriskUser[]" class="form-control" rows="4" readonly>' . $displayKey . '</textarea></td>'
								. '<td class="pk-restrictions-cell"><span class="text-muted">' . $restrictions . '</span></td>'
								. '<td><button type="button" class="btn btn-danger deleteRow">' . _('Delete') . '</button></td>'
								. '</tr>';
						}
					}
					?>
				</tbody>
			</table>
	</div>
</div>

<div id="addPublicKeyModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="addPublicKeyModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="<?php echo _('Close') ?>">
					<span aria-hidden="true">&times;</span>
				</button>
				<h4 class="modal-title" id="addPublicKeyModalLabel"><?php echo _("Add Public Key") ?></h4>
			</div>
			<div class="modal-body">
				<form id="addPublicKeyForm" class="form-horizontal" onsubmit="return false;">
				<div class="element-container">
					<div class="row">
						<div class="form-group">
							<div class="col-sm-3">
								<label class="control-label" for="pkServerName"><?php echo _("Server Name") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="pkServerName"></i>
							</div>
							<div class="col-sm-9">
								<input type="text" id="pkServerName" class="form-control" placeholder="<?php echo _('e.g. 10.25.3.146') ?>" />
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-12">
							<span id="pkServerName-help" class="help-block fpbx-help-block"><?php echo _("Hostname or IP of the remote backup server") ?></span>
						</div>
					</div>
					<div class="row">
						<div class="form-group">
							<div class="col-sm-3">
								<label class="control-label" for="pkPublicKey"><?php echo _("Public Key") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="pkPublicKey"></i>
							</div>
							<div class="col-sm-9">
								<textarea id="pkPublicKey" class="form-control" rows="5" placeholder="ssh-rsa AAAA..."></textarea>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-12">
							<span id="pkPublicKey-help" class="help-block fpbx-help-block"><?php echo _("Paste the remote system's public key (ssh-rsa, ssh-ed25519, or ecdsa)") ?></span>
						</div>
					</div>

					<hr class="pk-modal-section-divider" />
					<h5 class="col-sm-offset-3 pk-modal-section-title"><?php echo _("SSH Key Restrictions") ?></h5>
					<p class="col-sm-offset-3 text-muted pk-section-intro"><?php echo !empty($sshCommandRestrictionEnabled)
						? _("restrict and a forced command wrapper are applied automatically. Set From to limit which hosts may connect.")
						: _("restrict is applied automatically. Set From to limit which hosts may connect.") ?></p>

					<div class="row">
						<div class="form-group">
							<div class="col-sm-3">
								<label class="control-label" for="pkFrom"><?php echo _("From") ?> <span class="text-danger">*</span></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="pkFrom"></i>
							</div>
							<div class="col-sm-9">
								<input type="text" id="pkFrom" class="form-control" placeholder="<?php echo _('Restrict source IP/host') ?>" required="required" />
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-12">
							<span id="pkFrom-help" class="help-block fpbx-help-block"><?php echo _("Required. Limits which client address may use this key (IP, hostname, or comma-separated list, e.g. 10.0.0.5 or 10.0.0.5,backup.example.com). For backup servers, set this to the remote system's address; it defaults from Server Name when that field changes. Connections from other IPs are rejected even with a valid key.") ?></span>
						</div>
					</div>

					<hr class="pk-modal-section-divider" />
					<div class="row pk-output-preview-section">
						<div class="form-group">
							<div class="col-sm-3">
								<label class="control-label" for="pkAuthorizedPreview"><?php echo _("Output preview") ?></label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="pkAuthorizedPreview"></i>
							</div>
							<div class="col-sm-9">
								<pre id="pkAuthorizedPreview" class="pk-authorized-preview form-control-static"><?php echo _("Enter a public key to preview the authorized_keys line") ?></pre>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-12">
						<span id="pkAuthorizedPreview-help" class="help-block fpbx-help-block"><?php echo !empty($sshCommandRestrictionEnabled)
							? _("Shows the exact single line that will be appended to /home/asterisk/.ssh/authorized_keys as restrict,command=\"/usr/local/bin/freepbx-ssh-restrict.sh\",from=\"...\" followed by the public key.")
							: _("Shows the exact single line that will be appended to /home/asterisk/.ssh/authorized_keys as restrict,from=\"...\" followed by the public key.") ?></span>
						</div>
					</div>
				</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" id="pkModalCancel" data-dismiss="modal"><?php echo _("Cancel") ?></button>
				<button type="button" class="btn btn-primary" id="pkModalSave"><?php echo _("Save") ?></button>
			</div>
		</div>
	</div>
</div>

<style>
#addPublicKeyModal .pk-modal-section-divider {
	margin-top: 20px;
	margin-bottom: 0;
}
#addPublicKeyModal .pk-modal-section-title {
	margin-top: 16px;
	margin-bottom: 8px;
}
#addPublicKeyModal .pk-output-preview-section {
	margin-top: 16px;
}
#addPublicKeyModal .pk-section-intro {
	margin-bottom: 6px;
}
/* Match FreePBX element-container row spacing (e.g. Missed Call tab) */
#addPublicKeyModal .element-container > .row {
	margin-bottom: 1px;
}
#addPublicKeyModal .element-container .form-group {
	margin-bottom: 0;
	padding-top: 5px;
	padding-bottom: 5px;
}
#addPublicKeyModal .element-container .row:nth-child(odd) {
	background-color: #f5f5f5;
	border-radius: 3px;
}
#addPublicKeyModal .element-container .form-group .col-sm-3 {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	flex-wrap: nowrap;
	gap: 5px;
	padding-top: 7px;
}
#addPublicKeyModal .element-container .form-group .col-sm-3 .control-label {
	float: none;
	width: auto;
	margin-bottom: 0;
	padding-top: 0;
	line-height: 1.42857143;
}
#addPublicKeyModal .element-container i.fpbx-help-icon {
	color: #0f5a59;
	cursor: pointer;
	font-size: 15px;
	line-height: 1;
	flex-shrink: 0;
	margin: 0;
	padding: 0;
	height: auto;
	width: auto;
}
#addPublicKeyModal .element-container .fpbx-help-block {
	display: none;
	color: #666;
	margin-top: 0;
	margin-bottom: 0;
	padding: 4px 15px 2px;
}
#addPublicKeyModal .element-container .fpbx-help-block.active {
	display: block;
}
.pk-authorized-preview {
	min-height: 4em;
	max-height: 12em;
	overflow: auto;
	white-space: pre-wrap;
	word-break: break-all;
	background: #f5f5f5;
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 10px;
	margin: 0;
	font-size: 12px;
}
.pk-restrictions-cell {
	max-width: 220px;
	word-break: break-word;
}
</style>
<script>
(function($) {
	window.PK_SSH_COMMAND_RESTRICTION_ENABLED = <?php echo !empty($sshCommandRestrictionEnabled) ? 'true' : 'false'; ?>;
	var PK_SSH_RESTRICT_SCRIPT = '/usr/local/bin/freepbx-ssh-restrict.sh';

	function pkGetSshFixedOptions() {
		return window.PK_SSH_COMMAND_RESTRICTION_ENABLED ? ['restrict'] : [];
	}

	function pkBuildAuthorizedKeysLine(publicKey, fromValue) {
		var key = (publicKey || '').trim();
		var from = (fromValue || '').trim();
		if (!key || !from) {
			return '';
		}
		var parts = pkGetSshFixedOptions().slice();
		if (window.PK_SSH_COMMAND_RESTRICTION_ENABLED) {
			parts.push('command="' + pkEscapeSshOptionValue(PK_SSH_RESTRICT_SCRIPT) + '"');
		}
		parts.push('from="' + pkEscapeSshOptionValue(from) + '"');
		return parts.join(',') + ' ' + key;
	}

	function pkEscapeSshOptionValue(value) {
		return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
	}

	function pkUpdateAuthorizedPreview() {
		var publicKey = $('#pkPublicKey').val().trim();
		var from = $('#pkFrom').val().trim();
		if (!publicKey) {
			$('#pkAuthorizedPreview').text(_('Enter a public key to preview the authorized_keys line'));
			return;
		}
		if (!from) {
			$('#pkAuthorizedPreview').text(_('Enter From to preview the authorized_keys line'));
			return;
		}
		$('#pkAuthorizedPreview').text(pkBuildAuthorizedKeysLine(publicKey, from));
	}

	function pkUpdateAuthorizedPreviewDeferred() {
		setTimeout(pkUpdateAuthorizedPreview, 0);
	}

	$(function() {
		window.buildAuthorizedKeysLine = function(publicKey, sshOptions) {
			var from = (sshOptions && sshOptions.from) ? sshOptions.from : $('#pkFrom').val();
			return pkBuildAuthorizedKeysLine(publicKey, from);
		};
		window.updatePkAuthorizedPreview = pkUpdateAuthorizedPreview;

		$('#pkPublicKey, #pkFrom, #pkServerName').on('input.pkAuthPreview change.pkAuthPreview', pkUpdateAuthorizedPreviewDeferred);
		$('#addPublicKeyModal').on('shown.bs.modal.pkAuthPreview', pkUpdateAuthorizedPreviewDeferred);
	});
})(jQuery);
</script>
