/*
   Behaviour v1.1 by Ben Nolan, June 2005. Based largely on the work
   of Simon Willison (see comments by Simon below).

   Description:
   	
   	Uses css selectors to apply javascript behaviours to enable
   	unobtrusive javascript in html documents.
   	
   Usage:   
   
	var myrules = {
		'b.someclass' : function(element){
			element.onclick = function(){
				alert(this.innerHTML);
			}
		},
		'#someid u' : function(element){
			element.onmouseover = function(){
				this.innerHTML = "BLAH!";
			}
		}
	};
	
	Behaviour.register(myrules);
	
	// Call Behaviour.apply() to re-apply the rules (if you
	// update the dom, etc).

   License:
   
   	My stuff is BSD licensed. Not sure about Simon's.
   	
   More information:
   	
   	http://ripcord.co.nz/behaviour/
   
*/   

var Behaviour = {
	list : new Array,
	
	register: function(sheet){
		Behaviour.list.push(sheet);
	},

	start : function(){
		// Replace with Prototype library function
		Event.observe(window, 'load', function() {
			Behaviour.apply();
		});
	},
	
	apply : function(){
		for (h = 0; sheet = Behaviour.list[h]; ++h)
			Behaviour.applyRules(sheet);
	},

	applySelective : function(elt) {
		for (h = 0; sheet = Behaviour.list[h]; ++h) {
			for (selector in sheet) {
				list = $(elt).select(selector);
				if (!list.length)
					continue;
				for (i = 0; element = list[i]; ++i)
					sheet[selector](element);
			}
		}
	},

	applyRules: function(sheet) {
		for (selector in sheet) {
			list = $(document.body).select(selector);
			if (!list)
				continue;
			for (i = 0; element = list[i]; ++i)
				sheet[selector](element);
		}
	}
}

Behaviour.start();
