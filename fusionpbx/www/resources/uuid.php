<?php

//set the include path
	$conf_linux = glob("/etc/fusionpbx/config.conf");
	$conf_bsd = glob("/usr/localetc/fusionpbx/config.conf");
	$conf = array_merge($conf_linux, $conf_bsd);
	set_include_path(parse_ini_file($conf[0])['document.root']);

//includes files
	include "resources/functions.php";

//show the uuid
	echo uuid();

?>
