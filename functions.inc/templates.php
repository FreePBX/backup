<?php

function backup_del_template($id) {
	global $db;
	$data = backup_get_template($id);
	
	//dont delete if deleting has been blocked
	if ($data['immortal'] == 'true') {
		return $id;
	}
	
	$sql = 'DELETE FROM backup_templates WHERE id = ?';
	$ret = $db->query($sql, $id);
	if ($db->IsError($ret)){
		die_freepbx($ret->getDebugInfo());
	}
	
	$sql = 'DELETE FROM backup_template_details WHERE template_id = ?';
	$ret = $db->query($sql, $id);
	if ($db->IsError($ret)){
		die_freepbx($ret->getDebugInfo());
	}
	
	/*todo: selete servers from backups
	$sql = 'DELETE FROM backup_details WHERE server = ?';
	$ret = $db->query($sql, $id);
	if ($db->IsError($ret)){
		die_freepbx($ret->getDebugInfo());
	}*/
	
	return '';
}

function backup_get_template($id = '') {
	global $db;
	
	//return a blank if no id was set, all servers if 'all' was passed
	//otherwise, a specifc server

	switch ($id) {
		case '':
			$ret = array(
					'id'		=> '',
					'name'		=> '',
					'desc'		=> '',
					'items'		=> array(),
					'immortal'	=> ''
					);
			return $ret;
			break;
		case 'all':
		case 'all_detailed':
			$ret = sql('SELECT * FROM backup_templates ORDER BY name', 'getAll', DB_FETCHMODE_ASSOC);
			$templates = array();
			//set index to server id for easy retrieval
			foreach ($ret as $s) {
				//set index to  id for easy retrieval
				$templates[$s['id']] = $s;
				
				//default name in one is missing
				if (!$templates[$s['id']]['name']) {
					$templates[$s['id']]['name'] = _('Template') . ' ' . $s['id'];
				}
				
				//add details if requested
				if ($id == 'all_detailed') {
					$templates[$s['id']] = backup_get_template($s['id']);
				}
			}

			return $templates;
			break;
		default:
			$sql = 'SELECT * FROM backup_templates WHERE id = ?';
			$ret = $db->getAll($sql, array($id), DB_FETCHMODE_ASSOC);
			if ($db->IsError($ret)){
				die_freepbx($ret->getDebugInfo());
			}
			
			//return a blank set if an invalid id was entered
			if (!$ret) {
				return backup_get_template('');
			}
			
			$ret = $ret[0];
			$sql = 'SELECT type, path, exclude FROM backup_template_details WHERE template_id = ?';
			$ret1 = $db->getAll($sql, array($id), DB_FETCHMODE_ASSOC);
			if ($db->IsError($ret1)){
				die_freepbx($ret1->getDebugInfo());
			}
			
			if ($ret1) {
				foreach ($ret1 as $res) {
					foreach($res as $key => $value) {
						$my[$key] = $value;
 					}
					if ($my['exclude']) {
						$my['exclude'] = unserialize($my['exclude']);
					}
					$ret['items'][] = $my;
					unset($my);
				}
			} else {
				$ret['items'] = array();
			}


			//default a name
			$ret['name'] = $ret['name'] ? $ret['name'] : 'Template ' . $ret['id'];
			
			return $ret;
			break;
	}
}

function backup_put_template($var) {
	global $db, $amp_conf;
	
	//dont save protected templates
	if ($var['id']) {
		$stale = backup_get_template($var['id']);
		if ($stale['immortal'] == 'true') {
			return false;
		}
	}
	
	//save server
	$sql = 'REPLACE INTO backup_templates (id, name, `desc`) VALUES (?, ?, ?)';
	$ret = $db->query($sql, array($var['id'], $var['name'], $var['desc']));
	if ($db->IsError($ret)){
		die_freepbx($ret->getDebugInfo());
	}
	
	$sql = ($amp_conf["AMPDBENGINE"] == "sqlite3") ? 'SELECT last_insert_rowid()' : 'SELECT LAST_INSERT_ID()';
	$var['id'] = $var['id'] ? $var['id'] : $db->getOne($sql);

	//save server details
	//first delete stale
	$sql = 'DELETE FROM backup_template_details WHERE template_id = ?';
	$ret = $db->query($sql, $var['id']);
	if ($db->IsError($ret)){
		die_freepbx($ret->getDebugInfo());
	}
	
	//prepare array for insertion
	$saved = array();
	if (is_array($var['type'])) {
		foreach ($var['type'] as $e_id => $type) {
			if (!isset($saved[$type], $saved[$type][$var['path'][$e_id]])) {
				//mark row as saved so that we can check for dups
				$saved[$type][$var['path'][$e_id]] = true;
				
				//ensure excludes are unique and clean
				$excludes = explode("\n", $var['exclude'][$e_id]);
				foreach ($excludes as $my => $e) {
					$excludes[$my] = trim($e);
				}
				$excludes  = array_unique($excludes);
				$data[] = array($var['id'],  $type, $var['path'][$e_id], serialize($excludes));
			}
		}
		
		//then insert fresh
		$sql = $db->prepare('INSERT INTO backup_template_details (template_id, type, path, exclude) VALUES (?, ?, ?, ?)');
		$ret = $db->executeMultiple($sql, $data);
		if ($db->IsError($ret)){
			die_freepbx($ret->getDebugInfo());
		}
	}

	
	return $var['id'];
}

 /**
 * $c is count, $i is item
 */
function backup_template_generate_tr($c, $i, $immortal = 'false', $build_tr = false) {
	$type			= '';
	$path			= '';
	$exclude		= '';
	$server_list	= array();
	static $servers;
	
	switch ($i['type']) {
		case 'file':
			$type		= _('File') . form_hidden('type[' . $c . ']', 'file');
			$path 		= array(
							'name'			=> 'path[' . $c . ']', 
							'value'			=> $i['path'],
							'required'		=> '',
							'placeholder'	=> _('/path/to/file')
						);
			$immortal ? $path['disabled'] = '' : '';
			$path		= form_input($path);
			$exclude	= form_hidden('exclude[' . $c . ']', '');
			break;
		
		case 'dir':
			$type		= _('Directory') . form_hidden('type[' . $c . ']', 'dir');
			$path 		= array(
							'name'			=> 'path[' . $c . ']', 
							'value'			=> $i['path'],
							'required'		=> '',
							'placeholder'	=> _('/path/to/dir')
						);
			$immortal ? $path['disabled'] = '' : '';
			$path		= form_input($path);
			$exclude 	= array(
							'name'			=> 'exclude[' . $c . ']', 
							'value'			=> implode("\n", $i['exclude']),
							'rows'			=> count($i['exclude']),
							'cols'			=> 20,
							'placeholder'	=> _('PATTERNs, one per line')
						);
			$immortal ? $exclude['disabled'] = '' : '';
			$exclude	= form_textarea($exclude);
			break;
		
		case 'mysql':
			$type		= _('Mysql') . form_hidden('type[' . $c . ']', 'mysql');
			$servers	= backup_get_Server('all');
			
			//draw list of mysql servers for dorpdown
			foreach ($servers as $s) {
				if ($s['type'] == 'mysql') {
					$server_list['server-' . $s['id']] = $s['name'];
				}
			}
			
			if ($server_list) {
				$more 		= $immortal ? ' disabled ' : '';
				$path		= form_dropdown('path[' . $c . ']', $server_list, $i['path'], $more);
			} else {
				$path		= _('{no servers available}');
			}

			$exclude 	= array(
							'name'			=> 'exclude[' . $c . ']', 
							'value'			=> implode("\n", $i['exclude']),
							'rows'			=> count($i['exclude']),
							'cols'			=> 20,
							'placeholder'	=> _('table names, one per line')
						);
			$immortal || !$server_list ? $exclude['disabled'] = '' : '';
			$exclude	= form_textarea($exclude);
			break;
		
		case 'astdb':
			$type		= _('Asterisk DB') . form_hidden('type[' . $c . ']', 'astdb');
			$path 		= form_hidden('path[' . $c . ']', '');
			$exclude 	= array(
							'name'			=> 'exclude[' . $c . ']', 
							'value'			=> implode("\n", $i['exclude']),
							'rows'			=> count($i['exclude']),
							'cols'			=> 20,
							'placeholder'	=> _('Family, one per line')
						);
			$immortal ? $exclude['disabled'] = '' : '';
			$exclude	= form_textarea($exclude);
			break;
	}
	
	$del_txt	= _('Delete this entry. Don\'t forget to click Submit to save changes!');
	$delete		= $immortal == 'true' ? ''
				: '<img src="images/trash.png" style="cursor:pointer" title="' 
				. $del_txt . '" class="delete_entrie">';
				
	if($build_tr) {
		return '<tr><td>'	
				. $type 	. '</td><td>' 
				. $path		. '</td><td>' 
				. $exclude	. '</td><td>' 
				. $delete	. '</td></tr>';
	} else {
		return array('type' => $type, 'path' => $path, 'exclude' => $exclude, 'delete' => $delete);
	}
 	
}
?>