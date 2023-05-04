<?php

	/*
  FusionPBX
  Version: MPL 1.1

  The contents of this file are subject to the Mozilla Public License Version
  1.1 (the "License"); you may not use this file except in compliance with
  the License. You may obtain a copy of the License at
  http://www.mozilla.org/MPL/

  Software distributed under the License is distributed on an "AS IS" basis,
  WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
  for the specific language governing rights and limitations under the
  License.

  The Original Code is FusionPBX

  The Initial Developer of the Original Code is
  Mark J Crane <markjcrane@fusionpbx.com>
  Portions created by the Initial Developer are Copyright (C) 2018 - 2019
  the Initial Developer. All Rights Reserved.

  Contributor(s):
  Mark J Crane <markjcrane@fusionpbx.com>
  Tim Fry <tim@voipstratus.com>
 */



	/*
	 * This is designed to make an empty postgresql fusionpbx database usuable with core/upgrade/upgrade.php
	 */

	$domain_name = 'localhost';
	define('BASE_DIR','/var/www/fusionpbx');
	define('CORE_DIR',BASE_DIR . '/core');
	define('APP_DIR' ,BASE_DIR . '/app');

	//load the uuid function
	require BASE_DIR .'/resources/functions.php';

	function connect() {
		//test for v_domains to exist
		try {
			$con = new \PDO('pgsql:host=db;port=5432;dbname=fusionpbx', 'fusionpbx','fusionpbx');
		} catch (Exception $ex) {
			die($ex->getMessage());
		}
		return $con;
	}

	function has_table(PDO $con, string $table) {
		try {
			$sql = "select * from $table";
			$statement = $con->prepare($sql);
			$statement->execute();
		} catch (Exception $e) {
			return false;
		}
		return true;
	}

	function db_execute($con, $sql, ?int $fetch_type = null) {
		$statement = $con->prepare($sql);
		$result = $statement->execute();
		if($result !== false) {
			if($fetch_type === null)
				return;
			else
				return $statement->fetch($fetch_type);
		}
		return false;
	}

	function get_schema_from_app_config($path, $file = 'app_config.php') {
		$x = 0;
		$config = $path . '/' . $file;
		if(file_exists($config))
			require $config;
		if(!empty($apps) && is_array($apps) && count($apps) > 0) {
			if(!empty($apps[0]['db'])) {
				return $apps[0]['db'];
			}
		}
		return null;
	}

	function write_schema($con, $schema) {
		if(empty($schema))
			return;
		if(!is_array($schema))
			return;
		foreach($schema as $table) {
			$table_name = $table['table']['name'];
			if(is_array($table_name)) {
				$table_name = $table['table']['name']['text'];
			}
			$sql = "create table $table_name (";
			if(!empty($table['fields'])) {
				foreach($table['fields'] as $field) {
					if(isset($field['deprecated']) && $field['deprecated'] == true)
						continue;
					$field_name = $field['name'];
					if(is_array($field_name)) {
						$field_name = $field['name']['text'];
					}
					$field_type = $field['type'];
					if(is_array($field_type)) {
						$field_type = $field_type['pgsql'];
					}
					$sql .= "$field_name $field_type";
					if(!empty($field['key']['type'])) {
						$field_key_type = $field['key']['type'];
						if($field_key_type === 'primary') {
							$sql .= " primary key";
						}
						if($field_key_type === 'foreign') {
							$foreign_key_table = $field['key']['reference']['table'];
							$foreign_key_field = $field['key']['reference']['field'];
						}
					}
					$sql .= ",";
				}
				if(substr($sql, -1) === ",") {
					$sql = substr($sql, 0, strlen($sql)-1);
				}
			}
			$sql .= ")";
			db_execute($con, $sql);
		}
	}

	function get_switch_setting($con, $subcategory) {
		return db_execute($con, "select default_setting_value from v_default_settings"
		. " where default_setting_category='switch' and default_setting_subcategory='$subcategory'", 7);
	}

	function put_switch_setting($con, $uuid, $subcategory, $value) {
		db_execute($con, "insert into v_default_settings("
			. "default_setting_uuid"
			. ",default_setting_category"
			. ",default_setting_subcategory"
			. ",default_setting_name"
			. ",default_setting_value"
			. ",default_setting_enabled"
			. ") values ("
			. "'$uuid'"
			. ",'switch'"
			. ",'$subcategory'"
			. ",'dir'"
			. ",'$value'"
			. ",true"
			. ")"
			);
	}

	$con = connect();
	if(!has_table($con, 'v_domains')) {
		write_schema($con, get_schema_from_app_config(CORE_DIR . '/domains'));
		db_execute($con, "insert into v_domains("
			. "domain_uuid"
			. ",domain_name"
			. ",domain_enabled"
			. ") values ("
			. "'" . uuid() . "'"
			. ",'$domain_name'"
			. ",true"
			. ")");
	}
	$domain_uuid = db_execute($con, "select domain_uuid from v_domains where domain_name='$domain_name'", 7);

	if(!has_table($con, 'v_users')) {
		write_schema($con, get_schema_from_app_config(CORE_DIR . '/users'));
		db_execute($con, "insert into v_users("
			. "user_uuid"
			. ",domain_uuid"
			. ",username"
			. ",password"
			. ",user_enabled"
			. ") values ("
			. "'" . uuid() . "'"
			. ",'$domain_uuid'"
			. ",'admin'"
			. ",'" . password_hash('password', PASSWORD_BCRYPT) . "'"
			. ",'true'"
			. ")");
	}

	$sadmin_uuid = db_execute($con, "select user_uuid from v_users where username='admin'", 7);
	
	if(!has_table($con, 'v_groups')) {
		write_schema($con, get_schema_from_app_config(CORE_DIR . '/groups'));
		db_execute($con, "insert into v_groups(group_uuid,group_name,group_level) values ('" . uuid() . "','superadmin',80)");
		db_execute($con, "insert into v_groups(group_uuid,group_name,group_level) values ('" . uuid() . "','admin',50)");
		db_execute($con, "insert into v_groups(group_uuid,group_name,group_level) values ('" . uuid() . "','supervisor',40)");
		db_execute($con, "insert into v_groups(group_uuid,group_name,group_level) values ('" . uuid() . "','user',30)");
		db_execute($con, "insert into v_groups(group_uuid,group_name,group_level) values ('" . uuid() . "','agent',20)");
		db_execute($con, "insert into v_groups(group_uuid,group_name,group_level) values ('" . uuid() . "','public',10)");
	}

	$sadmin_group_uuid = db_execute($con, "select group_uuid from v_groups where group_name='superadmin'",7);
	$user_group_uuid = db_execute($con, "select user_group_uuid from v_user_groups"
		. " where domain_uuid='$domain_uuid' and group_uuid='$sadmin_group_uuid' and user_uuid='$sadmin_uuid'", 7);

	if(empty($user_group_uuid)) {
		db_execute($con, "insert into v_user_groups("
			. "user_group_uuid"
			. ",domain_uuid"
			. ",group_name"
			. ",group_uuid"
			. ",user_uuid"
			. ") values ("
			. "'" . uuid() . "'"
			. ",'$domain_uuid'"
			. ",'superadmin'"
			. ",'$sadmin_group_uuid'"
			. ",'$sadmin_uuid'"
			. ")");
	}

	if(!has_table($con, 'v_software')) 
		write_schema($con, get_schema_from_app_config(CORE_DIR.'/software'));
	
	if(!has_table($con, 'v_default_settings'))
		write_schema($con, get_schema_from_app_config(CORE_DIR.'/default_settings'));
	
	if(empty(get_switch_setting($con, 'base')))
		put_switch_setting($con, '09e2eed0-0254-4e57-860c-f281491927c8' ,'base', '');

	if(empty(get_switch_setting($con, 'bin')))
		put_switch_setting($con, '330d837a-b8bf-4fe8-afbf-413073c4ff24' ,'bin', '/usr/bin/freeswitch');

	if(empty(get_switch_setting($con, 'conf')))
		put_switch_setting($con, '5c0413bb-530c-44fb-a12c-1dab02066a3c' ,'conf', '/etc/freeswitch');

	if(empty(get_switch_setting($con, 'db')))
		put_switch_setting($con, '45ba4ffe-b303-4d64-b94a-1801ac4177f8' ,'db', '/var/lib/freeswitch/db');

	if(empty(get_switch_setting($con, 'grammar')))
		put_switch_setting($con, '1b5a5dbe-7061-444d-a701-ed1b634468fa' ,'grammar', '/var/lib/freeswitch/grammar');

	if(empty(get_switch_setting($con, 'log')))
		put_switch_setting($con, '1660dde2-6931-41da-94ec-bd78352b5eb1' ,'log', '/var/log/freeswitch');

	if(empty(get_switch_setting($con, 'mod')))
		put_switch_setting($con, '1e76373d-7362-4381-b343-963e39f8c5e3' ,'mod', '/usr/lib/freeswitch/mod');

	if(empty(get_switch_setting($con, 'languages')))
		put_switch_setting($con, '1366d3ce-3399-437a-958b-e7f2fba1f716' ,'languages', '/etc/freeswitch/languages');

	if(empty(get_switch_setting($con, 'recordings')))
		put_switch_setting($con, '0440f651-dd9e-46b2-ad50-fd1252486210' ,'recordings', '/var/lib/freeswitch/recordings');

	if(empty(get_switch_setting($con, 'scripts')))
		put_switch_setting($con, 'fae9105c-c64a-4534-b7b0-d95da1c988c2' ,'scripts', '/usr/share/freeswitch/scripts');

	if(empty(get_switch_setting($con, 'sip_profiles')))
		put_switch_setting($con, '874bed95-6c3b-4fe0-be8a-ea2dcecac109' ,'sip_profiles', '/etc/freeswitch/sip_profiles');

	if(empty(get_switch_setting($con, 'sounds')))
		put_switch_setting($con, 'b332acc0-c48f-41d2-8d5d-e5452c1cfd86' ,'sounds', '/usr/share/freeswitch/sounds');

	if(empty(get_switch_setting($con, 'storage')))
		put_switch_setting($con, 'b21f3949-e75e-459d-8add-3c73ba72e6cc' ,'storage', '/var/lib/freeswitch/storage');

	if(empty(get_switch_setting($con, 'voicemail')))
		put_switch_setting($con, 'ba3ac900-245c-4cff-a191-829137db47d8' ,'voicemail', '/var/lib/freeswitch/storage/voicemail');

	//now run the core upgrade twice

	try {
		// consume the output
		ob_start();
		include CORE_DIR . '/upgrade/upgrade.php';
		ob_end_clean();
	} catch (\Throwable $t) {

	}

	include CORE_DIR . '/upgrade/upgrade.php';
