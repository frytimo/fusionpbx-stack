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

	// read the environment file from /etc/fusionpbx/config.conf
	$settings = parse_ini_file('/etc/fusionpbx/config.conf');

	// database connection and type (dsn)
	define('DB_TYPE', $settings['database.0.type']);
	define('DB_HOST', $settings['database.0.host']);
	define('DB_PORT', $settings['database.0.port']);
	define('DB_NAME', $settings['database.0.name']);
	define('DB_USERNAME', $settings['database.0.username']);
	define('DB_PASSWORD', $settings['database.0.password']);

	// initial settings to use for admin login and password
	define('DOMAIN_NAME',    $settings['init.domain.name']);
	define('ADMIN_NAME',     $settings['init.admin.name']);
	define('ADMIN_PASSWORD', $settings['init.admin.password']);
	
	// directory structure
	define('BASE_DIR',$settings['document.root']);
	define('CORE_DIR',BASE_DIR . '/core');
	define('APP_DIR' ,BASE_DIR . '/app');

	//set include path
	set_include_path(BASE_DIR);

	//load the uuid function
	require BASE_DIR .'/resources/functions.php';

	function connect() {
		$tries = 0;
		while($tries++ < 10) {
			//test for v_domains to exist
			try {
				$con = new \PDO(DB_TYPE.':host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME, DB_USERNAME, DB_PASSWORD);
				return $con;
			} catch (Exception $ex) {
				sleep(1);
			}
		}
		die('Unable to connect after 10 tries');
	}

	// checks for a table to exist or not assuming this is a postgres connection
	function has_table($con, $table_name, $schema = "public") {
		$statement = $con->prepare("SELECT COUNT(*)"
			. " FROM information_schema.tables"
			. " WHERE table_schema LIKE '$schema' AND"
			. " table_type LIKE 'BASE_TABLE' AND"
			. " table_name = :table_name"
			. " LIMIT 1");
		$success = $statement->execute(['table_name' => $table_name]);
		if ($success !== false) {
			$result = $statement->fetchAll(PDO::FETCH_COLUMN);
			if (!empty($result) && count($result) > 0 && $result[0] === 1)
				return true;
		}
		return false;
	}

	/**
	 * Execute a statement and return a value if fetch_type is set
	 * @param type $con
	 * @param type $sql
	 * @param int|null $fetch_type
	 * @return bool
	 */
	function db_execute($con, $sql, ?int $fetch_type = null) {
		//allow sql commands to fail without crashing
		$con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		//prepare the sql statement
		$statement = $con->prepare($sql);
		//execute the statement
		$result = $statement->execute();
		if($result !== false) {
			if($fetch_type === null)
				return;
			else
				switch($fetch_type) {
					case PDO::FETCH_COLUMN:
						return $statement->fetchColumn();
					case PDO::FETCH_ASSOC:
						return $statement->fetchAll(PDO::FETCH_ASSOC);
					default:
						return $statement->fetch($fetch_type);
				}
		}
		return false;
	}

	/**
	 * Read a schema array from an existing FusionPBX app_config style file
	 * @param type $path full directory path
	 * @param type $file if not provided default is app_config.php
	 * @return type
	 */
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

	/**
	 * Writes a FusionPBX app_config.php style schema array to the database
	 * @param type $con PDO connection
	 * @param type $schema FusionPBX app_config.php style schema array
	 */
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
			$sql = "create table if not exists $table_name (";
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

	/**
	 * Reads a default_setting_value for a switch setting from the database
	 * @param type $con	PDO connection
	 * @param type $subcategory switch setting
	 * @return type
	 */
	function get_switch_setting($con, $subcategory) {
		return db_execute($con, "select default_setting_value from v_default_settings"
		. " where default_setting_category='switch' and default_setting_subcategory='$subcategory'", 7);
	}

	function enable_switch_setting($con, $uuid) {
		db_execute($con, "update v_default_settings"
			. " set default_setting_enabled = true"
			. " where default_setting_uuid = '$uuid'");
	}

	/**
	 * Writes a default_setting_value for a switch setting to the database
	 * @param type $con PDO connection
	 * @param string $uuid must be a valid UUID type
	 * @param string $subcategory switch setting
	 * @param string $value value to store in database
	 */
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


	function make_directory($directory) {
		if(!file_exists($directory))
			mkdir($directory);
	}

	// checks for the dsn pre-process connector in the database
	function dsn_exists($con) {
		return ((int)db_execute($con, "select count(var_uuid) from v_vars where var_category='DSN' and var_name='db_dsn' and var_enabled='true'", PDO::FETCH_COLUMN) > 0);
	}

	function rewrite_event_socket_config() {
//		$xml_object = simplexml_load_file('/etc/freeswitch/autoload_configs/event_socket.conf.xml');
//		$json = json_encode($xml_object);
//		$conf_array = json_decode($json, true);
//
//		$found = false;
//		$inbound_acl_exists = false;
//		$sb = '<configuration name="event_socket.conf" description="Socket Client">' . "\n";
//		$sb .= '	<settings>'."\n";
//		foreach($conf_array['settings']['param'] as &$param) {
//			$sb .= "		<param";
//			foreach($param as &$attributes) {
//				foreach($attributes as $key => &$value) {
//					if($found) {
//						$value = '0.0.0.0';
//					}
//					$found = ($value === 'listen-ip');
//					if($value === 'apply-inbound-acl') {
//						$inbound_acl_exists = true;
//					}
//					$sb .= " $key=\"$value\"";
//				}
//			}
//			$sb .= " />\n";
//		}
//		if(!$inbound_acl_exists) {
//			$sb .= '		<param name="apply-inbound-acl" value="any_v4.auto" />' . "\n";
//		}
//		$sb .= '	</settings>'."\n";
//		$sb .= '</configuration>';
//		$fhandle = fopen('/etc/freeswitch/autoload_configs/event_socket.conf.xml', 'w');
//		fwrite($fhandle, $sb);
//		fclose($fhandle);

		$data = <<<EOF
<configuration name="event_socket.conf" description="Socket Client">
        <settings>
                <param name="nat-map" value="false" />
                <param name="listen-ip" value="0.0.0.0" />
                <param name="listen-port" value="8021" />
                <param name="password" value="ClueCon" />
                <param name="apply-inbound-acl" value="any_v4.auto" />
        </settings>
</configuration>
EOF;

		//always replace contents
		file_put_contents('/etc/freeswitch/autoload_configs/event_socket.conf.xml', $data);
	}

	function rewrite_modules_conf() {
		$data = <<<EOF
<configuration name="modules.conf" description="Modules">
        <modules>

                <!-- Applications -->
                <load module="mod_commands"/>
                <load module="mod_memcache"/>

                <!-- Languages -->
                <load module="mod_lua"/>

                <!-- Endpoints -->
                <load module="mod_sofia"/>

                <!-- Loggers -->
                <load module="mod_logfile"/>
                <load module="mod_console"/>

                <!-- Applications -->
                <load module="mod_callcenter"/>
                <load module="mod_fifo"/>
                <load module="mod_sms"/>
                <load module="mod_fsv"/>
                <load module="mod_esf"/>
                <load module="mod_expr"/>
                <load module="mod_dptools"/>
                <load module="mod_enum"/>
                <load module="mod_valet_parking"/>
                <load module="mod_spandsp"/>
                <load module="mod_db"/>
                <load module="mod_hash"/>
                <load module="mod_conference"/>

                <!-- Auto -->

                <!-- Codecs -->
                <load module="mod_g729"/>
                <load module="mod_g723_1"/>
                <load module="mod_bv"/>
                <load module="mod_amr"/>
                <load module="mod_h26x"/>

                <!-- Dialplan Interfaces -->
                <load module="mod_dialplan_xml"/>

                <!-- Endpoints -->
                <load module="mod_loopback"/>

                <!-- Event Handlers -->
                <load module="mod_event_socket"/>

                <!-- File Format Interfaces -->
                <load module="mod_sndfile"/>
                <load module="mod_native_file"/>

                <!-- Say -->
                <load module="mod_say_en"/>
                <load module="mod_say_zh"/>
                <load module="mod_say_ru"/>
                <load module="mod_say_fr"/>
                <load module="mod_say_th"/>
                <load module="mod_say_he"/>
                <load module="mod_say_pt"/>
                <load module="mod_say_de"/>
                <load module="mod_say_it"/>
                <load module="mod_say_nl"/>
                <load module="mod_say_es"/>
                <load module="mod_say_hu"/>

                <!-- Speech Recognition / Text to Speech -->
                <load module="mod_flite"/>
                <load module="mod_tts_commandline"/>

                <!-- Streams / Files -->
                <load module="mod_local_stream"/>
                <load module="mod_tone_stream"/>
                <load module="mod_shout"/>

                <!-- XML Interfaces -->
                <load module="mod_xml_cdr"/>

        </modules>
</configuration>
EOF;
		//if(!file_exists('/etc/freeswitch/autoload_configs/.modules_copied')) {
			file_put_contents('/etc/freeswitch/autoload_configs/modules.conf.xml', $data);
		//	shell_exec('touch /etc/freeswitch/autoload_configs/.modules_copied');
		//}
	}

	//startup
	echo "+-----------------+\n";
	echo "|+---------------+|\n";
	echo "||  STARTING UP  ||\n";
	echo "|+---------------+|\n";
	echo "+-----------------+\n";

	//give extra time for freeswitch to be running
	sleep(10);

	//rewrite the event_socket_configuration
	rewrite_event_socket_config();

	//override session event_socket variables
	$_SESSION['event_socket_ip_address'] = 'fs';
	$_SESSION['event_socket_port'] = '8021';
	$_SESSION['event_socket_password'] = 'ClueCon';

	//connect to database and check needed values
	$con = connect();
	if(!has_table($con, 'v_domains')) {
		echo "Creating v_domains\n";
		write_schema($con, get_schema_from_app_config(CORE_DIR . '/domains'));
		db_execute($con, "insert into v_domains("
			. "domain_uuid"
			. ",domain_name"
			. ",domain_enabled"
			. ") values ("
			. "'" . uuid() . "'"
			. ",'". DOMAIN_NAME . "'"
			. ",true"
			. ")");
	}
	$domain_uuid = db_execute($con, "select domain_uuid from v_domains where domain_name='" . DOMAIN_NAME . "'", 7);

	if(empty($domain_uuid)) {
		$domain_uuid = uuid();
	}

	if(!has_table($con, 'v_users')) {
		echo "Creating v_users\n";
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
			. ",'" . ADMIN_NAME . "'"
			. ",'" . password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT) . "'"
			. ",'true'"
			. ")");
	}

	$sadmin_uuid = db_execute($con, "select user_uuid from v_users where username='admin'", 7);
	
	if(!has_table($con, 'v_groups')) {
		echo "Creating v_groups\n";
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
		echo "Creating v_software\n";
		write_schema($con, get_schema_from_app_config(CORE_DIR.'/software'));
	
	if(!has_table($con, 'v_default_settings'))
		echo "Creating v_default_settings\n";
		write_schema($con, get_schema_from_app_config(CORE_DIR.'/default_settings'));

	$base = get_switch_setting($con, 'base');
	if(is_null($base) || $base === false)
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

	if(empty(get_switch_setting($con, 'extensions')))
		put_switch_setting($con, '04e0ea1c-dc2c-4377-bee9-39adb61f2c66', 'extensions', '/etc/freeswitch/directory');

	$new_install = false;
	//move the fusionpbx version of the config to the freeswitch config
	if(!file_exists('/etc/freeswitch/.copied')) {
		$new_install = true;
		//move fusionpbx template files in
		shell_exec('rm -Rf /etc/freeswitch/*');
		shell_exec('cp -R /var/www/fusionpbx/resources/templates/conf/* /etc/freeswitch');
		shell_exec('touch /etc/freeswitch/.copied');
	}

	//rewrite the event_socket_configuration
	rewrite_event_socket_config();

	// make sure the v_settings table exists
	if(!has_table($con, 'v_settings')) {
		echo "Creating v_settings\n";
		write_schema($con, get_schema_from_app_config(APP_DIR . '/settings'));
	}

	// get the current data
	$socket_ip = db_execute($con, "select event_socket_ip_address from v_settings", 7);

	if(empty($socket_ip) || $socket_ip === false) {
		db_execute($con, "insert into v_settings ("
			. "setting_uuid"
			. ",event_socket_ip_address"
			. ",event_socket_port"
			. ",event_socket_password"
			. ",event_socket_acl"
			. ",xml_rpc_http_port"
			. ",xml_rpc_auth_realm"
			. ",xml_rpc_auth_user"
			. ",xml_rpc_auth_pass"
			. ",mod_shout_volume"
			. ") values ("
			. "'ce1b1936-fc61-4e8c-84cf-252a510a74fd'"
			. ",'fs'"
			. ",'8021'"
			. ",'ClueCon'"
			. ",'any_v4.auto'"
			. ",'8080'"
			. ",'freeswitch'"
			. ",'freeswitch'"
			. ",'works'"
			. ",'0.3'"
			. ")"
			);
	} elseif ($socket_ip !== 'fs') {
		db_execute($con, "update v_settings set event_socket_ip_address = 'fs' where setting_uuid = 'ce1b1936-fc61-4e8c-84cf-252a510a74fd'");
	}

	//ensure the sip profile directories are present
	make_directory('/etc/freeswitch/sip_profiles');
	make_directory('/etc/freeswitch/sip_profiles/internal');
	make_directory('/etc/freeswitch/sip_profiles/external');
	
//	//now run the core upgrade twice
//
//	try {
//		// consume the output
//		ob_start();
//		include CORE_DIR . '/upgrade/upgrade.php';
//		ob_end_clean();
//	} catch (\Throwable $t) {
//
//	}

	// inject the DSN in to the database
	if(!dsn_exists($con)) {
		echo "\n\n";
		echo "+----------------------------------------+\n";
		echo "| CREATING DSN CONNECTOR FOR FreeSWITCH. |\n";
		echo "|    FreeSWITCH MUST BE RESTARTED!       |\n";
		echo "+----------------------------------------+\n";
		echo "\n\n";
		$db_dsn = DB_TYPE."://hostaddr=".DB_HOST . " port=". DB_PORT . " dbname=".DB_NAME. " user=".DB_USERNAME. " password=".DB_PASSWORD;
		db_execute($con, "insert into v_vars("
			. "var_uuid"
			. ",var_category"
			. ",var_name"
			. ",var_value"
			. ",var_command"
			. ",var_enabled"
			. ",var_order) values ("
			. "'" . uuid() . "'"
			. ",'DSN'"
			. ",'db_dsn'"
			. ",'" . DB_TYPE."://hostaddr=".DB_HOST . " port=". DB_PORT . " dbname=".DB_NAME. " user=".DB_USERNAME. " password=".DB_PASSWORD. "'"
			. ",'set'"
			. ",'true'"
			. ",0)"
			);
	}

	try {
		// consume the output
		ob_start();
		include CORE_DIR . '/upgrade/upgrade.php';
		ob_end_clean();
	} catch (\Throwable $t) {

	}

	//wait a while for changes
	sleep(10);
	
	//rewrite the event_socket_configuration
	rewrite_event_socket_config();
	rewrite_modules_conf();

	//
	// ask freeswitch to restart
	//
	$socket = new event_socket;
	if (!$socket->connect('fs', '8021', 'ClueCon')) {
		echo "Unable to connect to event socket\n";
	} else {
		$cmd = "api fsctl shutdown";
		$result = $socket->request($cmd);
		if( $result !== false && !empty($result)) {
			if($result === '+OK') {
				echo "FreeSWITCH restarting successfully\n";
			}
		}
	}

	//
	//finish
	//
	echo "+----------------------------+\n";
	echo "|+--------------------------+|\n";
	echo "||  DATABASE INIT FINISHED  ||\n";
	echo "|+--------------------------+|\n";
	echo "+----------------------------+\n";
