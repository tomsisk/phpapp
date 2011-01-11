Hash.without = function() {
    var values = $A(arguments);
	var retHash = {};
    this.each(function(entry) {
		if(!values.include(entry.key))
			retHash[entry.key] = entry.value;
    });
	return retHash;
}

Element.insertAfter = function(insert, element) {
	if (element.nextSibling) element.parentNode.insertBefore(insert, element.nextSibling);
	else element.parentNode.appendChild(insert);
}

// Fix exceptions thrown thrown when removing an element with no parent
Element._remove = Element.remove;
Element.remove = function(element) {
	if (element.parentNode)
		Element._remove(element);
}

Object.copy = function(source, properties) {
	var copy = {};
	for (property in source)
		copy[property] = source[property];
	if (properties)
		for (property in properties)
			copy[property] = properties[property];
	return copy;
}
