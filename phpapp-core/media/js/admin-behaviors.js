var rules = {
	'#sidebar .menuitem': function(elt) {
		elt.onmouseover = function() {
				Element.addClassName(elt, 'menuitemhover');
			};
		elt.onmouseout = function() {
				Element.removeClassName(elt, 'menuitemhover');
			};
	},

	'form.focusonload': function(elt) {
		Form.focusFirstElement(elt);
	},
	'form.validated': function(form) {
		// Validated form
		var parts = form.getAttribute('id').split(/_/);
		var vurl = baseUrl + '/modules/' + parts[0] + '/' + parts[1] + '/validate/';
		parts = window.location.pathname.split(/\//);
		var action = parts[parts.length-2];
		if (action == 'edit' || action == 'save')
			vurl += parts[parts.length-1];
		vform = new AjaxValidatedForm(vurl, form.getAttribute('id'), 'errorMessage');
	},

	'input.datepicker': function(elt) {
		new Control.DatePicker(elt, { icon: mediaRoot + '/images/calendar.png', locale: userLocale });
	},
	'input.datetimepicker': function(elt) {
		new Control.DatePicker(elt, { icon: mediaRoot + '/images/calendar.png', timePicker: true, locale: userLocale, timePickerAdjacent: true });
	},
	'input.colorpicker': function(elt) {
		new Control.ColorPicker(elt, { icon: mediaRoot + '/images/blank.gif' });
	},

	// Autocomplete boxes
	'input.autocomplete': function(elt) {
		var paths = elt.id.split(/_/);
		if (paths.length == 3) {
			var updateList = document.createElement('div');
			Element.hide(updateList);
			document.body.appendChild(updateList);
			new Ajax.Autocompleter(elt, updateList, baseUrl + '/modules/'+paths[1]+'/'+paths[2]+'/autocomplete', { parameters: window.location.search.substring(1), paramName: 'search' });
		}
	},

	'input.relavailsearch': function(elt) {
		var idx = elt.id.indexOf('_');
		var field = elt.id.substring(idx + 1);
		elt.onkeydown = function(e) { typeAheadFind(e, elt, $('relavailable_'+field)); }.bindAsEventListener(this);
	},

	// Sortable lists
	'.sortablehandle': function(elt) {
		setSortable(elt, 'dragHandle');
	},
	'.sortable': function(elt) {
		setSortable(elt);
	},
	'.sortable li': function(elt) {
		setSortableItemBehavior(elt, null);
	},
	'.treelist': function (elt) {
		new Control.TreeList(elt, {
			topOffset: 5,
			triggerOutdent: '17px',
			collapseIcon: mediaRoot + '/images/down_arrow_outline.png',
			collapseIconHover: mediaRoot + '/images/down_arrow_filled.png',
			expandIcon: mediaRoot + '/images/right_arrow_outline.png',
			expandIconHover: mediaRoot + '/images/right_arrow_filled.png'
			});
	},
	'.expander': function (elt) {
		new Control.TreeList(elt, {
			contentMargin: 0,
			singleClick: true,
			collapseIcon: mediaRoot + '/images/down_arrow_outline.png',
			collapseIconHover: mediaRoot + '/images/down_arrow_filled.png',
			expandIcon: mediaRoot + '/images/right_arrow_outline.png',
			expandIconHover: mediaRoot + '/images/right_arrow_filled.png'
			});
	},
	'ul.collapsible li a.selectable': function(elt) {
		elt.onclick = function() {
				var list = null;
				for (var i = 0; i < elt.parentNode.childNodes.length; ++i) {
					if (elt.parentNode.childNodes[i].nodeName == 'UL') {
						list = elt.parentNode.childNodes[i];
						break;
					}
				}
				if (list) {
					if (list.style.display) list.style.display = '';
					else list.style.display = 'block';
					return false;
				}
			};
	}
};
Behaviour.register(rules);

if (typeof(tinyMCE) != 'undefined') {
	var isIE = (navigator.appName == 'Microsoft Internet Explorer');
	var stdOptions = {
		mode : 'textareas',
		editor_selector: 'richtext',
		content_css: baseUrl + '/css/layout.css',
		theme : 'advanced',
		theme_advanced_toolbar_location : 'top',
		theme_advanced_toolbar_align : 'left',
		theme_advanced_buttons1 : 'bold,italic,underline,separator,justifyleft,justifycenter,justifyright,justifyfull,separator,numlist,bullist,outdent,indent,separator,link,unlink,separator,forecolor,backcolor,separator,hr,image,table,media,separator,code',
		theme_advanced_buttons2 : '',
		theme_advanced_buttons3 : '',
		theme_advanced_blockformats : 'p,div,h1,h2,h3,h4,h5,h6,blockquote,dt,dd,code,samp',
		auto_cleanup_word: false,
		forced_root_block: false,
		force_p_newlines: true,
		force_br_newlines: false,
		relative_urls: false,
		file_browser_callback: 'filebrowser',
		//onchange_callback: 'mceFieldChanged',
		plugins : 'table,paste,advhr,advlink,insertdatetime,preview,advimage,media,searchreplace,print,contextmenu,fullscreen',
		plugin_insertdate_dateFormat : '%m/%d/%Y',
		plugin_insertdate_timeFormat : '%H:%M:%S',
		extended_valid_elements : 'a[name|href|target|title|onclick|class],img[src|class|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name|usemap],hr[class|width|size|noshade],font[face|size|color|style],span[class|align|style],marquee[*],iframe[*],map[name|id],area[coords|href|title],script[*],form[*],input[*],select[*]',
		paste_auto_cleanup_on_paste : isIE,
		paste_convert_headers_to_strong : isIE,
		setup: function(ed) {
			ed.onSaveContent.add(function(ed, o) {
					var el = o.element;
					var f = AjaxValidatedForm.get(el.form);
					if (f) {
						// Save to textarea manually since tinyMCE doesn't provide
						// post-save event
						el.value = ed.getContent();
						f.fieldBlurByElement(el);
					}
				});
		}
	};
	if (typeof(urlList) != 'undefined') {
		stdOptions['external_link_list_url'] = urlList;
	}

	tinyMCE.init(Object.copy(stdOptions));
	tinyMCE.init(Object.copy(stdOptions, {
			editor_selector: 'htmlrichtext',
			theme_advanced_buttons1 : 'formatselect,fontselect,fontsizeselect,bold,italic,underline,separator,justifyleft,justifycenter,justifyright,justifyfull',
			theme_advanced_buttons2 : 'numlist,bullist,outdent,indent,separator,undo,redo,separator,link,unlink,anchor,separator,forecolor,backcolor,separator,hr,image,table,media,separator,spellchecker,removeformat,cleanup,separator,code'
		}));
	tinyMCE.init(Object.copy(stdOptions, {
			editor_selector: 'simplerichtext',
			theme_advanced_buttons1 : 'bold,italic,underline,separator,removeformat'
		}));
}
