<?php
// "Azintex.com" LLC
// 5-th floor,
// "Avenu Veraj" BC,
// S.Rakhimov 179a str.,
// Baku, Azerbaijan,
// (+99412)492 22 22
// AZ1014
// Radius Control Panel
// @author Uzdenov Hadji 2014
//###################################################################################################
//# Used solutions :																				#
//# Slim framework http://www.slimframework.com/													#
//# MysqliDb library https://github.com/joshcam/PHP-MySQLi-Database-Class/blob/master/MysqliDb.php	#
//# AdminLTE framework https://adminlte.com															#
//###################################################################################################
// Description:
// Main route file.

require 'vendor/autoload.php'; // Load Slim
require_once('lib/MysqliDb.php'); // Load MysqliDB class

$db = new MysqliDb('localhost','slimphp','3KznvqF4edMJ8XZb','radius'); // Create connection to radius database
$db_nmp = new MysqliDb('localhost','slimphp','3KznvqF4edMJ8XZb','nmp_data'); // Create connection to nmp_data database

$app = new \Slim\Slim(); // Instantiate Slim class

$pdo = new PDO('mysql:host=localhost;dbname=radius', 'slimphp', '3KznvqF4edMJ8XZb');
$nmpDb = new PDO('mysql:host=localhost;dbname=nmp_data', 'slimphp', '3KznvqF4edMJ8XZb');
$nmpDb->exec("SET NAMES utf8");

// Entry point
$app->get('/', function() use($app){
	$app->render('login.html');
});


// Authentication
$app->post('/login', function () use($app,$db_nmp) {
	$email = $app->request->post('email'); // Get email and password from login page form
	$password = md5($app->request->post('password'));

	$db_nmp->where('email',$email);
	$dbCredentials = $db_nmp->getOne('nmp_users'); // Get email, password and role from `nmp_data`.`nmp_users` table

	// Check email and password
	if($email == $dbCredentials['email'] && $password == $dbCredentials['password']){
		session_start(); // Initialize session
		
		#$app->flash('name', 'Haji');
		$_SESSION['sessionId'] = session_id(); // Set session id
		$_SESSION['authenticated'] = true;
		$_SESSION['userEmail'] = $dbCredentials['email']; // Get email
		$_SESSION['userPrivilege'] = $dbCredentials['role']; // Get role
		$_SESSION['title'] = 'Home page'; // Set title
			// Switch to the right template
			switch ($dbCredentials['role']){
				case 'admin':
					$app->render('admin_template.php');
				break;
				case 'support':
					$app->render('support_template.php');
				break;
				case 'operator':
					$app->render('operator_template.php');
				break;};
	}
	else{
		$app->redirect('/error',301);
		}
});

// Logout and destroy session
$app->get('/logout', function() use($app){
	session_start();
	session_unset();
	session_destroy();
	$app->redirect('/',301);
});

// Error
$app->get('/error', function() use($app){
	$app->render('error.html');
});

// Home page
$app->get('/home', function() use($app){
	session_start();
	$app->render($_SESSION['userPrivilege'].'_template.php');
});


// Temporary API	
$app->get('/client', function() use($app){
	session_start();
	$app->render($_SESSION['userPrivilege'].'_template.php', array('param' => 'add_client'));
});

$app->post('/client', function() use($app, $db_nmp) {
	session_start();
	$reqBody = $app->request->post();
	if($db_nmp->insert('allClients', $reqBody)) {
		$app->render($_SESSION['userPrivilege'].'_template.php', array('param' => 'client_added'));
		//$app->response->write('Ok, client added');
	}
	else {
		$app->response->write('Oops :(');
	}
});

$app->get('/pppoe', function() use($app){
    
});


// List PPPoE users page
$app->get('/list_users', function() use($app,$db_nmp){
	session_start();
    $cols = Array('company','address','person','phone','email','rate','username','password','ip_address','start_date','stop_date', 'state');
    $users = $db_nmp->get('clients',null,$cols);
	/*if ($users['state'] == 1{
		$state = "active";
	}
	else {
		$state = "nonactive";
	});*/
	if(!isset($_SESSION['authenticated'])) {
		$app->response->write('Unathorized 401');
		$app->response->setStatus(401);
	}
	else {
		$app->render($_SESSION['userPrivilege'].'_template.php', array('table_data' => $users, 'param' => 'list_users', 'dir' => 'Users', 'sub_dir' => 'List users'));
	}
});

// Get IP addresses
/* $app->get('/ips', function() use($app, $db) {
    session_start();
    $columns = Array('attribute', 'value');
    $ip_addresses = $db->get('radreply', null, $columns);
    $app->render($_SESSION['userPrivilege'].'_template.php', arr)
}); */

// Add PPPoE user page
$app->get('/add_user', function() use($app){
	session_start();
	if(!isset($_SESSION['authenticated'])) {
		$app->response->write('Unathorized 401');
		$app->response->setStatus(401);
	}
	else {
		$app->render('template.php', Array('param' => 'add_user', 'dir' => 'Users', 'sub_dir' => 'Add user'));
	}
});

// Here is start magic :)
$app->post('/add_user', function() use($app,$db,$db_nmp){
	session_start();
// Get ip which not asigned for users
	$ip_pool = Array(); // Etalon ip pool, start ip address 85.132.36.2 end ip address 85.132.36.254
	$ip_used = Array(); // Used ip addresses
	for ($ip = 2; $ip <= 254 ; $ip++){
		$ip_pool[] = "85.132.36.$ip";
		};
	$current_used_ips = $db->get('radreply',null,'value'); //Get ip addresses from radreply table
	foreach ($current_used_ips as $ip_arr) {
		foreach($ip_arr as $ip){
			$ip_used[] = $ip;
		}
	}
	sort($ip_used);
	$result_ip = array_diff($ip_pool,$ip_used);
	$ip_address = current($result_ip);

// Get list of users and check which not exist
	$users_list = Array();
	$users_exist = Array();
	for ($i = 1000; $i<=1254 ; $i++){
		$users_list[] = "ppp".$i;
	};
	$current_users = $db->get('radcheck',null,'username');
	sort($current_users);
	foreach ($current_users as $users) {
		foreach ($users as $user){
			$users_exist[] = $user;
		}
	}
	$result_user = array_diff($users_list,$users_exist);
	$username = current($result_user);

// Generate password
	$password_gen_arr = Array('A','B','C','D','E','F','G','H','J','K','M','N','P','Q','R','S','T','U','V','W','X','Y','Z','1','2','3','4','5','6','7','8','9');
	shuffle($password_gen_arr); // Shuffle array
	$password = array_slice($password_gen_arr,0,8); // Slice password from array
	$password = implode($password); // Make password string

// Get form data
	$nmp_data_clients = Array('company' => $app->request->post('company'),
					'address' => $app->request->post('address'),
					'person' => $app->request->post('person'),
					'phone' => $app->request->post('phone'),
					'email' => $app->request->post('email'),
					'rate' => $app->request->post('rate'),
					'username' => $username,
					'password' => $password,
					'ip_address' => $ip_address,
					'start_date' => date('Ymd'),
					'state' => 1);
	$rate = $app->request->post('rate');
	$rate = $rate."Mb/s";
	$radusergroup_data = Array('username' => $username,'groupname' => $rate,'priority' => 2);
	$radcheck_data = Array('username' => $username,'attribute' => 'Cleartext-Password','op' => ':=','value' => $password);
	$radreply_data = Array('username' => $username,'attribute' => 'Framed-IP-Address','value' => $ip_address);
	$db->insert('radcheck',$radcheck_data);
	$db->insert('radreply',$radreply_data);
	$db->insert('radusergroup',$radusergroup_data);
	$db_nmp->insert('clients',$nmp_data_clients);
	$app->render('template.php', Array('param' => 'added_user','data' => $nmp_data_clients));
});

$app->get('/show_added', function() use($app){
	session_start();
	$data = ["company" => "Azitex", "address" => "Avenue Veraj", "person" => "Uzdenov Haji", 
			"phone" => "0504499126", "email" => "h.uzdenov@azintex.com", "rate" => "100", 
			"username" => "ppp2054", "password" => "BH78NBD", "ip_address" => "85.132.63.188",
			"start_date" => "2021-09-30", "1"];
	$app->render('template.php', Array('param' => 'added_user', 'data' => $data));
});


/* API */



// User group
$app->group('/api/v1', function() use($app, $db_nmp, $db) {
	
	// Get all users
	$app->get('/users', function() use($app, $db_nmp) {
		$users = $db_nmp->get('clients');
		$json = json_encode($users, JSON_PRETTY_PRINT);
		$app->response->headers->set('Content-Type', 'application/json');
		$app->response->setBody($json);
	});
	
	
	// Get user by id or username
	$app->get('/users/:username', function($username) use($app, $db_nmp) {
		$db_nmp->where('username', $username);
		$json = json_encode($db_nmp->get('clients', null), JSON_PRETTY_PRINT);
		$app->response->headers->set('Content-Type', 'application/json; charset=utf-8');
		$app->response->setBody($json);
	});
	
	// Get all accounting data
	$app->get('/accounting', function() use($app, $db){
		$columns = ['radacctid', 'username', 'acctstarttime', 'acctstoptime', 'acctterminatecause'];
		$db->groupBy('username');
		$db->orderBy('username', 'DESC');
		$accounting = $db->get('radacct', null, $columns);
		$json = json_encode($accounting, JSON_PRETTY_PRINT);
		$app->response->headers->set('Content-Type', 'application/json; charset=utf-8');
		$app->response->setBody($json);
	});
	
	// Get accounting by userName
	$app->get('/accounting/:username', function($username) use($app, $db) {
		$db->where('username', $username);
		$json = json_encode($db->get('radacct', null), JSON_PRETTY_PRINT);
		$app->response->headers->set('Content-Type', 'application/json; charset=urf-8');
		$app->response->setBody($json);
	});
});

$app->get('/api/v1/show', function() use($app) {
	$cidDate = date('dmy');
	$cidPrefix = 'AZX';
	$vlans = ['578', '984', '990', '1000', '1551', '2811'];
	$connectionTypesCode = ['Twisted pair' => 'TP', 'Fiber optic' => 'FO', 'Wireless' => 'RL'];
	$cidSerial = rand(100500, 9999999);
	$cid = $cidPrefix . '/' . $vlans[0] . $connectionTypesCode['Twisted pair'] . $cidDate . ' -- ' . $cidSerial;
	echo($cid);
});

/* $app->get('/api/v1/users', function() use($nmpDb, $app){
	$sqlReq = $nmpDb->prepare("SELECT * FROM `clients`");
	$sqlReq->execute();
	$data = $sqlReq->fetchAll(PDO::FETCH_ASSOC);
    $json = json_encode($data, JSON_PRETTY_PRINT);
    $app->response->headers->set('Content-Type', 'application/json');
    $app->response->setBody($json);
}); */

$app->get('/api/users/:username', function($username) use($app,$db_nmp) {
	$columns = ['id', 'company', 'address', 'person', 'phone', 'email', 'rate', 'username', 'password', 'ip_address', 'start_date', 'stop_date', 'state'];
	$db_nmp->where('username', $username);
	$response = $db_nmp->get('clients', null, $columns);
	$json = json_encode($response, JSON_PRETTY_PRINT);
	$app->response->headers->set('Content-Type', 'application/json; charset=utf-8');
	$app->response->setBody($json);
});

$app->get('/api/users/v2', function() use($app, $db_nmp) {
    $users = $db_nmp->get('clients');
    $json = json_encode($users, JSON_PRETTY_PRINT);
    $app->response->headers->set('Content-Type', 'application/json; charset=utf-8');
    $app->response->setBody($json);
});



$app->get('/api/accounting/:username', function($username) use($app, $db){
	$columns = ['radacctid', 'username', 'acctstarttime', 'acctstoptime', 'acctterminatecause'];
	$db->where('username', $username);
	$db->orderBy('acctstarttime', 'DESC');
	$response = $db->get('radacct', null, $columns);
	$json = json_encode($response, JSON_PRETTY_PRINT);
	$app->response->headers->set('Content-Type', 'application/json; charset=utf-8');
	$app->response->setBody($json);
});

$app->put('/api/update/:id', function($id) use($app, $db){
	$jsonData = json_decode($app->request->getBody(), true);
  $db->where('id', $id);
  $userItemData = $db->getOne('active_clients');
  $username = $userItemData['username'];
  echo $username;
  // $rate = $jsonData['rate'];
	// $db->where('id', $id);
	//    if($db->update('active_clients', $jsonData)){
  //      $db->where('username', $username);
  //      $db->update('radusergroup', $rate."Mb/s");
	//    }
	//    else{
	//   	  echo 'Something gona wrong';
	//    }
});

$app->delete('/api/v1/users', function() use ($app, $db) {
	$requestJSON = json_decode($app->request->getBody(), true);
	//$lastConnectionDate
	//$db->startTransaction();
	var_dump($requestJSON);
	//$accountingArchiveData = "SELECT $requestJSON['company'] AS `company`,`radacct`.`radacctid`,`radacct`.`acctsessionid`,`radacct`.`acctuniqueid`,`radacct`.`username`,`radacct`.`groupname`,`radacct`.`realm`,`radacct`.`nasipaddress`,`radacct`.`nasportid`,`radacct`.`nasporttype`,`radacct`.`acctstarttime`,`radacct`.`acctstoptime`,`radacct`.`acctsessiontime`,`radacct`.`acctauthentic`,`radacct`.`connectinfo_start`,`radacct`.`connectinfo_stop`,`radacct`.`acctinputoctets`,`radacct`.`acctoutputoctets`,`radacct`.`calledstationid`,`radacct`.`callingstationid`,`radacct`.`acctterminatecause`,`radacct`.`servicetype`,`radacct`.`framedprotocol`,`radacct`.`framedipaddress`,`radacct`.`acctstartdelay`,`radacct`.`acctstopdelay`,`radacct`.`xascendsessionsvrkey` FROM `radacct` WHERE `username` = $requestJSON['username']";
	//$db->startTransaction();
	//$accountingArchiveResult = $db->rawQuery("INSERT INTO `accounting_archive` SELECT ? AS `company`,`radacct`.`radacctid`,`radacct`.`acctsessionid`,`radacct`.`acctuniqueid`,`radacct`.`username`,`radacct`.`groupname`,`radacct`.`realm`,`radacct`.`nasipaddress`,`radacct`.`nasportid`,`radacct`.`nasporttype`,`radacct`.`acctstarttime`,`radacct`.`acctstoptime`,`radacct`.`acctsessiontime`,`radacct`.`acctauthentic`,`radacct`.`connectinfo_start`,`radacct`.`connectinfo_stop`,`radacct`.`acctinputoctets`,`radacct`.`acctoutputoctets`,`radacct`.`calledstationid`,`radacct`.`callingstationid`,`radacct`.`acctterminatecause`,`radacct`.`servicetype`,`radacct`.`framedprotocol`,`radacct`.`framedipaddress`,`radacct`.`acctstartdelay`,`radacct`.`acctstopdelay`,`radacct`.`xascendsessionsvrkey` FROM `radacct` WHERE `username` = ?", Array('company' => $requestJSON['company'], 'username' => $requestJSON['username']));
	//$db->commit();
	//echo json_encode($accountingArchiveResult);
	//var_dump($db->getLastError());
/* 	if($accountingArchiveResult){
		$db->where('raddact', $requestJSON['username']);
		$db->delete('radacct');
		$db->rawQuery("DELETE checkTbl,replyTbl,groupTbl FROM `radcheck` AS `checkTbl` 
						INNER JOIN `radreply` AS `replyTbl` ON `checkTbl`.`username` = `replyTbl`.`username` 
						INNER JOIN `radusergroup` AS `groupTbl` ON `checkTbl`.`username` = `groupTbl`.`username` 
						WHERE `checkTbl`.`username` = ?", Array('username' => $requestJSON['username']));
		$db->commit();
		echo json_encode($accountingArchiveResult);
	}else{
		$db->rollback();
		echo json_encode($db->getLastError());
	} */
});

$app->delete('/api/delete/:id', function($id) use ($app, $db){
	/*
		This routing get company name and username at request body.
		First copy data which contains username == {request}.username from `radius`.`radacct`
		table	to `nmp_data`.`accounting` table.
	*/
  $json = json_decode($app->request->getBody(), true); // Get request body
	$username = $json['username']; // Username
	$company = $json['company']; // Company
	echo $username,$company,$id;

/*   // Check existing accounting record for $username.
  // In case exist records copy them from `radacct` table to `accounting_archive` table,
  // then delete from all tables where exist records with `username` == $username.
	$db->where('username', $username);
	if($accountingDataArray = $db->get('radacct') ){
		// Each element of $accountingDataArray is array, so make
    // foreach and merge new array with returned array as result
    // new associative array which first key is `company`
		foreach ($accountingDataArray as $accountingDataArrayItem) {
			$result[] = array('company' => $company) + $accountingDataArrayItem;
		}
		// Move to `accounting_archive` table
				$db->insertMulti('accounting_archive', $result);
				$db->where('id', $id);
				$nonActive = $db->getOne('active_clients');
        $db->insert('nonactive_clients', $nonActive);
        // Delete from `radacct` table
        $db->where('username', $username);
        $db->delete('radacct');
        // Delete from `active_clients` table
				$db->where('id', $id);
				$db->delete('active_clients');
        // Delete from `radcheck` table
        $db->where('username', $username);
        $db->delete('radcheck');
        // Delete from `radreply` table
        $db->where('username', $username);
        $db->delete('radreply');
        // Delete from `radusergroup` table
        $db->where('username', $username);
        $db->delete('radusergroup');
		}
		else{
			$db->where('id', $id);
			$nonActive = $db->getOne('active_clients');
			$db->insert('nonactive_clients', $nonActive);
			// Delete from `radacct` table
			$db->where('username', $username);
			$db->delete('radacct');
			// Delete from `active_clients` table
			$db->where('id', $id);
			$db->delete('active_clients');
			// Delete from `radcheck` table
			$db->where('username', $username);
			$db->delete('radcheck');
			// Delete from `radreply` table
			$db->where('username', $username);
			$db->delete('radreply');
			// Delete from `radusergroup` table
			$db->where('username', $username);
			$db->delete('radusergroup');
		} */
});

$app->post('/auth', function() use($app, $pdo){

	// $username = $app->request->post('userName');
	// $password = md5($app->request->post('userPassword'));
	$reqBody = $app->request->getBody();

	// $sql = $pdo->prepare("SELECT `username`, `password` FROM `auth` WHERE `username` = '$username' AND `password` = '$password'");
	// $sql->execute();
	//
	// if($sql->fetch()){
	// 	$token = sha1(date('U/s/m') + "Allice in mirror land");
	// 	$sql = $pdo->prepare("INSERT INTO `tokens` VALUES (NULL, '$username', '$token', NULL)");
	// 	$sql->execute();
	// 	echo "Correct";
	// }
	// else{
	// 	echo "Incorrect";
	// }
	var_dump($reqBody);

});

$app->run(); // Run application
?>
