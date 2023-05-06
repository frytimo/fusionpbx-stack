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
		echo "Creating v_domains\n";
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
			. ",'admin'"
			. ",'" . password_hash('password', PASSWORD_BCRYPT) . "'"
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

	//now run the core upgrade twice

	try {
		// consume the output
		ob_start();
		include CORE_DIR . '/upgrade/upgrade.php';
		ob_end_clean();
	} catch (\Throwable $t) {

	}

	include CORE_DIR . '/upgrade/upgrade.php';

	//after upgrade.php executes it will wipe out the modules.conf.xml so put it back
	$module_data = <<<EOF
<configuration name="modules.conf" description="Modules">
  <modules>
    <!-- Loggers (I'd load these first) -->
    <load module="mod_console"/>
    <!-- <load module="mod_graylog2"/> -->
    <load module="mod_logfile"/>
    <!-- <load module="mod_syslog"/> -->

    <!--<load module="mod_yaml"/>-->

    <!-- Multi-Faceted -->
    <!-- mod_enum is a dialplan interface, an application interface and an api command interface -->
    <load module="mod_enum"/>

    <!-- XML Interfaces -->
    <!-- <load module="mod_xml_rpc"/> -->
    <!-- <load module="mod_xml_curl"/> -->
    <!-- <load module="mod_xml_cdr"/> -->
    <!-- <load module="mod_xml_radius"/> -->
    <!-- <load module="mod_xml_scgi"/> -->

    <!-- Event Handlers -->
    <!-- <load module="mod_amqp"/> -->
    <load module="mod_cdr_csv"/>
    <!-- <load module="mod_cdr_sqlite"/> -->
    <!-- <load module="mod_event_multicast"/> -->
    <load module="mod_event_socket"/>
    <!-- <load module="mod_event_zmq"/> -->
    <!-- <load module="mod_zeroconf"/> -->
    <!-- <load module="mod_erlang_event"/> -->
    <!-- <load module="mod_smpp"/> -->
    <!-- <load module="mod_snmp"/> -->

    <!-- Directory Interfaces -->
    <!-- <load module="mod_ldap"/> -->

    <!-- Endpoints -->
    <!-- <load module="mod_dingaling"/> -->
    <!-- <load module="mod_portaudio"/> -->
    <!-- <load module="mod_alsa"/> -->
    <load module="mod_sofia"/>
    <load module="mod_loopback"/>
    <!-- <load module="mod_woomera"/> -->
    <!-- <load module="mod_freetdm"/> -->
    <!-- <load module="mod_unicall"/> -->
    <!-- <load module="mod_skinny"/> -->
    <!-- <load module="mod_khomp"/>   -->
    <load module="mod_rtc"/>
    <!-- <load module="mod_rtmp"/>   -->
    <load module="mod_verto"/>

    <!-- Applications -->
    <load module="mod_signalwire"/>
    <load module="mod_commands"/>
    <load module="mod_conference"/>
    <!-- <load module="mod_curl"/> -->
    <load module="mod_db"/>
    <load module="mod_dptools"/>
    <load module="mod_expr"/>
    <load module="mod_fifo"/>
    <load module="mod_hash"/>
    <!--<load module="mod_mongo"/> -->
    <load module="mod_voicemail"/>
    <!--<load module="mod_directory"/>-->
    <!--<load module="mod_distributor"/>-->
    <!--<load module="mod_lcr"/>-->
    <!--<load module="mod_easyroute"/>-->
    <load module="mod_esf"/>
    <load module="mod_fsv"/>
    <!--<load module="mod_cluechoo"/>-->
    <load module="mod_valet_parking"/>
    <!--<load module="mod_fsk"/>-->
    <!--<load module="mod_spy"/>-->
    <!--<load module="mod_sms"/>-->
    <!--<load module="mod_sms_flowroute"/>-->
    <!--<load module="mod_smpp"/>-->
    <!--<load module="mod_random"/>-->
    <load module="mod_httapi"/>
    <!--<load module="mod_translate"/>-->

    <!-- SNOM Module -->
    <!--<load module="mod_snom"/>-->

    <!-- This one only works on Linux for now -->
    <!--<load module="mod_ladspa"/>-->

    <!-- Dialplan Interfaces -->
    <!-- <load module="mod_dialplan_directory"/> -->
    <load module="mod_dialplan_xml"/>
    <load module="mod_dialplan_asterisk"/>

    <!-- Codec Interfaces -->
    <load module="mod_spandsp"/>
    <load module="mod_g723_1"/>
    <load module="mod_g729"/>
    <load module="mod_amr"/>
    <!--<load module="mod_ilbc"/>-->
    <!--<load module="mod_h26x"/>-->
    <load module="mod_b64"/>
    <!--<load module="mod_siren"/>-->
    <!--<load module="mod_isac"/>-->
    <load module="mod_opus"/>

    <!-- File Format Interfaces -->
    <load module="mod_av"/>
    <load module="mod_sndfile"/>
    <load module="mod_native_file"/>
    <!--<load module="mod_opusfile"/>-->
    <load module="mod_png"/>
    <!-- <load module="mod_shell_stream"/> -->
    <!--For icecast/mp3 streams/files-->
    <!--<load module="mod_shout"/>-->
    <!--For local streams (play all the files in a directory)-->
    <load module="mod_local_stream"/>
    <load module="mod_tone_stream"/>

    <!-- Timers -->
    <!-- <load module="mod_timerfd"/> -->
    <!-- <load module="mod_posix_timer"/> -->

    <!-- Languages -->
    <!-- <load module="mod_v8"/> -->
    <!-- <load module="mod_perl"/> -->
    <!-- <load module="mod_python"/> -->
    <!-- <load module="mod_python3"/> -->
    <!-- <load module="mod_java"/> -->
    <load module="mod_lua"/>

    <!-- ASR /TTS -->
    <!-- <load module="mod_flite"/> -->
    <!-- <load module="mod_pocketsphinx"/> -->
    <!-- <load module="mod_cepstral"/> -->
    <!-- <load module="mod_tts_commandline"/> -->
    <!-- <load module="mod_rss"/> -->

    <!-- Say -->
    <load module="mod_say_en"/>
    <!-- <load module="mod_say_ru"/> -->
    <!-- <load module="mod_say_zh"/> -->
    <!-- <load module="mod_say_sv"/> -->

    <!-- Third party modules -->
    <!--<load module="mod_nibblebill"/>-->
    <!--<load module="mod_callcenter"/>-->

  </modules>
</configuration>
EOF;

	try {
		$fhandle = fopen('/etc/freeswitch/autoload_configs/modules.conf.xml', w);
		fwrite($fhandle, $module_data);
		fclose($fhandle);
	} catch (\Throwable $e) {
		// do nothing
	}