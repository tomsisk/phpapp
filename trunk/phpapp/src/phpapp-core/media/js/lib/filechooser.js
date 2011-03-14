/*
 * Control.FileChooser
 * 
 * Displays a file chooser when a user clicks on the file select icon in
 * the input control.  Requires a user-provided function to provide directory
 * queries (static or via AJAX).
 *
 * Features:
 *  - Image preview
 *  - Integrated file upload
 *  - Create and delete directories and files
 *  - Customizable by CSS
 *
 * Written and maintained by Jeremy Jongsma (jeremy@jongsma.org)
 */
if (window.Control == undefined) Control = {};

Control.FileChooser = Class.create();
Control.FileChooser.prototype = {
	initialize: function(element, fileManager, options) {
		this.element = $(element);
		this.options = Object.extend({
				icon: '/images/icons/filechooser.png'
			}, options || {});
		
		this.filechooser = new Control.FileChooserPanel(fileManager, Object.extend({
				openListener: this.fileSelected.bind(this),
				standalone: true
			}, options || {}));

		this.dialogOpen = false;

		this.dialog = this.filechooser.getElement();

		this.button = document.createElement('img');
		this.button.src = this.options.icon;
		this.button.className = 'inputExtension';
		this.button.title = 'Select a file';
		var topOffset = /MSIE/.test(navigator.userAgent) ? '1px' : '3px';
		Element.setStyle(this.button, {'position': 'relative', 'left': '-24px', 'top': topOffset});
		if (this.element.nextSibling) this.element.parentNode.insertBefore(this.button, this.element.nextSibling);
		else this.element.parentNode.appendChild(this.button);

		this.element.style.paddingRight = '26px';

		this.button.onclick = this.toggleChooser.bindAsEventListener(this);
		this.element.onblur = this.hideChooser.bindAsEventListener(this);
		this.element.onkeypress = this.hideChooser.bindAsEventListener(this);
		this.documentClickListener = this.documentClickHandler.bindAsEventListener(this);
	},
	fileSelected: function(file) {
		this.element.value = file.url;
		this.hideChooser();
	},
	toggleChooser: function() {
		if (this.dialogOpen) this.hideChooser();
		else this.showChooser();
	},
	showChooser: function() {
		if (!this.dialogOpen) {
			var dim = Element.getDimensions(this.element);
			var position = Position.cumulativeOffset(this.element);
			var pickerTop = /MSIE/.test(navigator.userAgent) ? (position[1] + dim.height) + 'px' : (position[1] + dim.height - 1) + 'px';
			this.dialog.style.top = pickerTop;
			this.dialog.style.left = position[0] + 'px';
			this.filechooser.refresh();
			document.body.appendChild(this.dialog);
			Event.observe(document, 'click', this.documentClickListener);
			this.dialogOpen = true;
		}
	},
	hideChooser: function() {
		if (this.dialogOpen) {
			Event.stopObserving(document, 'click', this.documentClickListener);
			if (this.dialog.parentNode)
				Element.remove(this.dialog);
			this.dialogOpen = false;
		}
	},
	documentClickHandler: function(e) {
		var element = Event.element(e);
		var abort = false;
		do {
			if (element == this.button || element == this.dialog)
				abort = true;
		} while (element = element.parentNode);
		if (!abort)
			this.hideChooser();
	}
};

Control.FileChooserPanel = Class.create();
Control.FileChooserPanel.prototype = {

	initialize: function(fileLister, options) {
		this.fileLister = fileLister || Prototype.emptyFunction;
		this.options = Object.extend({
				width: 360,
				height: 220,
				className: 'filechooserControl',
				fileImage: '/images/icons/file.gif',
				directoryImage: '/images/icons/directory.gif',
				parentImage: '/images/icons/parent.gif',
				uploadHandler: this.showUploadDialog.bindAsEventListener(this)
			}, options || {});
		this.element = this.createFileChooser();
		if (this.options.selectFile)
			this.select(this.options.selectFile);
		else
			this.refresh();
	},

	getElement: function() {
		return this.element;
	},

	createFileChooser: function() {
		var browser = document.createElement('div');

		this.directoryHeader = document.createElement('div');
		this.directoryHeader.style.marginBottom = '5px';
		this.directoryHeader.className = 'directoryheader';
		this.directoryHeader.innerHTML = '&nbsp;';
		browser.appendChild(this.directoryHeader);

		var table = document.createElement('table');
		table.cellSpacing = 0;
		table.cellPadding = 0;
		table.style.border = 0;

		var row = table.insertRow(0);

		var previewHeight = this.options.height - 40;
		var previewWidth = Math.round((this.options.width - 6) * 0.3);
		var listHeight = this.options.height - 61;
		var listWidth = this.options.width - previewWidth - 10;

		var cell = row.insertCell(0);
		cell.vAlign = 'top';
		this.fileList = document.createElement('div');
		Element.setStyle(this.fileList, {'height': listHeight + 'px', 'width': listWidth + 'px', 'overflow': 'auto', 'marginRight': '3px', 'marginBottom': '5px'});
		this.fileList.className = 'filelist';
		this.fileList.onmousedown = function() { return false; };
		this.fileList.onselectstart = function() { return false; };
		cell.appendChild(this.fileList);

		this.createButton = document.createElement('input');
		this.createButton.type = 'button';
		this.createButton.value = 'New Folder';
		this.createButton.style.marginRight = '5px';
		this.createButton.style.width = Math.round((listWidth - 10) / 3) + 'px';
		this.createButton.onclick = this.showDirectoryCreateDialog.bindAsEventListener(this);
		this.uploadButton = document.createElement('input');
		this.uploadButton.type = 'button';
		this.uploadButton.value = 'New File';
		this.uploadButton.style.marginRight = '5px';
		this.uploadButton.style.width = Math.round((listWidth - 10) / 3) + 'px';
		this.uploadButton.onclick = function(e) { this.options.uploadHandler(this); }.bindAsEventListener(this);
		this.deleteButton = document.createElement('input');
		this.deleteButton.type = 'button';
		this.deleteButton.value = 'Delete';
		this.deleteButton.style.width = Math.round((listWidth - 10) / 3) + 'px';
		this.deleteButton.onclick = this.showDeleteDialog.bindAsEventListener(this);

		var buttons = document.createElement('div');
		buttons.appendChild(this.createButton);
		buttons.appendChild(this.uploadButton);
		buttons.appendChild(this.deleteButton);
		cell.appendChild(buttons);

		cell = row.insertCell(1);
		cell.vAlign = 'top';
		this.filePreview = document.createElement('div');
		Element.setStyle(this.filePreview, {'height': previewHeight + 'px', 'width': previewWidth + 'px', 'marginLeft': '3px', 'marginBottom': '5px', 'overflow': 'hidden', 'position': 'relative'});
		this.filePreview.className = 'filepreview';
		cell.appendChild(this.filePreview);

		browser.appendChild(table);

		Event.observe(document, 'keypress', this.keyPressListener());

		if (this.options.standalone) {
			var form = document.createElement('form');
			form.style.margin = 0;

			var table = document.createElement('table');
			table.cellSpacing = 0;
			table.cellPadding = 0;
			table.border = 0;

			var row = table.insertRow(0);

			var cell = row.insertCell(0);
			this.fileLocation = document.createElement('input');
			this.fileLocation.type = 'text';
			this.fileLocation.style.width = '245px';
			this.fileLocation.style.marginRight = '5px';
			this.fileLocation.readOnly = true;
			cell.appendChild(this.fileLocation);

			cell = row.insertCell(1);
			cell.style.textAlign = 'right';
			var input = document.createElement('input');
			input.type = 'button';
			input.value = 'Cancel';
			input.style.width = '50px';
			input.style.marginRight = '5px';
			input.onclick = function(e) { Element.remove(this.getElement()); }.bindAsEventListener(this);
			cell.appendChild(input);

			cell = row.insertCell(2);
			cell.style.textAlign = 'right';
			var input = document.createElement('input');
			input.type = 'button';
			input.value = 'Select';
			input.style.width = '50px';
			input.onclick = function(e) { (this.options.openListener || Prototype.emptyFunction)(this.selectedFile); }.bindAsEventListener(this);
			cell.appendChild(input);

			form.appendChild(table);

			browser.appendChild(form);

			var wrapper = document.createElement('div');
			wrapper.style.position = 'absolute';
			wrapper.appendChild(browser);

			wrapper.className = this.options.className;
			return wrapper;
		} else {
			browser.className = this.options.className;
			return browser;
		}
	},

	select: function(imageurl) {
		this.filePreview.innerHTML = '';
		this.fileList.innerHTML = '<div style="padding:3px">Loading file list...</div>';
		this.selectedFile = null;
		var response = this.fileLister(null, this.selectByURL(imageurl).bind(this));
		if (response)
			this.selectByURL(imageurl)(response);
	},

	selectByURL: function(imageurl) {
		return function(directory) {
			if (directory.status != 'error' && imageurl && imageurl.indexOf(directory.url) == 0) {
				var relpath = imageurl.substr(directory.url.length);
				var reldir = relpath.substr(0, relpath.lastIndexOf('/'));
				this.pendingSelect = imageurl;
				if (reldir != directory.path) {
					this.refresh(reldir);
					return;
				}
			}
			this.populateFileList(directory);
		}.bind(this);
	},

	refresh: function(directory) {
		if (!directory && this.currentDirectory) directory = this.currentDirectory.path;
		if (this.prompt && this.prompt.parentNode) Element.remove(this.prompt);
		this.filePreview.innerHTML = '';
		this.fileList.innerHTML = '<div style="padding:3px">Loading file list...</div>';
		this.selectedFile = null;

		var response = this.fileLister(directory, this.populateFileList.bind(this));
		// If it returned no result, it's asynchronous
		if (response)
			this.populateFileList(response);
	},

	populateFileList: function(directory) {
		this.currentDirectory = directory;
		this.entries = [];
	
		this.directoryHeader.innerHTML = '<b>Folder:</b> ' + (directory.path || '/');
		this.fileList.innerHTML = '';
		if (directory.parent)
			this.entries[this.entries.length] = {
				image: 'parent',
				type: 'directory',
				name: 'Parent folder',
				path: directory.parent 
				};
		if(directory.files) {
			if (directory.files.constructor == Array)
				directory.files.each(function(row) {
						this.entries.push(row);
					}.bind(this));
			else
				this.entries.push(directory.files);
		}

		if (this.entries.length) {
			var table = document.createElement('table');
			var sRow = null;
			table.cellSpacing = 0;
			table.cellPadding = 0;
			table.width = '100%';
			table.style.border = 3;
			this.entries.each(function(row) {
					var cRow = this.createFileRow(row, table);
					if (this.pendingSelect && this.pendingSelect == row.url) {
						this.selectRow(row);
						sRow = cRow;
						this.pendingSelect = null;
					}
				}.bind(this));
			this.fileList.appendChild(table);
			if (sRow) {
				var tHeight = table.offsetHeight;
				var cHeight = this.fileList.offsetHeight;
				var rTop = sRow.offsetTop;
				var rBottom = rTop + sRow.offsetHeight;
				if (rBottom > cHeight) {
					var idealTop = Math.round(rTop - (cHeight / 2));
					if (idealTop + cHeight > tHeight)
						this.fileList.scrollTop = tHeight - cHeight;
					else
						this.fileList.scrollTop = idealTop;
				}
			}
		} else {
			this.fileList.innerHTML = '<div style="padding:3px">To add items to your folder, please click <b>New Folder</b> or <b>New File</b> below.</div>';
		}

		if (directory.fileManager) {
			this.createButton.disabled = false;
			if ($('hidden_iframe'))
				this.uploadButton.disabled = false;
			else
				this.uploadButton.disabled = true;
		} else {
			this.createButton.disabled = true;
			this.uploadButton.disabled = true;
		}
	},

	showUploadDialog: function() {
		var prompt = document.createElement('div');
		prompt.className = 'prompt';
		prompt.style.position = 'absolute';
		prompt.onkeypress = function(e) {
				if (e.stopPropagation)
					e.stopPropagation();
				else e.cancelBubble = true;
			}.bindAsEventListener(this);

		/* IE can't add frames dynamically and still use them as form targets
		var uploadFrame = document.createElement('iframe');
		uploadFrame.name = 'rte_uploadFrame';
		uploadFrame.src = 'about:blank';
		Element.hide(uploadFrame);
		document.body.appendChild(uploadFrame);
		*/
		var uploadFrame = $('hidden_iframe');
		var uploadComplete = function(e) {
				var msg = uploadFrame.contentWindow.document.body.innerHTML;
				if (prompt.parentNode) Element.remove(prompt);
				if (msg && msg != '') alert(msg);
				else this.refresh(this.currentDirectory.path);
			}.bindAsEventListener(this);
		// Mozilla
		uploadFrame.onload = uploadComplete;

		var uploadForm = document.createElement('form');
		uploadForm.target = uploadFrame.name;
		uploadForm.action = this.currentDirectory.fileManager;
		uploadForm.method = 'post';
		uploadForm.enctype = 'multipart/form-data';
		var hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = 'a';
		hidden.value = 'upload';
		uploadForm.appendChild(hidden);
		hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = 'p';
		hidden.value = (this.currentDirectory.path || '');
		uploadForm.appendChild(hidden);

		if (/MSIE/.test(navigator.userAgent)) {
			// For some reason form.enctype doesn't work on IE, despite the docs
			uploadForm.encoding = 'multipart/form-data';
			// IE doesn't have iframe onload that works well
			uploadForm.onsubmit = function(e) {
					var timer;
					var checkComplete = function() {
							try {
								var state = uploadFrame.contentWindow.document.readyState;
								if (state == 'complete') {
									clearInterval(timer);
									uploadComplete();
								}
							} catch(e) { }
						};
					timer = setInterval(checkComplete, 10);
				}.bindAsEventListener(this);
		}

		var table = document.createElement('table');
		var row = table.insertRow(0);
		var cell = row.insertCell(0);
		cell.colSpan = 2;
		cell.innerHTML = '<b>Upload File</b>';

		row = table.insertRow(1);
		cell = row.insertCell(0);
		cell.innerHTML = 'File';
		cell = row.insertCell(1);
		var input = document.createElement('input');
		input.type = 'file';
		input.name = 'i';
		input.style.width = '200px';
		cell.appendChild(input);

		row = table.insertRow(2);
		cell = row.insertCell(0);
		cell.innerHTML = 'New filename';
		cell = row.insertCell(1);
		var input = document.createElement('input');
		input.type = 'text';
		input.name = 'n';
		input.style.width = '200px';
		cell.appendChild(input);

		var upload = document.createElement('input');
		upload.type = 'submit';
		upload.value = 'Upload';
		upload.style.marginRight = '5px';

		var cancel = document.createElement('input');
		cancel.type = 'button';
		cancel.value = 'Cancel';
		cancel.onclick = function(e) { Element.remove(prompt); };

		row = table.insertRow(3);
		cell = row.insertCell(0);
		cell.innerHTML = '&nbsp;';
		cell = row.insertCell(1);
		cell.appendChild(upload);
		cell.appendChild(cancel);

		uploadForm.appendChild(table);
		prompt.appendChild(uploadForm);

		this.element.appendChild(prompt);
		this.prompt = prompt;

		prompt.style.top = Math.round((this.element.offsetHeight - prompt.offsetHeight) / 2) + 'px';
		prompt.style.left = Math.round((this.element.offsetWidth - prompt.offsetWidth) / 2) + 'px';

		Form.focusFirstElement(uploadForm);
	},

	showDeleteDialog: function() {
		var message = this.selectedFile.type == 'directory'
			? 'Are you sure you want to PERMANENTLY delete this folder\nand all files and folders in it?'
			: 'Are you sure you want to PERMANENTLY delete this file?';
		if (this.selectedFile && confirm(message)) {
			var url = this.currentDirectory.fileManager;
			var options = {
					parameters: 'a=delete&p=' + (this.currentDirectory.path || '') + '&f=' + this.selectedFile.name,
					onSuccess: function(transport) {
							this.refresh(this.currentDirectory.path);
						}.bindAsEventListener(this),
					onFailure: function(transport) {
							this.refresh(this.currentDirectory.path);
							alert(transport.responseText);
						}.bindAsEventListener(this)
				};

			this.filePreview.innerHTML = '';
			this.fileList.innerHTML = 'Loading file list...';

			new Ajax.Request(url, options);
		}
	},

	showDirectoryCreateDialog: function() {
		var dirname = prompt('Enter a folder name:', '');
		if (dirname)
			this.createDirectory(dirname);
	},

	createDirectory: function(dirname) {
		var url = this.currentDirectory.fileManager;
		var options = {
				parameters: 'a=createdir&p=' + (this.currentDirectory.path || '') + '&d=' + dirname,
				onSuccess: this.createDirectorySuccessful.bind(this),
				onFailure: this.createDirectoryFailed.bind(this)
			};

		this.filePreview.innerHTML = '';
		this.fileList.innerHTML = 'Loading file list...';

		new Ajax.Request(url, options);
	},

	createDirectorySuccessful: function(transport) {
		this.refresh(this.currentDirectory.path);
	},

	createDirectoryFailed: function(transport) {
		this.refresh(this.currentDirectory.path);
		alert(transport.responseText);
	},

	showPreview: function(url) {
		var image = document.createElement('img');
		var loaded = false;
		image.onload = function(e) {
				// Event fires again when adding to the preview frame
				if (!loaded) {
					loaded = true;

					var origWidth = image.width;
					var origHeight = image.height;

					// Clear preview pane
					while(this.filePreview.firstChild)
						Element.remove(this.filePreview.firstChild);

					// Figure out maximum dimensions and current ratios
					var maxWidth = this.filePreview.offsetWidth - 6;
					var maxHeight = this.filePreview.offsetHeight - 6;
					var widthRatio = image.width / maxWidth;
					var heightRatio = image.height / maxHeight;

					// Adjust to best fit
					if (widthRatio > 1 && widthRatio >= heightRatio) {
						image.width = Math.floor(image.width / widthRatio);
						image.height = Math.floor(image.height / widthRatio);
					} else if (heightRatio > 1) {
						image.width = Math.floor(image.width / heightRatio);
						image.height = Math.floor(image.height / heightRatio);
					}

					// Add to preview pane
					image.style.position = 'absolute';
					image.style.top = Math.round((maxHeight - image.height) / 2) + 'px';
					image.style.left = Math.round((maxWidth - image.width) / 2) + 'px';
					image.style.backgroundColor = '#FFFFFF';
					image.border = 1;
					this.filePreview.appendChild(image);

					if (this.options.previewListener)
						this.options.previewListener(origWidth, origHeight);
				}
			}.bindAsEventListener(this);
		image.onerror = function(e) {
				// Clear preview pane
				while(this.filePreview.firstChild)
					Element.remove(this.filePreview.firstChild);
				if (this.options.previewListener)
					this.options.previewListener('', '');
			}.bindAsEventListener(this);
		image.src = url;
	},

	createFileRow: function(record, table) {
		var row = table.insertRow(table.rows.length);

		var cell = row.insertCell(0);
		cell.className = 'filerow';
		cell.width = 10;
		var icon = document.createElement('img');
		icon.src = this.options[(record.image || record.type) + 'Image'];
		cell.appendChild(icon);

		cell = row.insertCell(1);
		cell.className = 'filerow';
		var fileName = document.createElement('div');
		fileName.style.overflow = 'hidden';
		fileName.innerHTML = record.name;
		cell.appendChild(fileName);

		cell = row.insertCell(2);
		cell.className = 'filerow';
		cell.align = 'right';

		if (record.size !== undefined) {
			var sizeDesc;
			if (record.type == 'directory')
				sizeDesc = record.size + ' items';
			else
				sizeDesc= this.formatFileSize(record.size);
			cell.innerHTML = '<nobr>' + sizeDesc + '</nobr>';
		} else {
			cell.innerHTML = '&nbsp;';
		}

		row.onmousedown = this.fileSelectListener(record);
		if (record.type == 'file')
			row.ondblclick = this.fileOpenListener(record);
		else
			row.ondblclick = this.directoryOpenListener(record);
		row.onselectstart = function() { return false; };

		record.element = row;

		return row;
	},

	formatFileSize: function(bytes) {
		if (!bytes) bytes = 0;
		if (bytes < 1024) {
			return bytes + ' bytes';
		} else if (bytes < 1024*1024) {
			return this.twoDecimals(bytes / 1024) + ' KB';
		} else {
			return this.twoDecimals(bytes / (1024*1024)) + ' MB';
		}
	},

	twoDecimals: function(num) {
		return Math.round(num * 100) / 100;
	},

	fileSelectListener: function(record) {
		return function(e) {	
				this.selectRow(record);
				return false;
			}.bindAsEventListener(this);
	},

	directoryOpenListener: function(record) {
		return function(e) {
				this.refresh(record.path);
			}.bindAsEventListener(this);
	},

	fileOpenListener: function(record) {
		return function(e) {
				if (this.options.openListener)
					this.options.openListener(record);
			}.bindAsEventListener(this);
	},

	keyPressListener: function() {
		return function(e) {
				if (this.element.parentNode) {
					switch(e.keyCode) {
						case Event.KEY_DELETE:
							this.showDeleteDialog();
							break;
						case Event.KEY_RETURN:
							if (this.selectedFile.type == 'file')
								this.fileOpenListener(this.selectedFile)();
							else
								this.directoryOpenListener(this.selectedFile)();
							break;
						case Event.KEY_UP:
							var idx = this.selectedIndex() - 1;
							if (idx < 0) idx = 0;
							this.selectRow(this.entries[idx]);
							break;
						case Event.KEY_DOWN:
							var idx = this.selectedIndex() + 1;
							if (idx >= this.entries.length) idx = this.entries.length - 1;
							this.selectRow(this.entries[idx]);
							break;
						case 33: // PgUp
							var visibleRows = Math.floor(this.fileList.offsetHeight / this.entries[0].element.offsetHeight);
							var idx = this.selectedIndex() - visibleRows;
							if (idx < 0) idx = 0;
							this.selectRow(this.entries[idx]);
							break;
						case 34: // PgDn
							var visibleRows = Math.floor(this.fileList.offsetHeight / this.entries[0].element.offsetHeight);
							var idx = this.selectedIndex() + visibleRows;
							if (idx >= this.entries.length) idx = this.entries.length - 1;
							this.selectRow(this.entries[idx]);
							break;
						case 35: // End
							if (this.entries)
								this.selectRow(this.entries[this.entries.length - 1]);
							break;
						case 36: // Home
							if (this.entries)
								this.selectRow(this.entries[0]);
							break;
						default:
							return;
					}
					Event.stop(e);
				}
			}.bindAsEventListener(this);
	},

	selectRow: function(record) {
		if (this.selectedFile != record) {
			if (this.selectedFile)
				Element.removeClassName(this.selectedFile.element, 'selected')

			this.selectedFile = record;
			Element.addClassName(record.element, 'selected')
			if (record.url) {
				this.showPreview(record.url);
				if (this.options.standalone)
					this.fileLocation.value = record.url;
				if (this.options.selectListener)
					this.options.selectListener(record.url);
			} else {
				this.showPreview('');
				if (this.options.selectListener)
					this.options.selectListener('');
			}
		}

		if (record.element.offsetTop < this.fileList.scrollTop)
			this.fileList.scrollTop = record.element.offsetTop;
		else if (record.element.offsetTop + record.element.offsetHeight > this.fileList.scrollTop + this.fileList.offsetHeight)
			this.fileList.scrollTop = (record.element.offsetTop + record.element.offsetHeight) - this.fileList.offsetHeight;
	},

	selectedIndex: function() {
		for (index = 0; index < this.entries.length; ++index)
			if (this.entries[index] == this.selectedFile)
				return index;
		return -1;
	}

};
