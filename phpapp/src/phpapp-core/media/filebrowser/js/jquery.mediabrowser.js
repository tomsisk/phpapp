/*
PDW File Browser
Date: May 9, 2010
Url: http://www.neele.name

Copyright (c) 2010 Guido Neele

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/
(function($){

	$.MediaBrowser = {
	
		clipboard: new Array(),
		copyMethod: '',
		ctrlKeyPressed: false,
		currentFolder: '',
		currentView: '',
		dragMode: false,
		dragObj: null,
		dragID: '',
		lastSelectedItem:null,
		searchDefaultValue: '',
		shiftKeyPressed: false,
		tableHeadersFixed: 0,
		timeout: null,
		uploadPath: null,
		rootName: null,
		baseUrl: null,
		
		init: function(){
			
			// Add treeview
			$('ul.treeview').TreeView();
			
			// Calculate widths and heights of treeview, file viewer and footer
			$.MediaBrowser.resizeWindow();
			
			// Make treeview resizable
			$.MediaBrowser.setResizeHandlers();
			
			// If a filter is specified then hide files with a wrong file types
			$.MediaBrowser.filter();
			
			// Set currently selected folder and view
			$.MediaBrowser.setCurrentFolder($('input#currentfolder').val());
			$.MediaBrowser.currentView = $('input#currentview').val();
			
			//Stripe table if view is Details
			$('div#files table#details tbody tr:odd').addClass('odd');
			
			// *** Navbar ***//
			
			// Style navbar children
			$('div#navbar ul li:has(ul) > a')
				// Add class 'children' for the arrow to show up
				.addClass('children')
				// Add spacer to link for arrow to show up
				.append('<span class="options"><img src="img/spacer.gif" width="20" /></span>')
				.click(function(){
					$(this).next().toggle().end().toggleClass('selected');	
					return false;
				})
			.end();
			
			// select all <li> with children
			$('div#navbar ul li:has(ul)').hover(function(){},function(event){
				$('a.selected', this).removeClass('selected').next().hide();
				event.preventDefault(); //Don't follow link
			});
			
			// *** Search ***//
			
			// Clear search form on first click
			$('div#searchbar')
				.find('input#search').click(function(){
					if ( this.value == this.defaultValue ){	
						this.value = '';	
					}
				})
			.end();
			
			// Start searching while typing
			$('input#search').keyup(function(event){
				var keycode = event.keyCode;
				
				if (!(keycode == 9 || keycode == 13 || keycode == 16 || keycode == 17 || keycode == 18 || keycode == 38 || keycode == 40 || keycode == 224))
				{
					$.MediaBrowser.searchFor($(this).val());
				}
				
				event.preventDefault();
			});
			
			// Set searchbar value to default if input is empty
			$.MediaBrowser.searchDefaultValue = $('input#search').val();
			
			$('input#search').blur(function(){
				if($('input#search').val() == ""){
					$('input#search').val($.MediaBrowser.searchDefaultValue);
				}
			});
			
			// *** Events *** //
			// Check if ctrl key or shift key is pressed
			$(document).keydown(function(event) {
				if(event.ctrlKey || event.metaKey){
					$.MediaBrowser.ctrlKeyPressed = true;
				}
				if(event.shiftKey){
					$.MediaBrowser.shiftKeyPressed = true;	
				}
			}); 
			
			$(document).keyup(function(event) {
				$.MediaBrowser.ctrlKeyPressed = false;
				$.MediaBrowser.shiftKeyPressed = false;
			}); 
			
			// If filter has changed then apply new filtering
			$('select#filters').change(function(){
				$.MediaBrowser.filter();								
			});
			
			// Folder events					
			$('div#files ul li a.folder, div#files table tr.folder').live('dblclick', function(event){
				$.MediaBrowser.loadFolder($(this).attr('href'));	
				event.preventDefault(); //Don't follow link
			});

			$('div#files ul li a.folder, div#files table tr.folder').live('click', function(event){
				if (event.button != 0) return true; //If right click then return true
				$.MediaBrowser.selectFileOrFolder(this,$(this).attr('href'),'folder'); //Select clicked folder
				event.preventDefault(); //Don't follow link
			});
			
			// File events
			$('div#files ul li a.file, div#files table tr.file').live('click', function(event){
				if (event.button != 0) return true; //If right click then return true
				$.MediaBrowser.selectFileOrFolder(this,$(this).attr('href'),'file'); //Select clicked file
				event.preventDefault(); //Don't follow link
			});
			
			// Image events
			$('div#files ul li a.image, div#files table tr.image').live('click', function(event){
				if (event.button != 0) return true; //If right click then return true
				$.MediaBrowser.selectFileOrFolder(this,$(this).attr('href'),'image'); //Select clicked image
				event.preventDefault(); //Don't follow link
			});
			
			$('div#files').click(function(event){
				if (event.button != 0) return true; //If right click then return true
				if ($(event.target).closest('.files').length == 1) return true;
				
				$('div#files li.selected, div#files tr.selected').removeClass("selected"); //Deselect all selected items
				$.MediaBrowser.updateFileSpecs($.MediaBrowser.currentFolder, 'folder');
				
				$('table.contextmenu, div.context-menu-shadow').css({'display': 'none'}); //Hide all contextmenus
			});	
			
			// Add event handlers to links in addressbar
			$('div#addressbar a[href]').live('click', function(event){
				$.MediaBrowser.loadFolder($(this).attr('href'));
				event.preventDefault(); //Don't follow link
			});
			
			// Add event handlers to links in addressbar
			$('input#fn').live('keyup', function(event){
				if(this.value != this.defaultValue){
					$('a.save_rename').css({'display':'inline'});
				} else {
					$('a.save_rename').css({'display':'none'});	
				}
			});
			
			// Add event handlers to links in treeview
			$('ul.treeview a[href]').live('click', function(event){
				$.MediaBrowser.loadFolder($(this).attr('href'));
				event.preventDefault(); //Don't follow link
			});
			
			//Hide all handlers and show on entering tree div
			$('ul.treeview a.children').css({'opacity' : 0}); //Hide all handlers
			$('div#tree').hover(function(){
					$('ul.treeview a.children').animate({'opacity' : 1}, 'slow');
				}, function(){
					$('ul.treeview a.children').animate({'opacity' : 0}, 'slow');	
				}
			);
			
			// Reset layout if window is being resized
			window.onresize = window.onload = function(){
				$.MediaBrowser.resizeWindow();
			};
		},
		
		changeview: function(view){
			$.MediaBrowser.currentView = view;
			$.MediaBrowser.loadFolder($.MediaBrowser.currentFolder);
			
			//Clear searchbox
			$('input#search').val($.MediaBrowser.searchDefaultValue);
			
			//Save view to cookie
			$.MediaBrowser.createCookie("view", $.MediaBrowser.currentView, 30);
			
			return false;
		},
		
		contextmenu: function(){
			$('div#files li a.folder, div#files tr.folder').contextMenu(foldercmenu);
			$('div#files li a.file, div#files tr.file').contextMenu(filecmenu);
			$('div#files li a.image, div#files tr.image').contextMenu(imagecmenu);
			$('div#files').contextMenu(cmenu);
		},
		
		copy: function(){
			// Clear clipboard
			$.MediaBrowser.clipboard = [];
			$.MediaBrowser.copyMethod = 'copy';
			
			$('div#files li.selected a, div#files tr.selected').each(function(){
				$.MediaBrowser.clipboard.push( urlencode($(this).attr("href")) );
			});
			
			//Update clipboard label
			$('div#cbItems').text( $.MediaBrowser.clipboard.length );
		},
		
		cut: function(){
			$.MediaBrowser.copy();
			$.MediaBrowser.copyMethod = 'cut';
			
			$('div#files li.selected, div#files tr.selected').addClass('cut');
		},

		delete_all: function(){
			
			var files = new Array();
			
			// Get all selected files and folders
			$('div#files li.selected a, div#files tr.selected').each(function(){
				files.push( urlencode($(this).attr("href")) );
			});
			
			$.post("actions.php", {'action': 'delete', 'files': files}, function(data){
				if(data.substring(0,7) == 'success'){ //Delete was a success
					var message = data.split("||");
					$.MediaBrowser.hideContextMenu();
					$.MediaBrowser.loadFolder($.MediaBrowser.currentFolder);
					$.MediaBrowser.reloadTree();
					$('input#file').val("");
					$.MediaBrowser.showMessage(message[1], "success");
				} else {
					$.MediaBrowser.showMessage(data, "error");
				}							   
			});
		},

		filter: function(){
			if($.MediaBrowser.filterString != ''){
					
				if ($.MediaBrowser.currentView == "details"){
					//Give table headers a fixed width so colums won't change widths when a row gets hidden
					if (!$.MediaBrowser.tableHeadersFixed) $.MediaBrowser.fix_widths();
					$('div#files table tbody tr').css({display: ""}); //NO DISPLAY:BLOCK TABLE CELLS DON"T LIKE IT!!
				} else {
					$('div#files ul li').css({display: "block"});
				}
				
				$('div#files table tbody tr, div#files ul li').removeClass('filter');
				
				// Normalise
				var filterString = $('select#filters').val();
				strFilter = $.trim(filterString.toLowerCase().replace(/\n/, '').replace(/\s{2,}/, ' '));
				
				if (strFilter != ""){	
				
					var arrList = [];
				
					var rgxpFilter = new RegExp(strFilter,'i');
								
					// Fill array with the list items or table rows depending on view
					if ($.MediaBrowser.currentView == "details"){ 
						arrList = $('div#files table tbody tr:not(.folder) .filename').get();
					
						for(var i = 0; i < arrList.length; i++){
							if ( !rgxpFilter.test( $(arrList[i]).text() ) ) $(arrList[i]).parent().addClass('filter').css({'display': 'none'});
						}
					} else {
						arrList = $('div#files ul li a:not(.folder) .filename').get();
						
						for(var i = 0; i < arrList.length; i++){
							if ( !rgxpFilter.test( $(arrList[i]).text() ) ) $(arrList[i]).parent().parent().addClass('filter').css({'display': 'none'});
						}
					}
		
				}	
				
			}
		},
		
		fix_widths: function(){
			if($.MediaBrowser.currentView == "details"){
				$('table#details th').each(function () {
					$(this).attr('width', parseInt($(this).outerWidth()));
				});
				$.MediaBrowser.tableHeadersFixed = 1;
			}
		},
		
		hideContextMenu: function(){
			// Hide all other contextmenus
			$('table.contextmenu, div.context-menu-shadow').css({'display': 'none'});
		},
			
		hideLayer: function(){
			$(".layer").css({'display':'none'});
			$("div#filelist").css({'display':'block'});
		},
		
		insertFile: function(){
			
			var URL = $("form#fileform input#file").val();

			if(URL != '') {
			
				if ($.MediaBrowser.baseUrl)
					URL = $.MediaBrowser.baseUrl + URL;
		
				if ($.MediaBrowser.callback) {

					try {
						fn = eval('window.opener.'+$.MediaBrowser.callback);
						if (fn)
							fn(URL);
					} catch (e) {
						alert('No callback:' + e.message);
					}

					self.close();

				} else if (typeof(tinyMCE) != 'undefined') {

					var tmSettings = tinyMCE.majorVersion == '2' ? tinyMCE : tinyMCEPopup;

					try {
						var win = tmSettings.getWindowArg("window");
					} catch(err) {
						$.MediaBrowser.showMessage(insert_cancelled);
						return;
					}
					
					// insert information now
					win.document.getElementById(tmSettings.getWindowArg("input")).value = URL;
			
					// are we an image browser
					if (typeof(win.ImageDialog) != "undefined") {
						// we are, so update image dimensions...
						if (win.ImageDialog.getImageData)
							win.ImageDialog.getImageData();
			
						// ... and preview if necessary
						if (win.ImageDialog.showPreviewImage)
							win.ImageDialog.showPreviewImage(URL);
					} else {
						if (win.getImageData)
							win.getImageData();
						if (win.showPreviewImage) 
							win.showPreviewImage(URL);
					}
		
					// close popup window
					tinyMCEPopup.close();

				}

			} else {

				$.MediaBrowser.showMessage(select_one_file);	

			}

		},
		
		loadFolder: function(folder){
			
			// Show loading icon
			$('div#files').html('<img src="img/ajaxLoader.gif" style="margin:10px;" />');
			
			switch($.MediaBrowser.currentView){
				case 'large_images': 
					viewfile = 'view_images_large.php';
					break;
				case 'small_images': 
					viewfile = 'view_images_small.php';
					break;
				case 'list': 
					viewfile = 'view_list.php';
					break;
				case 'details': 
					viewfile = 'view_details.php';
					break;
				case 'tiles':
					viewfile = 'view_tiles.php';
					break;
				default: //Content
					viewfile = 'view_content.php';
					break;
			}
			
			$.post(viewfile, {'ajax':true, 'path': urlencode(folder)}, function(data){
				if(data.substring(0,3) == '0||'){
					var message = data.split("||");
					$('div#files').html("");
					$.MediaBrowser.showMessage(message[1],"error");	
				} else {
					// Set currently selected folder
					$.MediaBrowser.setCurrentFolder(folder);
					
					$.MediaBrowser.updateAddressBar();
					$.MediaBrowser.updateHeader();
					$.MediaBrowser.updateTreeView(folder);
					
					$.MediaBrowser.updateFileSpecs(folder, 'folder');
					
					$('div#files').html(data);
					$.MediaBrowser.filter();
					
					if($.MediaBrowser.currentView == 'details') {
						$('div#files table#details tbody tr:odd').addClass('odd');	
						$.MediaBrowser.tableHeadersFixed = 0;
					}
					$.MediaBrowser.contextmenu();
				}
			});
		},
		
		newFolder: function(){
			
			folderpath = $('form#newfolderform input#folderpath').val();
			foldername = $('form#newfolderform input#foldername').val();
			
			$.post('actions.php', {'ajax':true, 'action': 'create_folder', 'folderpath': urlencode(folderpath), 'foldername': urlencode(foldername)}, function(data){
				if(data.substring(0,7) == 'success'){
					
					$.MediaBrowser.currentFolder = folderpath + foldername + '/';
					
					$.MediaBrowser.reloadTree();
					$('form#newfolderform input#folderpath').val($.MediaBrowser.currentFolder);
					$('form#newfolderform input#foldername').val("");
					
					var message = data.split("||");
					$.MediaBrowser.showMessage(message[1],"success");
				} else {
					var message = data.split("||");
					$.MediaBrowser.showMessage(message[1],"error");	
				}
			});
		},
		
		paste: function(){
			
			// Only paste if copyMethod is set
			if($.MediaBrowser.copyMethod != ''){
				action = $.MediaBrowser.copyMethod == 'cut' ? 'cut' : 'copy';
				
				// Show loading icon
				$('div#files').html('<img src="img/ajaxLoader.gif" style="margin:10px;" />');
				
				$.post("actions.php",
					   { // Post arguments
					   		'action': action+'_paste', 
					   		'files': $.MediaBrowser.clipboard, 
					   		'folder': urlencode($.MediaBrowser.currentFolder)
					   }, 
					   function(data){ // Callback
							if(data.substring(0,3) != '1||'){ // Paste was NOT successful
	
								var message = data.split("||");
								alert(message[1]);
								
								// Reload current folder
								$.MediaBrowser.loadFolder($.MediaBrowser.currentFolder);
							}	
							
							// Clear clipboard
							$.MediaBrowser.clipboard = [];
							
							// Reset copyMethod
							$.MediaBrowser.copyMethod = '';					
							
							// Update clipboard label to 0
							$('div#cbItems').text('0');
								
							// Reload current folder
							$.MediaBrowser.loadFolder($.MediaBrowser.currentFolder);
							
							//Reload tree
							$.MediaBrowser.reloadTree();
						}
				);
			}
			
		},
		
		printClipboard: function(){
			cb = $.MediaBrowser.clipboard;
			str = $('div#navbar li.label span').text() + "<br /><br />";
			
			for(i = 0; i < cb.length; i++){
				str	+= urldecode(cb[i]) + "<br />";
			}
			
			$.MediaBrowser.showMessage(str);
			return false;
		},
		
		reloadTree: function(){
			$.post('treeview.php', {'ajax': true}, function(data){
				$('div#tree').html(data);	
				$('ul.treeview').TreeView();
				
				$.MediaBrowser.updateTreeView($.MediaBrowser.currentFolder);
			});	
		},
		
		rename: function(path, type){
			
			var path_segments = ($.MediaBrowser.trim(path,"/")).split("/");
			var name = path_segments[path_segments.length - 1];
			var old_filename = name;
			var message = rename_folder;
			
			if (type == 'file') {
				//Save extension for later use
				file_segments = name.split(".");
				name = file_segments[0];
				file_ext = file_segments[file_segments.length - 1];
				message = rename_file;
			}
			
			var prompt_message = printf(message, name, "\n", "^ \\ / ? * \" ' < > : | .");
			var new_name = prompt(prompt_message, name);
		
			// Validate new name
			if(new_name === "" || new_name == name || new_name == null)
				return;
				
			// Check if any unwanted characters are used
			if(/\\|\/|\.|\?|\.|\^|\*|\"|'|\<|\>|\:|\|/.test(new_name)){
				$.MediaBrowser.showMessage(invalid_characters_used,"error");	
				return;								
			}
			
			var new_filename;
			if (type == 'file') {
				new_filename = new_name + '.' + file_ext;
			} else {
				new_filename = new_name;
			}

			//Send new filename to server and do rename		
			$.post("actions.php",
				{ // Post arguments
					'action': 'rename', 
					'new_filename': urlencode(new_filename),  
					'old_filename': urlencode(old_filename), 
					'folder': urlencode($.MediaBrowser.currentFolder),
					'type': type
				}, 
				function(data){ // Callback
					if(data.substring(0,3) != '1||'){ // Paste was NOT successful
						var message = data.split("||");
						$.MediaBrowser.showMessage(message[1],"error");
					} else {
						var message = data.split("||");
						$.MediaBrowser.showMessage(message[1],"success");
					}
							
					// Reload current folder
					$.MediaBrowser.loadFolder($.MediaBrowser.currentFolder);
							
					// Reload tree
					if(type === "folder")
						$.MediaBrowser.reloadTree();
				}
			);
		},
		
		resizeWindow: function(){
			
			// Set default screen layout
			windowHeight = $(window).height();
			addressbarHeight = $('div#addressbar').outerHeight();
			navbarHeight = $('div#navbar').outerHeight();
			detailsHeight = $('div#file-specs').outerHeight();
			explorerHeight = windowHeight - navbarHeight - addressbarHeight - detailsHeight;
			
			windowWidth = $(window).width();
			treeWidth = $('div#tree').outerWidth();
			separatorWidth = $('div#vertical-resize-handler').outerWidth();
			mainWidth = windowWidth - treeWidth - separatorWidth;
			
			//Set Explorer Height
			$('div#explorer').height(explorerHeight);
			$('div#main').height(explorerHeight);
			$('div#files').height(explorerHeight - 41); // -41 because of the fixed heading and ruler (H2) above the files
			$('div#main').width(mainWidth);
		},
		
		searchFor: function(strSearchFor){
			
			clearTimeout($.MediaBrowser.timeout);
			
			$.MediaBrowser.timeout = setTimeout(function () {
			
				if ($.MediaBrowser.currentView == "details"){
					//Give table headers a fixed width so colums won't change widths when a row gets hidden
					if (!$.MediaBrowser.tableHeadersFixed) $.MediaBrowser.fix_widths();
					$('div#files table tbody tr:not(.filter)').css({display: ""}); //NO DISPLAY:BLOCK!!
				} else {
					$('div#files ul li:not(.filter)').css({display: "block"});
				}
				
				// Normalise
				strSearchFor = $.trim(strSearchFor.toLowerCase().replace(/\n/, '').replace(/\s{2,}/, ' '));
				
				if (strSearchFor != ""){	
				
					var arrList = [];
				
					var rgxpSearchFor = new RegExp(strSearchFor,'i');
								
					// Fill array with the list items or table rows depending on view
					if ($.MediaBrowser.currentView == "details"){ 
						arrList = $('div#files table tbody tr:not(.filter) .filename').get();
					
						for(var i = 0; i < arrList.length; i++){
							if ( !rgxpSearchFor.test( $(arrList[i]).text() ) ) $(arrList[i]).parent().css({'display': "none"});
						}
					} else {
						arrList = $('div#files ul li:not(.filter) .filename').get();
						
						for(var i = 0; i < arrList.length; i++){
							if ( !rgxpSearchFor.test( $(arrList[i]).text() ) ) $(arrList[i]).parent().parent().css({'display': "none"});
						}
					}
		
				}
			}, 250);
		},
		
		selectFileOrFolder: function(el, path, type /* , contextmenu */){

			//See if function is called via a context menu
			cm = (typeof arguments[3] == 'undefined') ? false : true;

			// Hide all visible contextmenus
			$('table.contextmenu, div.context-menu-shadow').css({'display': 'none'});

			$.MediaBrowser.setSelection(el, cm);
			$.MediaBrowser.updateFileSpecs(path, type);
			
			if(type != "folder" && $('div#files li.selected, div#files tr.selected').length == 1){
				$("form#fileform input#file").val(path);
			} else {
				$("form#fileform input#file").val("");	
			}
		},
		
		setCurrentFolder: function(str){
			$.MediaBrowser.currentFolder = str;
			$('input#uploadpath, input#folderpath').val(str);
		},
		
		setSelection: function(el, cm){
			
			lastItemNo = null;
			currentItemNo = null;
			currentSelectedItem = $(el).attr('href');
			
			el = ($.MediaBrowser.currentView == 'details') ? $(el) : $(el).parent();
			container = ($.MediaBrowser.currentView == 'details') ? 'tbody' : 'ul';
			
			if($.MediaBrowser.shiftKeyPressed && $.MediaBrowser.lastSelectedItem != null){
				$('div#files li a, div#files tr').each(function(i){
					if($.MediaBrowser.lastSelectedItem == $(this).attr('href')){
						lastItemNo = i;
					}
					
					if(currentSelectedItem == $(this).attr('href')){
						currentItemNo = i;
					}
				});
				
				if(isNumber(lastItemNo) && isNumber(currentItemNo)){
					if(lastItemNo > currentItemNo){
						for(i = currentItemNo; i <= lastItemNo; i++){
							$('div#files li, div#files tr').eq(i).addClass('selected');
						}
					} else {
						for(i = lastItemNo; i <= currentItemNo; i++){
							$('div#files li, div#files tr').eq(i).addClass('selected');
						}
					}
				}
			}
			
			//See if selections should be removed
			if(!$.MediaBrowser.ctrlKeyPressed && !$.MediaBrowser.shiftKeyPressed){
				if(!cm || !el.hasClass("selected")){ //If click is called via a context menu then don't remove selections
					el.parents(container)
						.find('.selected')
						.removeClass('selected')
					.end();
				}
				
				el.addClass('selected');
				
			} else if($.MediaBrowser.ctrlKeyPressed && el.hasClass("selected")) { //If ctrl-key is pressed and item is already selected then deselect item
				el.removeClass('selected');
			} else {		
				el.addClass('selected');
			}
			
			$.MediaBrowser.lastSelectedItem = currentSelectedItem;

		},

		// Resize treeview and details screen
		setResizeHandlers: function(){
			var startingPositionX = 0;
			var startingPositionY = 0;
			var endPositionX = 0;
			var endPositionY = 0;
			
			$(document)

				.mousedown(function(e){
					if ($(e.target).attr('className') == 'resize-grip') {
						$.MediaBrowser.dragID = $(e.target).attr('id');
						startingPositionX = e.pageX;
						startingPositionY = e.pageY;
						$.MediaBrowser.dragMode = true;
						
						$.MediaBrowser.logger('dragID', $.MediaBrowser.dragID);
						
						// Thanks http://luke.breuer.com/tutorial/javascript-drag-and-drop-tutorial.aspx
						// cancel out any text selections 
						document.body.focus(); 
						
						// prevent text selection in IE 
						document.onselectstart = function () { return false; }; 
						
						// prevent IE from trying to drag an image 
						e.target.ondragstart = function() { return false; }; 
						
						// prevent text selection (except IE) 
						return false; 
					}
				})

				.mousemove(function(e){
					if(!$.MediaBrowser.dragMode) return false;
					endPositionX = e.pageX;
					endPositionY = e.pageY;
					if ($.MediaBrowser.dragID == 'vertical-resize-handler'){
						//Horizontal
						slide = endPositionX - startingPositionX
						if ($('div#tree').width() + slide < 300 && $('div#tree').width() + slide > 50 ){
							if (slide > 0){
								$('div#main').width($('div#main').width() - slide);
								$('div#tree').width($('div#tree').width() + slide);
							} else {
								$('div#tree').width($('div#tree').width() + slide);
								$('div#main').width($('div#main').width() - slide);
							}
						} else {
							$.MediaBrowser.dragMode = false;
							$.MediaBrowser.dragID = '';
						}
						
					} else {
						//Vertical
						slide = endPositionY - startingPositionY
						if ($('div#file-specs').height() - slide < 250 && $('div#file-specs').height() - slide > 50 ){
							if (slide > 0){
								$('div#file-specs').height($('div#file-specs').height() - slide);
								$('div#explorer').height($('div#explorer').height() + slide);	
								$('div#files').height($('div#files').height() + slide);	
								$('div#main').height($('div#main').height() + slide);	
							} else {
								$('div#files').height($('div#files').height() + slide);	
								$('div#main').height($('div#main').height() + slide);	
								$('div#explorer').height($('div#explorer').height() + slide);
								$('div#file-specs').height($('div#file-specs').height() - slide);
								
							}
						} else {
							$.MediaBrowser.dragMode = false;
							$.MediaBrowser.dragID = '';
						}
					}
					startingPositionX = e.pageX;
					startingPositionY = e.pageY;
				})

				.mouseup(function(e){
					if($.MediaBrowser.dragMode){
						$.MediaBrowser.dragMode = false;
						$.MediaBrowser.dragID = '';
					}
				})				
			.end();
		},
		
		showMessage: function(str, type){
			$('div#message').removeClass();
			if (type == "success" || type == "error") $('div#message').addClass(type);
			$('div#message').html(str);
			$('div#message').slideDown();
			
			timeout = (type != "error") ? 4000 : 7000;
			
			setTimeout(function() {
				$("div#message").slideUp();
			}, timeout);	
		},
		
		showLayer: function(elID){
			$.MediaBrowser.hideContextMenu();
			$(".layer").css({'display':'none'});
			$("div#" + elID).css({'display':'block'});
			if(elID == 'newfolder') $('input#foldername').focus();
			return false;
		},	

		// Breadcrumbs
		updateAddressBar: function(){

			var strLink = $.MediaBrowser.trim($.MediaBrowser.uploadPath, '/');
			var curFolder = $.MediaBrowser.trim($.MediaBrowser.currentFolder, '/').substring(strLink.length);
			curFolder = $.MediaBrowser.ltrim(curFolder, '/');

			strLink = '/' + strLink + '/';

			var html = '<li class=\'root\'><span>&nbsp;</span></li>';
			html += '<li><a href="' + strLink + '" title="' + $.MediaBrowser.rootName
				+ '"><span>' + $.MediaBrowser.rootName + '</span></a></li>';

			if (curFolder.length > 0) {

				folders = curFolder.split('/');
				
				for(i = 0; i < folders.length; i++){

					html += '<li><a href="';
					strLink += folders[i] + '/';
					html += strLink + '" title="' + folders[i] + '"><span>' + folders[i] + '</span></a></li>';
					
				}

			}
			
			$('div#addressbar ol').html(html);
			
		},
		
		// Set name of the folder as header
		updateHeader: function(){	
		
			title = $.MediaBrowser.rootName;
			uploadPath = $.MediaBrowser.trim($.MediaBrowser.uploadPath, '/');
			curFolder = $.MediaBrowser.trim($.MediaBrowser.currentFolder, '/');
			if (curFolder != uploadPath) {
				folders = curFolder.split('/');
				title = folders[folders.length-1]
			}
			
			$('div#main div#filelist h2').text(title);
		},
		
		// Open folders and select currently active folder
		updateTreeView: function(folder){
			$('ul.treeview li').removeClass();
			
			$('ul.treeview a[href="' + folder + '"]')
				.parents('ul')
					.css({'display':'block'})
					.prevAll('a.children')
						.addClass('open')
					.end()
				.end()
				
				.parent()
					.addClass('selected')
			.end();
			
		},
		
		// Show detailed information over the selected file or folder
		updateFileSpecs: function(path, type){
			$('div#file-specs #info').load('file_specs.php', {'ajax':true, 'path': urlencode(path), 'type': type});
			$('input#file').val("");
		},
		
		// Quirksmode.org --> http://www.quirksmode.org/js/cookies.html
		createCookie: function(name, value, days) {
			if (days) {
				var date = new Date();
				date.setTime(date.getTime()+(days*24*60*60*1000));
				var expires = "; expires="+date.toGMTString();
			}
			else var expires = "";
			document.cookie = name+"="+value+expires+"; path=/";
		},

		trim: function(str, chars) {
			return $.MediaBrowser.ltrim($.MediaBrowser.rtrim(str, chars), chars);
		},
 
		ltrim: function(str, chars) {
			chars = chars || '\\s';
			return str.replace(new RegExp('^[' + chars + ']+', 'g'), '');
		},
 
		rtrim: function(str, chars) {
			chars = chars || '\\s';
			return str.replace(new RegExp('[' + chars + ']+$', 'g'), '');
		}
	};

})(jQuery);

/**
 * PHP.JS (http://phpjs.org)
 *
 * This function is convenient when encoding a string to be used in a query part of a URL, 
 * as a convenient way to pass variables to the next page.
 * 
 * http://phpjs.org/functions/urlencode:573
 */
function urlencode (str) {
    str = (str+'').toString();
    
    // Tilde should be allowed unescaped in future versions of PHP (as reflected below), but if you want to reflect current
    // PHP behavior, you would need to add ".replace(/~/g, '%7E');" to the following.
    return encodeURIComponent(str).replace(/!/g, '%21').replace(/'/g, '%27').replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/\*/g, '%2A').replace(/%20/g, '+');
}

/**
 * PHP.JS (http://phpjs.org)
 *
 * http://phpjs.org/functions/urldecode:572
 */
function urldecode (str) {
    return decodeURIComponent(str.replace(/\+/g, '%20'));
}

function isNumber(val) {
	return /^-?((\d+\.?\d?)|(\.\d+))$/.test(val);
}

/**
 * Dav Glass extension for the Yahoo UI Library
 * 
 * Produces output according to format. 
 */
function printf() { 
	var num = arguments.length; 
  	var oStr = arguments[0];   
  	for (var i = 1; i < num; i++) { 
    	var pattern = "\\{" + (i-1) + "\\}"; 
    	var re = new RegExp(pattern, "g"); 
    	oStr = oStr.replace(re, arguments[i]); 
  	} 
  	return oStr; 
} 
