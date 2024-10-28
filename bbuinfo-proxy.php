<?php 

//sleep(5);
ini_set("allow_url_fopen",0);
/**
 * Este arquivo recebe a requisição do XML e verifica se existe um cache destes dados
 * na base atualmente, evitando uma chamada ao API do BlogBlogs
 * Ele funciona como cache e como proxy, pois permite por AJAX acessar páginas de 
 * outros dominios
 *
 * @author Dooms
 * @package defaultPackage
 */

function bbUIproxy_getXML($url){

	if (ini_get("allow_url_fopen")){
		return file_get_contents($url);
	}else{
		//FSCKOPEN or FOPEN Wrappers
		$sn = new Snoopy();
		$sn->fetch($url);
		$xml = $sn->results;
		unset($sn);
		return $xml;
	}
}

include_once('../../../wp-config.php');
include_once( ABSPATH . 'wp-includes/wp-db.php');
require_once( ABSPATH . 'wp-includes/class-snoopy.php');

$bbui_usrEmail = $_GET['email'];
$bbui_cacheTime = get_option('bbuinfo_cacheTime')*60;
$bbui_url = $_GET['url']."?key=".$_GET['key']."&email=".$_GET['email'];

$bbui_tablename = $wpdb->prefix."bbuinfo_cache";


//Buscar na base o último cache desta pessoa
$bbui_lastcache = $wpdb->get_var("SELECT cachedon FROM ".$bbui_tablename." WHERE usremail = '".$bbui_usrEmail."'");
if (!is_null($bbui_lastcache)){
	$bbui_tElapsed = strtotime($lastcache) - time();
}else{ //Sem cache.. forçar cacheamento
	$bbui_tElapsed = $bbui_cacheTime+5;
}
//Dumpcache
$bbui_cacheTime = 0;
$bbui_tElapsed = 15;
if ($bbui_tElapsed > $bbui_cacheTime){
	//Buscar da API
	$bbui_xmldata = bbUIproxy_getXML($bbui_url);

	//Trocar THUMB
	$bbui_xmldata = str_replace("_64.","_48.",$bbui_xmldata);

	//Validar o retorno
	$bbui_xmlObj = simplexml_load_string($bbui_xmldata);
	//Verificar retorno válido
	if (!$bbui_xmlObj){
		define(VALID,false);
		$error_string = "Retorno inválido";
	}else{
		define(VALID,true);

		//Verificar erro enviado pela API do BB
		$error = $bbui_xmlObj->xpath("/bbapi/document/result/error");
		if (count($error) > 0){
			define(NO_ERROR,false);
			$error_string = (string) $error[0];
		}else{
			define(NO_ERROR,true);
		}
	}

	if (!VALID || !NO_ERROR){
		$bbui_usrNotFound = 1;
		$return['document']['result']['error'] = $error_string;
		$bbui_jsondata = json_encode($return);
	}else{

		//fazer conversão para JSON
		if (function_exists('json_encode')){
			$bbui_jsondata = json_encode($bbui_xmlObj);
		}

		//Remover cache antigo
		$wpdb->query("DELETE FROM ".$bbui_tablename." WHERE usremail = '".$bbui_usrEmail."'");

		//Fazer cache
		$wpdb->query("INSERT INTO ".$bbui_tablename." (usremail, xmldata, cachedon)
				  VALUES(
				  	'".$bbui_usrEmail."',
				  	'".mysql_real_escape_string($bbui_jsondata)."',
				  	'".date('Y-m-d H:i:s')."'
				  )");
		$bbui_rMsg = "Dados obtidos da API e cacheados";
	}
}else{
	//Buscar da base e retornar
	$bbui_jsondata = $wpdb->get_var("SELECT xmldata FROM ".$bbui_tablename." WHERE usremail = '".$bbui_usrEmail."'");
	$bbui_rMsg = "Dados obtidos do cache";
}

//Buscar dados do BlogBlogs API
header("Content-type:application/xml; charset=utf-8");
echo $bbui_jsondata;

echo "/* bbUInfo by Rafael Dohms: ".$bbui_rMsg." */";
?>