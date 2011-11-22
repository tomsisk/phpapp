function setSortableItemBehavior(elt, editPage) {
	var objId = elt.id.substring(elt.id.indexOf('_') + 1);
	Event.observe(elt, 'mousedown', function() {
			elt.style.backgroundColor = '#FFFF99';
		});
	Event.observe(elt, 'mouseup', function() {
			elt.style.backgroundColor = '#EEEEEE';
		});
	if (editPage)
		Event.observe(elt, 'dblclick', function() {
				window.location = editPage + '?id=' + objId;
			});
}

function arrayToString(ar, indent) {
	var ser = '';
	var pad = (indent ? indent : '');
	for (key in ar) {
		if (!ar[key] || ar[key].constructor != Function) {
			ser += pad + ' ' + key + ': ';
			if (ar[key] == null) {
				ser += 'null\n';
			} else if (typeof(ar[key]) == 'object') {
				ser += 'array()\n';
				ser += arrayToString(ar[key], pad + '  ');
			} else {
				ser += ar[key] + '\n';
			}
		} else {
			//ser += pad + ' ' + key + ': function()\n';
		}
	}
	return ser;
}

function popup(url, width, height) {
	window.open(url, 'popup', 'width='+width+',height='+height+',scrollbars=2');
}

var popupField = null;

function addPopupValue(value, desc) {
	var matches = $$('select[name='+popupField+']');
	var field = null;
	if (matches.length) {
		field = matches[0];
		var existing = $A(field.options).find(function(o) { return o.value == value; });
		if (existing) {
			existing.update(desc);
			existing.selected = true;
		} else {
			var opt = new Element('option', { 'value' : value }).update(desc);
			opt.selected = true;
			field.appendChild(opt);
			//field.selectedIndex = field.options.length - 1;
		}
	} else {
		// Try radio/checkbox list
		field = $$('input[name='+popupField+']');
		if (field && Object.isArray(field))
			field = field[0];
		var cont = field.parentNode;
		cont.appendChild(new Element('input', {'type': field.type, 'checked': true, 'value': value }));
		cont.appendChild(document.createTextNode(desc));
		cont.appendChild(new Element('br'));
	}
	fieldChanged(field);
	popupField = null;
}

function setSortable(elt, handle) {
	var lastChanged = null;
	Sortable.create(elt, {
		handle: (handle ? handle : null),
		format:  /^(?:[A-Za-z0-9\-\_]*)[_](.*)$/,
		onChange: function(item) {
			lastChanged = item;
			// Refresh row colors
			var rowclass = 'odd';
			$(elt).childElements().each(function(li) {
				Element.addClassName(li, rowclass);
				rowclass = rowclass == 'odd' ? 'even' : 'odd';
				Element.removeClassName(li, rowclass);
			});
		},
		onUpdate: function(cont) {
				if (lastChanged.id) {
					var itemId = lastChanged.id.split(/_/).pop();
					var newOrder = 1;
					for (var i = 0; i < cont.childNodes.length; ++i)
						if (cont.childNodes[i] == lastChanged) {
							newOrder = i + 1;
							break;
						}
					var objInfo = cont.id.split(/__/);
					var ajaxUrl = [baseUrl, 'modules', objInfo[0], objInfo[1], 'savejs', itemId].join('/');
					new Ajax.Request(ajaxUrl, {
							parameters: objInfo[2] + '=' + newOrder,
							onSuccess: function() {
								$(cont).select('li').each(function(c) { new Effect.Highlight(c); });
							}
						});
				}
			}
		});
}

var fieldNotifiers = {};

function addFieldNotifier(name, callback) {
	if (!fieldNotifiers[name])
		fieldNotifiers[name] = Array();
	fieldNotifiers[name][fieldNotifiers[name].length] = callback;
}

function fieldListCallback(url, name, field) {
	return function(name, value) {
		var updateCont = document.getElementById('fielderror_'+field);
		if (!updateCont) {
			var matches = $$('form *[name='+field+']');
			var element = matches ? matches[0] : null;
			if (element)
				updateCont = element.parentNode;
		}
		if (updateCont) {
			var updateTxt = document.createElement('span');
			updateTxt.className = 'fieldUpdate';
			updateTxt.innerHTML = 'Loading...';
			for (j = 0; j < updateCont.childNodes.length; ++j)
				if (updateCont.childNodes[j].tagName
					&& (Element.hasClassName(updateCont.childNodes[j], 'fieldError')
						|| Element.hasClassName(updateCont.childNodes[j], 'fieldUpdate'))
					)
					updateCont.removeChild(updateCont.childNodes[j--]);
			updateCont.appendChild(updateTxt);
		}
		new Ajax.Request(url, {
				parameters: escape(name)+'='+escape(value),
				onComplete: function(transport) {
						try {
							fieldListResponse(eval('(' + transport.responseText + ')'), field);
						} catch (e) {
							fieldListResponse(false, field);
						}
					}
			});
	}
}

function fieldListResponse(objects, field) {
	var matches = $$('form *[name='+field+']');
	var element = matches ? matches[0] : null;
	var updateCont = document.getElementById('fielderror_'+field);
	if (!updateCont && element)
		updateCont = element.parentNode;
	if (updateCont) {
		for (j = 0; j < updateCont.childNodes.length; ++j)
			if (updateCont.childNodes[j].tagName
				&& Element.hasClassName(updateCont.childNodes[j], 'fieldUpdate'))
				updateCont.removeChild(updateCont.childNodes[j--]);
	}
	if (element) {
		if (element.options.length) {
			if (element.options[0].value == '') {
				while (element.options.length > 1)
					element.removeChild(element.options[1]);
			} else {
				while (element.firstChild)
					element.removeChild(element.firstChild);
			}
		}
		for (var i = 0; i < objects.length; ++i)
			element.appendChild(new Element('option', {'value': objects[i].pk}).update(objects[i].name));
	}
}

function fieldChanged(field) {
	var name = field.name.replace(/__/, '.');
	var notifiers = fieldNotifiers[name];
	if (notifiers)
		$A(notifiers).each(function (item) {
			item(name, Form.Element.getValue(field), field);
		});
}

function mceFieldChanged(ed) {
	ed.save();
	var el = ed.getElement();
	fieldChanged(el);
}

function m2m_addAll(field) {
	var source = $('relavailable_'+field);
	var dest = $('m2mselected_'+field);
	moveListItems(source, dest, false);
	refreshHiddenValues(dest, field+'[]', $('m2mfield_'+field));
}

function m2m_addSelected(field) {
	var source = $('relavailable_'+field);
	var dest = $('m2mselected_'+field);
	moveListItems(source, dest, true);
	refreshHiddenValues(dest, field+'[]', $('m2mfield_'+field));
}

function m2m_removeSelected(field) {
	var source = $('m2mselected_'+field);
	var dest = $('relavailable_'+field);
	moveListItems(source, dest, true);
	refreshHiddenValues(source, field+'[]', $('m2mfield_'+field));
}

function m2m_removeAll(field) {
	var source = $('m2mselected_'+field);
	var dest = $('relavailable_'+field);
	moveListItems(source, dest, false);
	refreshHiddenValues(source, field+'[]', $('m2mfield_'+field));
}

function moveListItems(source, dest, selectedOnly) {
	$A(source.options).each(function (option) {
			if (!selectedOnly || option.selected) {
				option.selected = false;
				if (!dest.options.length || option.text > dest.options[dest.options.length-1].text)
					dest.appendChild(option);
				for (var j = 0; j < dest.options.length; ++j) {
					if (option.text < dest.options[j].text) {
						dest.insertBefore(option, dest.options[j]);
						break;
					}
				}

				// Update type-ahead cache
				var sourceid = source.name||source.id;
				if (cache = typeAheadCache[sourceid])
					typeAheadCache[sourceid] = cache.reject(function(item) { return (item == option);});

				var destid = dest.name||dest.id;
				if (cache = typeAheadCache[destid]) {
					typeAheadCache[destid][cache.length] = option;
					typeAheadCache[destid].sort(function(left, right) {
							var a = left.text, b = right.text;
							return a < b ? -1 : a > b ? 1 : 0;
						});
				}
			}
		});
}

function refreshHiddenValues(list, field, fieldCont) {
	while (fieldCont.firstChild)
		fieldCont.removeChild(fieldCont.firstChild);
	$A(list.options).each(function(option) {
			fieldCont.appendChild(new Element('input', {'type': 'hidden', 'name': field, 'value': option.value}));
		});
}

var typeAheadTimer;
var typeAheadCache = {};

function typeAheadFind(e, input, list) {
	if (e.keyCode == Event.KEY_RETURN) {
		Event.stop(e);
		findListEntry(input.value, list);
		return false;
	} else if (e.keyCode == Event.KEY_UP || e.keyCode == Event.KEY_DOWN) {
		list.focus();
		return false;
	}
	if (typeAheadTimer) {
		clearTimeout(typeAheadTimer);
		typeAheadTimer = null;
	}
	typeAheadTimer = setTimeout(function() { findListEntry(input.value, list); }, 200);
}

function findListEntry(text, list, lastIdx) {	
	// Cache original options if this is the first search
	var listid = list.name||list.id;
	if (!typeAheadCache[listid])
		typeAheadCache[listid] = $A(list.options);
	text = text.toLowerCase();
	if (list.options.length) $A(list.options).invoke('remove');
	typeAheadCache[listid].each(function (opt) {
			var comp = opt.text.toLowerCase();
			if (comp.indexOf(text) > -1)
				list.appendChild(opt);
		});
	if (!list.multiple)
		list.selectedIndex = 0;
}

function removeInlineRow(row, noheader, nolink) {
	var minrows = 3;
	if (noheader)
		minrows--;
	if (nolink)
		minrows--;
	if (row.parentNode.select('tr').length <= minrows) {
		var newrow = cloneRow(row);
		Element.removeClassName(newrow, 'even');
		Element.addClassName(newrow, 'odd');
	}
	var p = row.nextSibling;
	$(row).remove();
	AjaxValidatedForm.refreshParent(p);
}

function cloneLastChild(elt) {
	var src = elt.lastChild;
	var clone = $(src.cloneNode(true));
	if (clone.hasClassName('even')) {
		clone.removeClassName('even');
		clone.addClassName('odd');
	} else {
		clone.removeClassName('odd');
		clone.addClassName('even');
	}
	clone.select('input[type=checkbox]', 'input[type=radio]').each(function(i) { i.checked = false; });
	clone.select('textarea', 'input[type=hidden]', 'input[type=text]', 'input[type=password]').each(function(i) { i.value = ''; });
	clone.select('select').each(function(i) { i.selectedIndex = -1; });
	src.parentNode.appendChild(clone);
	if (inputs = clone.select('input', 'textarea', 'select'))
		inputs.first().focus();
	AjaxValidatedForm.refreshParent(src);
	if (elt.nodeName.toUpperCase() == 'UL') {
		if ($(elt).hasClassName('sortable'))
			setSortable(elt);
		else if ($(elt).hasClassName('sortablehandle'))
			setSortable(elt, 'dragHandle');
	}

	Behaviour.applySelective(clone);

	return clone;
}

function removeListRow(li) {
	if (li.parentNode.select('li').length <= 1)
		cloneLastChild(li.parentNode);
	var p = li.parentNode;
	$(li).remove();

	var rowclass = 'odd';
	p.select('li').each(function(li) {
		li.addClassName(rowclass);
		rowclass = rowclass == 'odd' ? 'even' : 'odd';
		li.removeClassName(rowclass);
	});

	AjaxValidatedForm.refreshParent(p);
}

function clonePreviousRow(row) {
	var orig = row;
	while(orig = orig.previousSibling)
		if (orig.nodeType == 1 && orig.tagName == 'TR') {
			cloneRow(orig);
			break;
		}
	AjaxValidatedForm.refreshParent(row);
}

function cloneRow(row) {
	var newrow = $(row.cloneNode(true));
	if (Element.hasClassName(row, 'odd')) {
		Element.removeClassName(newrow, 'odd');
		Element.addClassName(newrow, 'even');
	} else if (Element.hasClassName(row, 'even')) {
		Element.removeClassName(newrow, 'even');
		Element.addClassName(newrow, 'odd');
	}
	newrow.select('input[type=checkbox]', 'input[type=radio]').each(function(i) { i.checked = false; });
	newrow.select('textarea', 'input[type=hidden]', 'input[type=text]', 'input[type=password]').each(function(i) { i.value = ''; });
	newrow.select('select').each(function(i) { i.selectedIndex = -1; });
	row.parentNode.insertBefore(newrow, row.nextSibling);
	newrow.select('td')[1].select('input', 'textarea', 'select').first().focus();

	Behaviour.applySelective(newrow);

	return newrow;
}

function syncTableCells(group) {
	var ref = group.down('table.tablegroupref');
	if (ref) {
		var widths = $(ref).select('td, th').invoke('getWidth');
		group.select('table.tablegroupmember').each(function(t) {
				cells = t.select('td, th');
				for (var i = 0; i < widths.length; ++i) {
					var padding = parseInt(cells[i].getStyle('padding-left'))
								+ parseInt(cells[i].getStyle('padding-right'));
					cells[i].style.width = (widths[i] - padding) + 'px';
				}
			});
	}
}

Event.observe(window, 'load', function(e) {
		$$('.tablegroup').each(syncTableCells);
	});

function addComboListRow(field) {
	var selector = $(field+'_selector');
	var selected = selector.options[selector.selectedIndex];
	var list = $(field+'_list');
	var li = new Element('li');
	var dellink = new Element('a', { 'href': '#', 'onclick': 'removeComboListRow("'+field+'", $(this).up("li"));return false;' });
	dellink.appendChild(new Element('img', { 'src': mediaRoot+'/images/red_delete.gif' }));
	li.appendChild(dellink);
	li.appendChild(new Element('input', { 'type': 'hidden', 'name': field+'[]', 'value': selected.value, 'onchange': 'fieldChanged(this)' }));
	li.appendChild(new Element('input', { 'type': 'hidden', 'name': field+'_desc[]', 'value': selected.text }));
	li.appendChild(document.createTextNode(' '+selected.text));
	list.appendChild(li);
	selector.removeChild(selected)
}

function removeComboListRow(field, li) {
	var fields = $(li).select('input[type=hidden]');
	var selector = $(field+'_selector');
	selector.appendChild(new Element('option', { 'value': fields[0].value }).update(fields[1].value));
	$(li).remove();
}

function filebrowser(field_name, url, type, win) {
		
	fileBrowserURL = mediaRoot + '/filebrowser/index.php?filter=' + type;
			
	//tinyMCE.activeEditor.windowManager.open({
	tinyMCE.openWindow({
		title: 'File Browser',
		file: fileBrowserURL,
		//url: fileBrowserURL,
		width: 950,
		height: 650,
		inline: 0,
		maximizable: 1,
		close_previous: "no"
	},{
		window : win,
		editor_id : tinyMCE.selectedInstance.editorId,
		baseUrl : 'http://ac-media.localhost',
		input : field_name
	});		
}

function showField(name) {
	var elt = $('_field_row_'+name);
	elt.removeClassName('hidden');
}

function hideField(name) {
	var elt = $('_field_row_'+name);
	elt.addClassName('hidden');
}

function checkField(name, values) {

	var name = name.replace(/__/, '.');

	// TODO: add and/or conditions
	var display = false;
	var form = document.forms[0];

	for (var i = 0; i < values.length; ++i) {
		var fname = values[i][0].replace(/\./, '__');
		var field = form.elements[fname];
		var value = Form.Element.getValue(field);
		if (value == values[i][1])
			display = true;
	}

	if (display)
		showField(name);
	else
		hideField(name);

}

function setValueToIndex(radio) {
	var group = radio.form.elements[radio.name];
	if (group == radio) {
		radio.value = 0;
		return;
	}
	for (var i = 0; i < group.length; ++i)
		if (group[i] == radio) {
			radio.value = i;
			return;
		}
}

function postBackForm(form) {
	form.action = window.location.pathname;
	form.method = 'get';
	form.submit();
}

function copyObject(source, properties) {
	var copy = {};
	for (property in source)
		copy[property] = source[property];
	if (properties)
		for (property in properties)
			copy[property] = properties[property];
	return copy;
}

function autoExpand(field) {
	var tr = $(field).up('tr');
	var last = $(tr.parentNode).childElements().last();
	if (field.value && tr == last) {
		cloneRow(tr);
		field.focus();
		AjaxValidatedForm.refreshParent(tr);
	}
}

function autoContract(field) {
	var rows = $(field).up('table').select('tr');
	var last = rows.last();
	rows.each(function(r) {
		if (r != last) {
			var input = r.down('input');
			if (!input.value)
				removeInlineRow(r, true, true);
		}
	});
}
