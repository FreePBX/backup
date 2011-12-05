<?php 
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

$get_vars = array(
				'action'			=> '',
				'display'			=> '',
				'id'				=> '',
				'path'				=> '',
				'restore_path'		=> '',
				'restore_source'	=> '',
				'restore'			=> '',
				'submit'			=> '',
				'upload'			=> ''
				);

foreach ($get_vars as $k => $v) {
	$var[$k] = isset($_REQUEST[$k]) ? $_REQUEST[$k] : $v;
}

//set action to delete if delete was pressed instead of submit
if ($var['submit'] == _('Download') && $var['action'] == 'backup_list') {
	$var['action'] = 'download';
}

//set action to view if only id is set
if ($var['action'] == '' && $var['id']) {
	$var['action'] = 'browseserver';
}

//action actions
switch ($var['action']) {
	case 'download':
		$var['restore_path'] = backup_restore_locate_file($var['id'], $var['restore_path']);
		$_SESSION['backup_restore_path'] = $var['restore_path'];
		download_file($var['restore_path']);
		break;
	case 'upload':
		//only accept .tar.gz or .tgz
		
		if (is_uploaded_file($_FILES['upload']['tmp_name']) 
			&& (
				substr($_FILES['upload']['name'], -7) == '.tar.gz' 
				|| substr($_FILES['upload']['name'], -4) == '.tgz'
			)
			&& (
				$_FILES['upload']['type'] == 'application/x-gzip'
				|| $_FILES['upload']['type'] == 'application/octet-stream'
			)
		) {
			$dest = $amp_conf['ASTSPOOLDIR'] 
					. '/tmp/' 
					. 'backuptmp-suser-'
					. time() . '-'
					. basename($_FILES['upload']['name']);
			move_uploaded_file($_FILES['upload']['tmp_name'], $dest);
			
			//$var['restore_path'] = $dest;
			$_SESSION['backup_restore_path'] = $dest;
			
		} else {
			echo _('Error uploading file!');
			$var['action'] = '';
		}
		break;
	case 'list_dir':
		echo json_encode(backup_jstree_list_dir($var['id'], $var['path']));
		exit;
		break;
	case 'backup_list':
		//prepare file + ensure that its local
		if(!isset($_SESSION['backup_restore_path'])) {
			$var['restore_path'] = backup_restore_locate_file($var['id'], $var['restore_path']);
			
			/*
			 * being that this is an absolute file path 
			 * and being that we arent going to be sanitizing/sanity checking this path anymore
			 * store it in the session so that the user cant manipulate it
			 */
			$_SESSION['backup_restore_path'] = $var['restore_path'];
		}
		break;
	case 'restore':
		backup_restore($_SESSION['backup_restore_path'], $var['restore']);
		break;
	default:
		//if backup_restore_path is already set, we probobly dont want that any more. delete it
		if (isset($_SESSION['backup_restore_path'])) {
			unset($_SESSION['backup_restore_path']);
		}
		break;
}

//rnav
$var['servers'] = backup_get_server('all');
echo load_view(dirname(__FILE__) . '/views/rnav/restore.php', $var);;


//view actions
switch ($var['action']) {
	case 'browseserver':
		echo load_view(dirname(__FILE__) . '/views/restore/browseserver.php', $var);
		break;
	case 'upload':
	case 'backup_list':
		$var['servers'] = backup_get_server('all');
		$var['templates'] = backup_get_template('all_detailed');
		
		//transalate variables
		//TODO: make this anonymous once we require php 5.3
		function callback(&$var) {
			$var = backup__($var);
		}
		array_walk_recursive($var['servers'], 'callback');
		array_walk_recursive($var['templates'], 'callback');
		
		
		//TODO: if $var['restore_path'] is an array, that means it contains an error + error
		// message. Do something with the error meesage
		if (!is_array($var['restore_path'])) {
			//try to get a manifest, and continue if we did
			//dbug($var['restore_path'], backup_get_manifest_tarball($_SESSION['backup_restore_path']));
			if (!$var['manifest'] = backup_get_manifest_tarball($_SESSION['backup_restore_path'])) {
				
				//we didnt get a manifet. is this a legacy backup?
				if($var['restore_path'] = backup_migrate_legacy($dest)) {
					if(!$var['manifest'] = backup_get_manifest_tarball($var['restore_path'])) {
						//nope, doesnt seem like legacy either. Guess we cant read this file
						//TODO:alert the user
						
					} else {
						$_SESSION['backup_restore_path'] = $var['restore_path'];
					}
				}
				
			}
		}
		//dbug($var['restore_path'], $var['manifest']);
		echo load_view(dirname(__FILE__) . '/views/restore/backup_list.php', $var);
		break;
	default:
		echo load_view(dirname(__FILE__) . '/views/restore/restore.php', $var);
		break;
}
?>
