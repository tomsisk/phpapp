Behaviour.register({
	'input' : function (elt) {
			if (elt.type == 'checkbox' && elt.name.indexOf('_perm_') == 0) {
				var ismaster = elt.name.indexOf('_ALL') > -1;
				var sections = null;
				if (ismaster) {
					sections = elt.name.split('_');
					sections.shift();
					sections.shift();
				}

				var clickhandler = function() {
						adjustState(elt);
						if (ismaster) {
							// Find slaves and tell them to figure out what state to
							// be in (in case they have multiple masters)
							var inputs = document.getElementsByTagName('input');
							for(var i = 0; i < inputs.length; ++i) {
								if (inputs[i].type == 'checkbox' && inputs[i] != elt && inputs[i].name.indexOf('_perm_') == 0) {
									parts = inputs[i].name.split('_');
									parts.shift();
									parts.shift();
									var match = true;
									for (var j = 0; j < sections.length; ++j)
										if (sections[j] != 'ALL' && sections[j] != parts[j])
											match = false;
									if (match)
										adjustState(inputs[i]);
								}
							}
						}
					}

				// Initialize children if already checked
				if (elt.checked) {
					// Show hidden parent table
					var p = elt.parentNode;
					while (p != document && !Element.hasClassName(p, 'treelist'))
						p = p.parentNode;
					if (p && p != document && p.treelist)
						p.treelist.expand();
					clickhandler();
				}

				Event.observe(elt, 'click', clickhandler);
			}
		}
	});

function adjustState(checkbox) {
	var parts = checkbox.name.split('_');
	parts.shift();
	parts.shift();

	// Check for active parent
	var parentActive = false;
	for (var i = 0; i < 2; ++i) {
		var first = (i==1) ? parts[0] : 'ALL';
		for (var j = 0; j < 2; ++j) {
			var second = (j==1) ? parts[1] : 'ALL';
			for (var k = 0; k < 2; ++k) {
				var third = (k==1) ? parts[2] : 'ALL';
				var inputName = '_perm_' + first + '_' + second + '_' + third;
				var possibleParent = checkbox.form.elements[inputName];
				if (possibleParent && possibleParent != checkbox && possibleParent.checked) {
					parentActive = true;
					break;
				}
			}
			if (parentActive) break;
		}
		if (parentActive) break;
	}

	// Find element to highlight
	var hl = checkbox.parentNode;
	while (hl && !Element.hasClassName(hl, 'highlightable'))
		hl = hl.parentNode;

	// Adjust state
	if (parentActive) {
		checkbox.disabled = true;
		if (!Element.hasClassName(hl, 'highlight'))
			Element.addClassName(hl, 'highlight');
	} else {
		checkbox.disabled = false;
		if (checkbox.checked) {
			if (!Element.hasClassName(hl, 'highlight'))
				Element.addClassName(hl, 'highlight');
		} else Element.removeClassName(hl, 'highlight');
	}
}
