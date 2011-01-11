function selectModule(field, customer) {
	if (field.selectedIndex > 0) {
		var module = field.options[field.selectedIndex].value;
		new Ajax.Request('../permjs/', {
				parameters: 'module=' + module + (customer ? '&_c=' + customer : ''),
				onSuccess: function(transport) {
					try {
						var sectlist = field.form.elements['_perm_types'];
						while (sectlist.firstChild)
							sectlist.removeChild(sectlist.firstChild);
						var sections = eval('(' + transport.responseText + ')');
						for (perm in sections) {
							var option = new Element('option');
							option.value = perm;
							option.innerHTML = sections[perm];
							sectlist.appendChild(option);
						}
						sectlist.disabled = false;
						sectlist.focus();
						resetItems();
						resetActions(true);
					} catch(e) {
						alert(e.message);
					}
				}
			});
	} else {
		resetTypes();
	}
}

function selectType(field, customer) {
	if (field.selectedIndex > 0) {
		var module = field.form.elements['_perm_modules'].value;
		var type = field.options[field.selectedIndex].value;
		new Ajax.Request('../permjs/', {
				parameters: 'module=' + module + '&type=' + type + (customer ? '&_c=' + customer : ''),
				onSuccess: function(transport) {
					try {
						var itemlist = field.form.elements['_perm_instances'];
						while (itemlist.firstChild)
							itemlist.removeChild(itemlist.firstChild);
						var items = eval('(' + transport.responseText + ')');
						for (perm in items) {
							var option = new Element('option');
							option.value = perm;
							option.innerHTML = items[perm];
							itemlist.appendChild(option);
						}
						itemlist.disabled = false;
						itemlist.focus();
					} catch(e) {
						alert(e.message);
					}
				}
			});
		resetActions(true);
	} else {
		resetItems();
		resetActions();
	}
}

function resetList(field, message, value) {
	while (field.firstChild)
		field.removeChild(field.firstChild);
	var option = new Element('option');
	option.value = value;
	option.innerHTML = message;
	field.appendChild(option);
	field.disabled = value ? false : true;
}

function resetTypes() {
	var itemlist = document.getElementById('perm_instances');
	resetList(itemlist, 'Please select a module and section first');
	resetActions();
	var sectlist = document.getElementById('perm_types');
	resetList(sectlist, 'Please select a module first');
}

function resetItems() {
	var itemlist = document.getElementById('perm_instances');
	resetList(itemlist, 'All items', 'ALL');
	itemlist.selectedIndex = 0;
}

function resetActions(enabled) {
	var actcont = document.getElementById('perm_actions_container');
	for (var i = 0; i < actcont.childNodes.length; ++i) {
		if (actcont.childNodes[i].nodeType == 1 && actcont.childNodes[i].tagName == 'INPUT') {
			actcont.childNodes[i].disabled = !enabled;
		}
	}
	return;
	while (actcont.firstChild)
		actcont.removeChild(actcont.firstChild);
	var cb = new Element('input', {type: 'checkbox', name: '_perm_actions', style: 'vertical-align: middle;', value: 'VIEW'});
	if (!enabled) cb.disabled = 'true';
	var text = document.createTextNode(' View ');
	actcont.appendChild(cb);
	actcont.appendChild(text);
	cb = new Element('input', {type: 'checkbox', name: '_perm_actions', style: 'vertical-align: middle;', value: 'CREATE'});
	if (!enabled) cb.disabled = 'true';
	text = document.createTextNode(' Create ');
	actcont.appendChild(cb);
	actcont.appendChild(text);
	cb = new Element('input', {type: 'checkbox', name: '_perm_actions', style: 'vertical-align: middle;', value: 'MODIFY'});
	if (!enabled) cb.disabled = 'true';
	text = document.createTextNode(' Modify ');
	actcont.appendChild(cb);
	actcont.appendChild(text);
	cb = new Element('input', {type: 'checkbox', name: '_perm_actions', style: 'vertical-align: middle;', value: 'DELETE'});
	if (!enabled) cb.disabled = 'true';
	text = document.createTextNode(' Delete ');
	actcont.appendChild(cb);
	actcont.appendChild(text);
}

function addPermission(form) {
	var modules = form.elements['_perm_modules'];
	var types = form.elements['_perm_types'];
	var items = form.elements['_perm_instances'];
	var actions = form.elements['_perm_actions'];

	var module = modules.options[modules.selectedIndex].value;
	var moduledesc = modules.options[modules.selectedIndex].innerHTML;
	var type = types.options[types.selectedIndex].value;
	var typedesc = types.options[types.selectedIndex].innerHTML;
	var privbox = document.getElementById('extraprivileges');
	var noprivs = document.getElementById('noprivileges');

	var privsadded = 0;

	for (var i = 0; i < items.options.length; ++i) {
		if (items.options[i].selected) {
			if (actions) {
				for (var j = 0; j < actions.length; ++j) {
					if (actions[j].checked) {
						var perm = module + '|' + type + '|' + items.options[i].value + '|' + actions[j].value;
						var desc = '[<i>'+moduledesc+'::'+typedesc+'</i>] '+items.options[i].innerHTML+' - '+actions[j].value.substring(0,1)+actions[j].value.substring(1).toLowerCase();

						var li = new Element('li');
						var input = new Element('input', {type:'hidden', name:'_permissions[]', value:perm});
						var dellink = new Element('a', {href:'#', onclick:'this.parentNode.parentNode.removeChild(this.parentNode); return false;'});
						var delicon = new Element('img', {src:mediaRoot + '/images/red_delete.gif', style:'vertical-align: middle;'});
						var label = new Element('span');
						label.innerHTML = ' '+desc;

						dellink.appendChild(delicon);
						li.appendChild(input);
						li.appendChild(dellink);
						li.appendChild(label);

						privbox.appendChild(li);
						if (noprivs && noprivs.parentNode)
							noprivs.parentNode.removeChild(noprivs);

						privsadded++;
					}
				}
			}
		}
	}
	
	if (!privsadded)
		alert('Please select a module, section and action(s).');
}
