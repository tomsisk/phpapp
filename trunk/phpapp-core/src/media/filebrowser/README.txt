The upload folder and cache folder need write permission too function properly

========================
CONFIG.PHP
========================
Edit the config.php file to specify your upload folder, default language and default view layout.


========================
TinyMCE javascript
========================

Add TinyMCE setting:
--------------------------------------------------------------------------
file_browser_callback: "filebrowser",


Add the following function to your javascript:
--------------------------------------------------------------------------
function filebrowser(field_name, url, type, win) {
		
	fileBrowserURL = "/path/to/file/browser/index.php?filter=" + type;
			
	tinyMCE.activeEditor.windowManager.open({
		title: "PDW File Browser",
		url: fileBrowserURL,
		width: 950,
		height: 650,
		inline: 0,
		maximizable: 1,
		close_previous: 0
	},{
		window : win,
		input : field_name
	});		
}