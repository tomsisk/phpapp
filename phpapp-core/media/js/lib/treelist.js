/*
 * Control.TreeList
 * 
 * Expands and hides trees of information.
 *
 * Written and maintained by Jeremy Jongsma (jeremy@jongsma.org)
 */
if (window.Control == undefined) Control = {};

Control.TreeList = Class.create();
Control.TreeList.prototype = {
	initialize: function(element, options) {
		this.element = $(element);
		Element.cleanWhitespace(this.element);
		this.options = Object.extend({
				expand: false,
				topOffset: 0,
				triggerOutdent: 0,
				collapseIconHover: '/images/icons/down_arrow_filled.gif',
				expandIconHover: '/images/icons/right_arrow_filled.gif',
				collapseIcon: '/images/icons/down_arrow_outline.gif',
				expandIcon: '/images/icons/right_arrow_outline.gif',
				contentMargin: '17px',
				singleClick: false,
				onCollapse: Prototype.emptyFunction,
				onExpand: Prototype.emptyFunction,
				onOpen: Prototype.emptyFunction
			}, options || {});

		this.children = [];
		
		if (this.element.childNodes.length == 2) {
			this.title = this.element.childNodes[0];
			this.title.onmousedown = function() { return false; };
			if (this.options.singleClick) {
				this.title.style.cursor = 'pointer';
				this.title.onmouseover = this.onMouseOver.bindAsEventListener(this);
				this.title.onmouseout = this.onMouseOut.bindAsEventListener(this);
				this.title.onmousedown = this.toggle.bind(this);
			} else
				this.title.ondblclick = this.toggle.bind(this);
			this.title.onselectstart = function() { return false; };
			
			this.trigger = document.createElement('img');
			if (this.options.triggerOutdent) {
				Element.setStyle(this.title, {
					'cursor': 'default',
					'position': 'relative',
					'margin-left': this.options.triggerOutdent});
				Element.setStyle(this.trigger, {
					'position': 'absolute',
					'left': '-'+this.options.triggerOutdent,
					'top': 0 + this.options.topOffset + 'px'});
			} else {
				Element.setStyle(this.trigger, {
					'padding-right': '3px',
					'padding-top': '1px',
					'vertical-align': 'text-top'
					});
			}
			if (!this.options.singleClick) {
				this.trigger.onmousedown = this.toggle.bind(this);
				this.trigger.onmouseover = this.onMouseOver.bindAsEventListener(this);
				this.trigger.onmouseout = this.onMouseOut.bindAsEventListener(this);
			}
			this.updateIcon();
			this.title.insertBefore(this.trigger, this.title.firstChild);

			this.content = this.element.childNodes[1];
			this.content.style.marginLeft = this.options.contentMargin;

			if (!this.options.expand)
				this.collapse();
		}

		this.element.treelist = this;
	},
	toggle: function() {
		if (this.expanded) this.collapse();
		else this.expand();
	},
	expand: function() {
		if (this.content) {
			this.content.style.display = 'block';
			this.expanded = true;
			this.updateIcon();
			this.options.onExpand(this.element);
		}
	},
	collapse: function() {
		if (this.content) {
			this.content.style.display = 'none';
			this.expanded = false;
			this.updateIcon();
			this.options.onCollapse(this.element);
		}
	},
	onMouseOver: function(e) {
		this.hover = true;
		this.updateIcon();
	},
	onMouseOut: function(e) {
		this.hover = false;
		this.updateIcon();
	},
	updateIcon: function(expanded) {	
		if (this.hover) {
			if(!this.expanded) this.trigger.src = this.options.expandIconHover;
			else this.trigger.src = this.options.collapseIconHover;
		} else {
			if(!this.expanded) this.trigger.src = this.options.expandIcon;
			else this.trigger.src = this.options.collapseIcon;
		}
	}
};

Control.TreeItem = Class.create();
Control.TreeItem.prototype = {
	initialize: function(element, options) {
		this.element = $(element);
		this.options = options || {};
		this.element.style.marginLeft = this.options.contentMargin;
		this.element.ondblclick = this.options.onOpen;
		this.element.onselectstart = function() { return false; };
		this.element.style.cursor = 'default';
		this.element.style.cssFloat = 'left';
		this.element.style.clear = 'left';
	},
	open: function() {
		this.options.onOpen && this.options.onOpen(this.element);
	}
};
