if(window.wgUserName || (!window.wgUserName && document.cookie.indexOf('ContentWarningApproved=1') == -1 )){
	window.wgNeedContentWarning = true;
	document.getElementsByTagName("body")[0].className += " ContentWarning";
} else {
	window.wgNeedContentWarning = false;
}