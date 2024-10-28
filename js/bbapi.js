/* BBAPI
* $Id: bbapi.js 123 2007-07-05 01:57:36Z Dooms $
*/

/* Funcoes auxiliares
 ******************************************************************************/
$ = function(id) {
  return document.getElementById(id);
}

/* Classe Ajax()
 ******************************************************************************/
bbuinfoAjax = function(url, options) {
  var self = this;
  self.options = options || {};

  self.init = function(url) {
    self.transport = self.getTransport();
    self.postBody = self.options.postBody || '';
    self.method = self.options.method || 'post';
    self.onComplete = self.options.onComplete || null;
    self.request(url);
  }

  self.request = function(url) {
    self.transport.open(self.method, url, true);
    self.transport.onreadystatechange = self.onStateChange;
    if (self.method == 'post') {
      self.transport.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
      if (self.transport.overrideMimeType) self.transport.setRequestHeader('Connection', 'close');
    }
    self.transport.send(self.postBody);
  }

  self.onStateChange = function() {
    if (self.transport.readyState == 4 && self.transport.status == 200) {
      if (self.onComplete) setTimeout(function(){self.onComplete(self.transport);}, 10);
      self.transport.onreadystatechange = function(){};
    }
  }

  self.getTransport = function() {
    if (window.ActiveXObject) return new ActiveXObject('Microsoft.XMLHTTP');
    else if (window.XMLHttpRequest) return new XMLHttpRequest();
    else return false;
  }

  self.init(url, options);
};

/* Classe BBAPI()
 ******************************************************************************/
BBAPI = function(email) {
  var self = this;
  self.key = BB_key ? BB_key : '';
  self.UID = BB_uniqueID ? BB_uniqueID : '';
  self.url = BB_url ? BB_url : 'http://api.blogblogs.com.br/api/rest/userinfo';
  self.baseurl = BB_proxy ? BB_proxy+'?url='+BB_url+'&' : self.url+'?';
  //self.username = username ? username : '';
  self.email = email ? email : '';

  self.init = function() {
	var mainDiv = document.createElement('DIV');
	mainDiv.id = "bbui_div_"+self.UID;
	mainDiv.className = "bb_user_info";
	
	var loadingSpan = document.createElement('DIV');
	loadingSpan.className = "bbui_loading";
	loadingSpan.id = "loading_"+self.UID;
	loadingSpan.innerHTML = "&nbsp;";
	mainDiv.appendChild(loadingSpan);

	document.getElementById('bbuinfo_container_'+self.UID).appendChild(mainDiv);
   	var url = self.baseurl+'key='+ self.key +'&email=' + self.email;

    new bbuinfoAjax(url, {onComplete:self.onComplete});
  }

  self.onComplete = function(response) {
    var xml = response.responseXML || false;
    var text = response.responseText || false;
    if (!xml || !text) {
		document.getElementById("bbuinfo_container_"+self.UID).style.display = 'none';
		return;
	}
	
    if (xml.getElementsByTagName('error').length > 0){
		//Erro... abortar
		document.getElementById("bbuinfo_container_"+self.UID).style.display = 'none';
		return false;
	}
	
    var result = xml.getElementsByTagName('result')[0];

    var username         = result.getElementsByTagName('username')[0];
    var firstname        = result.getElementsByTagName('firstname')[0];
    var lastname         = result.getElementsByTagName('lastname')[0];
    var fullname         = result.getElementsByTagName('fullname')[0];
    var thumbnailpicture = result.getElementsByTagName('thumbnailpicture')[0];
    var profilepage      = result.getElementsByTagName('profilepage')[0];
    var blogs            = result.getElementsByTagName('blogs')[0];
    var favorites        = result.getElementsByTagName('favorites')[0];
    var fans             = result.getElementsByTagName('fans')[0];
	var blogrank         = xml.getElementsByTagName('rank')[0];
	var faves        	 = result.getElementsByTagName('favorites')[0];

	
	if (profilepage != undefined){
		var profile = profilepage.firstChild.data;
	}else{
		var profile = "";
	}
	
	if (fullname != undefined){
		var fullname = fullname.firstChild.data;
	}else{
		var fullname = "";
	}
	
	if (blogrank != undefined){
		var blogrank = blogrank.firstChild.data;
	}else{
		var blogrank = "0";
	}
	
	if (faves != undefined){
		var faves = faves.firstChild.data;
	}else{
		var faves = "0";
	}
	
	if (fans != undefined){
		var fans = fans.firstChild.data;
	}else{
		var fans = "0";
	}
	
	if (thumbnailpicture != undefined){
		var thumbnailpicture = thumbnailpicture.firstChild.data;
	}else{
		var thumbnailpicture = "";
	}
	
	if (blogs != undefined){
		var blogs = blogs.firstChild.data;
	}else{
		var blogs = "0";
	}
	

	var alignDiv = document.createElement('DIV');
	alignDiv.id = 'bbui_cnt_'+self.UID;
	alignDiv.align = 'center';
	
	var mainTable = document.createElement('TABLE');
	mainTable.id = "bbui_table_"+self.UID;
	mainTable.className = "bbui_table";
	mainTable.cellpadding = 0;
	mainTable.cellspacing = 0;
	mainTable.align = "center";
	alignDiv.appendChild(mainTable);
	
	var tbody = document.createElement('TBODY');
	mainTable.appendChild(tbody);
	
	//FIRST ROW
	
	var firstRow = document.createElement('TR');
	tbody.appendChild(firstRow);
	
	var imgTD = document.createElement('TD');
	imgTD.rowSpan = "2";
	imgTD.className = "bbui_img";
	firstRow.appendChild(imgTD);
	
	var imgA = document.createElement('A');
	imgA.id = "link_img"+self.UID;
	imgA.src = profile;
	imgA.title = fullname;
	imgTD.appendChild(imgA);
	
	var imgImg = document.createElement('IMG');
	imgImg.id = "img"+self.UID;
	imgImg.src = thumbnailpicture;
	imgA.appendChild(imgImg);
	
	var nomeTD = document.createElement('TD');
	nomeTD.colSpan = "4";
	nomeTD.className = "bbui_nome";
	firstRow.appendChild(nomeTD);
	
	var nomeA = document.createElement('A');
	nomeA.id = "link_name"+self.UID;
	nomeA.href = profile;
	nomeA.innerHTML = fullname;
	nomeA.title = fullname;
	nomeTD.appendChild(nomeA);
	
	//SECOND ROW
	
	var secondRow = document.createElement('TR');
	tbody.appendChild(secondRow);
	
	var blogTD = document.createElement('TD');
	blogTD.className = "bbui_blog";
	secondRow.appendChild(blogTD);
	timg = document.createElement('IMG');
	timg.src = "http://assets5.blogblogs.com.br/public/bb/images/mini/page_world.png";
	var blogA = document.createElement('A');
	blogA.id = "link_blogs"+self.UID;
	blogA.href = profile;
	blogA.title = fullname+' tem '+blogs+' blogs';
	blogA.appendChild(timg);
	blogA.innerHTML += blogs + " blogs";
	blogTD.appendChild(blogA);
	
	var rankTD = document.createElement('TD');
	rankTD.className = "bbui_rank";
	secondRow.appendChild(rankTD);
	timg = document.createElement('IMG');
	timg.src = "http://assets4.blogblogs.com.br/public/bb/images/mini/award_star_gold_1.png";
	var rankA = document.createElement('A');
	rankA.id = "link_rank"+self.UID;
	rankA.appendChild(timg);
	rankA.href = profile;
	rankA.innerHTML += blogrank;
	rankA.title = "Rank do Blog";
	rankTD.appendChild(rankA);
	
	var feedTD = document.createElement('TD');
	feedTD.className = "bbui_feed";
	secondRow.appendChild(feedTD);
	timg = document.createElement('IMG');
	timg.src = "http://assets2.blogblogs.com.br/public/bb/images/mini/heart_add.png";
	var feedA = document.createElement('A');
	feedA.id = "link_feeds"+self.UID;
	feedA.appendChild(timg);
	feedA.href = profile;
	feedA.innerHTML += faves;
	feedA.title = faves+" Favoritos"
	feedTD.appendChild(feedA);
	
	var fasTD = document.createElement('TD');
	fasTD.className = "bbui_fas";
	secondRow.appendChild(fasTD);
	timg = document.createElement('IMG');
	timg.src = "http://assets1.blogblogs.com.br/public/bb/images/mini/hearts.png";
	var fasA = document.createElement('A');
	fasA.id = "link_fas"+self.UID;
	fasA.href = profile;
	fasA.title = fullname+' tem '+fans+' f&atilde;ns';
	fasA.appendChild(timg);
	fasA.innerHTML += fans;
	fasTD.appendChild(fasA);

	//remover loading
	$('loading_'+self.UID).style.display = 'none';
	
	//mostrar dados
	document.getElementById("bbui_div_"+self.UID).appendChild(alignDiv);

  }

  self.init();
}