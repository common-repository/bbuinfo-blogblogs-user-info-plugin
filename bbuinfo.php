<?php
/*
Plugin Name: BlogBlogs User Info
Plugin URI: http://www.rafaeldohms.com.br/dmsdev/index.php?go=projView&prj=69
Description: This plugin fetches information from BlogBlogs for every user who comments on your blog, if that user has an account at BlogBlogs.com.br, his info (pic, blogs, etc...) is show inside the comment window
Author: Rafael Dohms
Version: 1.0 "Knuckles"
Author URI: http://www.rafaeldohms.com.br/

@version $Id: bbuinfo.php 140 2007-07-07 15:04:15Z Dooms $
*/

/**
 * BlogBlogs User Info
 * 
 * @author Rafael Dohms <rafael@rafaeldohms.com.br>
 * @package BBUInfo
 *
 */
class BBUInfo {

	private static $wpdb;
	private static $info;
	private static $comment_ID;
	private static $version = '1.0';
	private static $url_options_page;
	private static $init_erro = 0;
	private static $warn = array();
	
	const DO_TABLE_CREATE = 2;
	const SUCESS = 1;
	const ERROR = 0;
	const URL_BBKEY = "http://www.blogblogs.com.br/developers";

	public function __construct(){

		BBUInfo::init();
	}

	/**
	 * Inicializa o script, chamada sempre ao inicio das paginas do WP
	 */
	public static function init(){

		BBUInfo::loadVars();
		BBUInfo::loadPlugin();
		BBUInfo::checkAPIKey();
		
		/** Funcao que carrega o menu */
		add_action('admin_menu',array('BBUInfo','loadMenu'));
		
		//Se não houver nenhum erro de inicializacao, registrar filtro dos comentarios
		if (!BBUInfo::$init_erro){
			/** Funcao que manipula comentario */
			add_filter('get_comment_ID', array('BBUInfo','appendData'));
		}
		

	}
	
	/**
	 * Insere no objeto estatico as variaves do WP
	 * necessarias para funcionamento do plugin
	 */
	public static function loadVars(){
		global $wpdb;

		BBUInfo::$wpdb = $wpdb;
		BBUInfo::$url_options_page = "options-general.php?page=".__FILE__;
	}
	
	/**
	 * Verifica o cadastro da chave da API BlogBlogs, e chama alerta
	 * para avisar usuario
	 */
	public static function checkAPIKey(){
		$apiKey = get_option('bbuinfo_APIKEY'); 
		if ( $apiKey == "" || is_null($apiKey) ){
			
			BBUInfo::$warn['texto'] = sprintf('Voc&ecirc; deve inserir sua chave da <a href="%1$s" target="_blank">API do BlogBlogs</a> no <a href="%2$s">menu de op&ccedil;&otilde;es</a> antes que ele possa funcionar',BBUInfo::URL_BBKEY, BBUInfo::$url_options_page);
			BBUInfo::$warn['titulo'] = "O BBUInfo n&atilde;o esta funcionando!";
			
			add_action('admin_footer',array("BBUInfo","showWarning"));
			BBUInfo::$init_erro = 1;
		}else{
			remove_action('admin_footer',array("BBUInfo","showWarning"));
		}
	}
	
	/**
 	* Executada ao instalar o script.
 	* Cria no banco as opcoes do plugin
 	* 
 	* @static 
 	* @return void
 	*/
	public static function install(){

		BBUInfo::checkVars();

		//Adicionar campos de opcoes
		add_option('bbuinfo_APIKEY',''); //BlogBlogs APIKEY para o UserInfo Plugin
		add_option('bbuinfo_method','JS'); //Modo de resgate de dados para o UserInfo Plugin
		add_option('bbuinfo_cacheTime','120'); //Tempo de vida do cache em minutos do bbUinfo

		//Verificar se alguma tabela já existe
		$table_exists = BBUInfo::$wpdb->get_row("SHOW TABLES LIKE '%bbuinfo%'");
				
		if (!is_null($table_exists)){
			//Rodar migracao "legado" da base
			$migration = BBUInfo::migrateToLatest();	
		}
		
		if ($migration == BBUInfo::DO_TABLE_CREATE || is_null($table_exists)){
			//Criar base de dados de cache
			BBUInfo::$wpdb->query("
				CREATE TABLE IF NOT EXISTS ".BBUInfo::$wpdb->prefix."bbuinfo_cache (
	  				usremail VARCHAR(60) NOT NULL,
	  				xmldata TEXT NULL,
	  				cachedon DATETIME NULL,
	  			PRIMARY KEY(usremail));
			");	
		}
		elseif ($migration == BBUInfo::ERROR){
			
			deactivate_plugins(__FILE__);
			
			$errorMsg  = "<h1><b>Erro encontrado! Plugin desativado automaticamente!</b><br></h1>";
			$errorMsg .= "Ao instalar o plugin BBUInfo, foi encontrado um erro na cria&ccedil;&atilde;0 da base de dados do cache. Por isso o plugin foi <b>desativado</b> e voc&ecirc; pode continuar utilizando o WordPress normalmente.<br>";
			$errorMsg .= "<br><a href='plugins.php'>Clique aqui para retornar</a><br><br>";
			
			wp_die($errorMsg);			
			
//			BBUInfo::$warn['texto'] = sprintf('O BBUInfo n&atilde;o esta instalado, houve um erro ao criar a base de dados, desative e ative novamente o plugin.');
//			BBUInfo::$warn['titulo'] = "Erro ao criar base de dados!";
//			
//			add_action('admin_footer',array("BBUInfo","showWarning"));
		}
		


	}

	/**
 	* Inicializa o script, buscando as opcoes e preparando a base
 	*/
	public static function loadPlugin(){

		$blogUrl = get_option('siteurl');

		BBUInfo::$info['perfil'] = "";
		BBUInfo::$info['blogUrl'] = $blogUrl;
		BBUInfo::$info['plugin_path'] = BBUInfo::$info['blogUrl']."/wp-content/plugins/".dirname(plugin_basename(__FILE__));
		BBUInfo::$info['plugin_fpath'] = dirname(__FILE__);
		BBUInfo::$info['url'] = "http://api.blogblogs.com.br/api/rest/userinfo";
		BBUInfo::$info['proxy'] = $blogUrl."/wp-content/plugins/bbuinfo/bbuinfo-proxy.php";
		BBUInfo::$info['APIKEY'] = get_option('bbuinfo_APIKEY');
		BBUInfo::$info['method'] = get_option('bbuinfo_method'); // JS|PHP

		//Adicionar hook para incluir arquivo javascript
		if (BBUInfo::$info['method'] == 'JS'){
			add_action('wp_print_scripts',array("BBUInfo","loadJS"));
		}

		//Adicionar CSS
		add_action('wp_print_scripts',array("BBUInfo","loadCSS"));

	}

	/**
 	* Adiciona ao WP a pagina de gerenciamento das opcoes
 	*/
	public static function loadMenu(){
		add_options_page('BBUinfo - Gerenciamento','BBUinfo',10,__FILE__,array("BBUInfo","execOptions"));
	}

	/**
 	* Mostra pagina de gerencia e executa a gravacao dos dados
 	*/
	public static function execOptions(){

		BBUInfo::checkVars();

		$errors = array();

		if (count($_POST) > 0){
			//Validar e gravar opcoes
			if ($_POST['bbuinfo_APIKEY'] != ''){
				update_option('bbuinfo_APIKEY',$_POST['bbuinfo_APIKEY']);
				//Forçar nova verificação da API
				BBUInfo::checkAPIKey();
			}else{
				$errors[] = "A APIKEY &eacute; obrigat&oacute;ria para o uso do plugin.";
			}

			if ($_POST['bbuinfo_method'] != ''){
				update_option('bbuinfo_method',$_POST['bbuinfo_method']);
			}else{
				$errors[] = "Escolha um modo de obter os dados.";
			}

			//Redirecionar e avisar que salvou
			$updated = true;

		}

		//Pegar template
		//Usando funcoes do WP
		$admTplObj = new FileReader(BBUInfo::$info['plugin_fpath']."/admin_tpl.htm");
		$admTpl = $admTplObj->read($admTplObj->_length);

		//Atribuir valores
		$admTpl = str_replace("{bbuinfo_APIKEY}",get_option('bbuinfo_APIKEY'),$admTpl);
		$admTpl = str_replace("{PATH}",BBUInfo::$info['plugin_path'],$admTpl);
		
		if (get_option('bbuinfo_method') == 'JS'){
			$admTpl = str_replace('{js_check}','checked="checked"',$admTpl);
			$admTpl = str_replace('{php_check}','',$admTpl);
		}else{
			$admTpl = str_replace('{js_check}','',$admTpl);
			$admTpl = str_replace('{php_check}','checked',$admTpl);
		}
		
		if (count($errors) > 0){
			$admTpl = str_replace('{ERROS}','<p id="">'.implode('<br>',$errors).'</p>',$admTpl);
		}else{
			$admTpl = str_replace('{ERROS}','',$admTpl);
		}
		
		if ($updated){
			$updtString = '<div id="message" class="updated fade"><p><strong>Dados atualizados!</strong></p></div>';
			$admTpl = str_replace('{UPDATED}',$updtString,$admTpl);
		}else{
			$admTpl = str_replace('{UPDATED}','',$admTpl);
		}
		
		//Log de erros
		$admTpl = str_replace('{LOG}',"Em breve...",$admTpl);
		
		//Estatisticas
		$statsReplace['{STATS}'] = "Em breve...";
		$statsReplace['{CACHED_ITENS}'] = "X";
		$admTpl = strtr($admTpl,$statsReplace);
		
		echo $admTpl;
	}

	/**
	* Pega o email do comentario e convoca a obtencao de dados
 	*
 	* @param int $comment_ID codigo do comentario
 	* @return int ID do comentario
 	*/
	public static function appendData($comment_ID) {

		BBUInfo::checkVars();

		BBUInfo::$comment_ID = $comment_ID;

		//Habilitar filtro que ira mostrar dados (SELF HOOK)
		add_filter('get_comment_text', array("BBUInfo",'showInfo'));

		//Buscar email do author
		$auth_email = BBUInfo::$wpdb->get_var("SELECT comment_author_email FROM `".BBUInfo::$wpdb->comments."` WHERE `comment_ID` = '$comment_ID'");

		//Disable next filter if no email found
		if ($auth_email == ''){
			remove_filter('get_comment_text', array("BBUInfo","showInfo"));
			BBUInfo::$info['debug'] = "Erro ao pegar email do coment&aacute;rio";
			//add_filter('get_comment_text', 'bbUI_debug');
		}else{

			if (BBUInfo::$info['method'] == 'PHP'){
				//Rodar funcao que busca por PHP
				BBUInfo::$info['perfil'] = BBUInfo::phpGetInfo($auth_email);
			}else{
				//Rodar JS
				$js  = '<div id="bbuinfo_container_'.BBUInfo::$comment_ID.'">';
				$js .= '<script type="text/javascript" id="bbuinfo_cmt_'.BBUInfo::$comment_ID.'">';
				$js .= '  BB_key = "'.BBUInfo::$info['APIKEY'].'";';
				$js .= '  BB_url = "'.BBUInfo::$info['url'].'";';
				$js .= '  BB_proxy = "'.BBUInfo::$info['proxy'].'";';
				$js .= '  BB_uniqueID = "'.BBUInfo::$comment_ID.'";';
				$js .= '  var BBAPI_'.BBUInfo::$comment_ID.' = new BBAPI("'.$auth_email.'");';
				$js .= '</script>';
				$js .= '</div>';

				BBUInfo::$info['perfil'] = $js;
			}

		}
		//Retornar ID recebido para nao quebrar outros plug-ins
		return  $comment_ID;
	}

	/**
 	* Conecta no BlogBlogs e resgata o codigo do XML
 	*
 	* @param string $url URL da API
 	* @return string XML source
 	*/
	public static function getXML($url){
		BBUInfo::checkVars();

		if (ini_get("allow_url_fopen")){
			return file_get_contents($url);
		}else{
			//FSCKOPEN or FOPEN Wrappers
			require_once( ABSPATH . 'wp-includes/class-snoopy.php');//Obtida pelo WP

			$sn = new Snoopy();
			$sn->fetch($url);
			$xml = $sn->results;
			unset($sn);
			return $xml;
		}
	}

	/**
 	* Chama a busca de dados e monta o template
 	*
 	* @param string $auth_email email do autor
 	* @return string HTML
 	*/
	public static function phpGetInfo($auth_email){

		BBUInfo::checkVars();

		//Buscar dados do BlogBlogs API
		$bbXmlString = BBUInfo::getXML(BBUInfo::$info['proxy']."?url=".BBUInfo::$info['url']."&key=".BBUInfo::$info['APIKEY']."&email=".$auth_email);
		$bbXml = simplexml_load_string($bbXmlString);

		if (!$bbXml){
			//Erro ao conectar com BlogBlogs
			remove_filter('get_comment_text', array("BBUInfo","showInfo"));
			BBUInfo::$info['debug'] = "Erro ao buscar no BlogBlogs";
			//add_filter('get_comment_text', 'bbUI_debug');
		}else{
			//Verificar se possui conta no BB
			if ($bbXml->document->result->error != ''){
				remove_filter('get_comment_text', array("BBUInfo","showInfo"));
				BBUInfo::$info['debug'] = "Perfil BB nï¿½o encontrado para este e-mail";
				//add_filter('get_comment_text', 'bbUI_debug');
			}else{
				//Tratar dados
				$rank = ($bbXml->document->item->weblog->rank > 0)? $bbXml->document->item->weblog->rank:"0";

				//Montar template
				$template .= '<div class="bb_user_info" id="bbui_div_'.BBUInfo::$comment_ID.'">';
				$template .= ' <div style="display: block;" id="bbui_cnt_'.BBUInfo::$comment_ID.'" align="center">';
				$template .= '   <table class="bbui_table" id="bbui_table_'.BBUInfo::$comment_ID.'" align="center">';
				$template .= '     <tr>';
				$template .= '       <td class="bbui_img" rowspan="2"><a title="'.$bbXml->document->result->fullname.'" href="'.$bbXml->document->result->profilepage.'" id="link_img'.BBUInfo::$comment_ID.'"><img src="'.$bbXml->document->result->thumbnailpicture.'" id="img'.BBUInfo::$comment_ID.'"></a></td>';
				$template .= '       <td class="bbui_nome" colspan="4"><a title="'.$bbXml->document->result->fullname.'" href="'.$bbXml->document->result->profilepage.'" id="link_name'.BBUInfo::$comment_ID.'">'.$bbXml->document->result->fullname.'</a></td>';
				$template .= '     </tr>';
				$template .= '     <tr>';
				$template .= '       <td class="bbui_blog"><a title="'.$bbXml->document->result->fullname.' tem $bbXml->document->result->blogs blogs" href="'.$bbXml->document->result->profilepage.'" id="link_blogs'.BBUInfo::$comment_ID.'"><img src="http://assets5.blogblogs.com.br/public/bb/images/mini/page_world.png">'.$bbXml->document->result->blogs.' blogs</a></td>';
				$template .= '       <td class="bbui_rank"><a title="Rank do Blog" href="'.$bbXml->document->result->profilepage.'" id="link_rank'.BBUInfo::$comment_ID.'"><img src="http://assets4.blogblogs.com.br/public/bb/images/mini/award_star_gold_1.png">'.$rank.'</a></td>';
				$template .= '       <td class="bbui_feed"><a title="'.$bbXml->document->result->favorites.' Favoritos" href="'.$bbXml->document->result->profilepage.'" id="link_feeds'.BBUInfo::$comment_ID.'"><img src="http://assets2.blogblogs.com.br/public/bb/images/mini/heart_add.png">'.$bbXml->document->result->favorites.'</a></td>';
				$template .= '       <td class="bbui_fas"><a title="'.$bbXml->document->result->fullname.' tem '.$bbXml->document->result->fans.' f&atilde;ns" href="'.$bbXml->document->result->profilepage.'" id="link_fas'.BBUInfo::$comment_ID.'"><img src="http://assets1.blogblogs.com.br/public/bb/images/mini/hearts.png">'.$bbXml->document->result->fans.'</a></td>';
				$template .= '     </tr>';
				$template .= '   </table>';
				$template .= ' </div>';
				$template .= '</div>';


				return $template;
			}
		}
	}

	/**
 	* Mostra o perfil da pessoa junto ao comentario
 	*
 	* @param string $cnt comentario 
 	* @return string html completo
 	*/
	public static function showInfo($cnt){

		$cnt = $cnt.BBUInfo::$info['perfil'];
		BBUInfo::$info['perfil'] = '';

		return $cnt;
	}

	/**
 	* Insere chamada ao script Javascript para chamadas AJAX
 	*/
	public static function loadJS(){

		echo "<script type='text/javascript' src='".BBUInfo::$info['plugin_path']."/js/bbapi.js' ></script>";
	}

	/**
 	* Le arquivo CSS e cola na pagina
 	*/
	public static function loadCSS(){

		//Carregar CSS
		$cssFile = new FileReader(BBUInfo::$info['plugin_fpath']."/bbuinfo.css");
		$css = $cssFile->read($cssFile->_length);
		//Substituir path de imagens
		$css = str_replace(":PLUGIN_PATH:",BBUInfo::$info['plugin_path']."/",$css);
		echo $css;

	}

	/**
 	* Debug function
	*
	* @param unknown_type $cnt
	* @return unknown
 	*/
	public static function debug($cnt){

		$cnt .= "<br><br>";
		$cnt .= "Debug Ligado, info:<br>";
		$cnt .= BBUInfo::$info['debug'];
		//$cnt .= "<br><br>".implode("<br>",$bbUInfo['erros']);

		remove_filter('get_comment_text', array("BBUInfo","debug"));
		BBUInfo::$info['debug'] = "";

		return $cnt;
	}

	/**
	 * Armazena rotinas de migração de versões anteriores da base
	 * de dados do plugin, para a versao nova
	 */
	public static function migrateToLatest(){
		
		$dbversion = get_option('bbuinfo_dbversion');
	
		switch($dbversion){
			case false: //versao anterior a 1.0
			case '':
				
				//Verificar se existe a tabela bbuinfo_cache
				$table_exists = BBUInfo::$wpdb->get_row("SHOW TABLES LIKE 'bbuinfo_cache'");
				
				if (!is_null($table_exists)){
					BBUInfo::$wpdb->query("RENAME TABLE bbuinfo_cache TO ".BBUInfo::$wpdb->prefix."bbuinfo_cache");
				}
				
				//Gravar versao atual da base
				update_option('bbuinfo_dbversion',BBUInfo::$version);
				$migrationOK = true;
				break;
		}
		
		
		//Verificar tabelas
		$table_exists = BBUInfo::$wpdb->get_row("SHOW TABLES LIKE '".BBUInfo::$wpdb->prefix."bbuinfo_cache'");
		if (!is_null($table_exists)){
			return BBUInfo::DO_TABLE_CREATE;
		}elseif($migrationOK){
			return BBUInfo::SUCESS;
		}else{
			return BBUInfo::ERROR;
		}
	}
	
	/**
	 * Verifica se as variaveis necessarias estao setadas no objeto estatico
	 */
	private static function checkVars(){
		if (!is_object(BBUInfo::$wpdb) ){
			BBUInfo::loadVars();
		}
	}
	/**
	 * Emite um alerta indicando que deve ser inserida a chaved a API
	 * do Blogblogs
	 */
	public static function showWarning() {
		$returnDiv  = "";
		$returnDiv .= "<div id='bbuinfo-warning' class='updated fade-ff0000'><p>";
		$returnDiv .= "<strong>".BBUInfo::$warn['titulo']."</strong>&nbsp;";
		$returnDiv .= BBUInfo::$warn['texto'];
		$returnDiv .= "</p></div>";
		$returnDiv .= "<style type='text/css'>";
		$returnDiv .= "    #adminmenu { margin-bottom: 5em; }";
		$returnDiv .= "    #bbuinfo-warning { position: absolute; top: 7em; }";
		$returnDiv .= "</style>";
		
		BBUInfo::$warn = array();
		remove_action('admin_footer',array("BBUInfo","showWarning"));
		
		echo $returnDiv;
	}
}


/**
 *  Adicionar HOOKs do WordPress
 */

/** Funcao de instalacao */
register_activation_hook('bbuinfo/bbuinfo.php',array('BBUInfo','install'));
/** Funcao de inicializacao */
add_filter('init', array('BBUInfo','init'));



?>