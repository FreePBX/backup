<?php
$html = '';
$html .= form_open($_SERVER['REQUEST_URI'], array('id' => 'files_browes_frm'));
$html .= form_hidden('action', 'restore');

//files
$html .= '<h3>' . _('Select items to restore') . '<hr /></h3>';
$html .= '<div id="restore_items">';
$html .= '<script type="text/javascript">var FILE_LIST=';
$html .= json_encode(backup_jstree_json_backup_files($manifest['file_list']));
$html .= '</script>';
$html .= '<div id="backup_files_container"><div id="backup_files">';
$html .= _("Please wait, loading...");
$html .= '</div></div>';

$mysql_count = count($manifest['mysql']);

//databases
if ($manifest['fpbx_db'] || $manifest['astdb']) {
	$mysql_count -= 1;
	$html .= br();
	$html .= fpbx_label(_('PBX Settings'), _('Restore all setting stored in the database'));
	$html .= ' ' . form_checkbox('restore[settings]', 'true');
}

//cdr's
if ($manifest['fpbx_cdrdb']) {
	$mysql_count -= 1;
	$html .= br();
	$html .= fpbx_label(_('CDR\'s'), _('Restore CDR records stored in this backup'));
	$html .= ' ' . form_checkbox('restore[cdr]', 'true');
}

//other mysql dumps
if ($mysql_count > 0) {
	$html .= br();
	$html .= fpbx_label(_('Other MySQL databases'), _('Restore MySQL databases stored in the backup'));
	$html .= ' ' . form_checkbox('restore[external_dbs]', 'true');
}

$html .= '</div>';

$html .= br();
$html .= form_submit(array(
	'name'  => 'submit',
	'value' => _('Restore'),
	'id'    => 'run_restore'
));

$html .= form_close();
$html .= br(15);
$html .= '<script type="text/javascript" src="modules/backup/assets/js/views/restore.js"></script>';
$html .= '<script type="text/javascript" src="modules/backup/assets/js/views/jquery.jstree.min.js"></script>';
echo $html;
