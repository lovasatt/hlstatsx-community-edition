/**
 * Observer - Observe formelements for changes
 *
 * - Additional code from clientside.cnet.com
 *
 * @version		1.2
 *
 * @license		MIT-style license
 * @author		Harald Kirschner <mail [at] digitarald.de>
 * @copyright	Author
 */
var Observer = new Class({

    Implements: [Options, Events],

    options: {
	periodical: false,
	delay: 1000
    },

    initialize: function(el, onFired, options){
	this.element = $(el) || $$(el);
	this.addEvent('onFired', onFired);
	this.setOptions(options);
	this.bound = this.changed.bind(this);
	this.resume();
    },

    changed: function() {
	var value = this.element.get('value');
	if ($equals(this.value, value)) return;
	this.clear();
	this.value = value;
	this.timeout = this.onFired.delay(this.options.delay, this);
    },

    setValue: function(value) {
	this.value = value;
	this.element.set('value', value);
	return this.clear();
    },

    onFired: function() {
	this.fireEvent('onFired', [this.value, this.element]);
    },

    clear: function() {
	// Modernized: Replaced $clear with native clearTimeout
	if (this.timeout) {
	    clearTimeout(this.timeout);
	    this.timeout = null;
	}
	return this;
    },

    pause: function(){
	if (this.timer) {
	    // Modernized: Replaced $clear with native clearInterval
	    clearInterval(this.timer);
	    this.timer = null;
	} else {
	    // Modernized: Remove all attached events
	    this.element.removeEvent('keyup', this.bound);
	    this.element.removeEvent('paste', this.bound);
	    this.element.removeEvent('input', this.bound);
	}
	return this.clear();
    },

    resume: function(){
	this.value = this.element.get('value');
	if (this.options.periodical) {
	    if (this.timer) clearInterval(this.timer);
	    this.timer = this.changed.periodical(this.options.periodical, this);
	} else {
	    // Modernized: Add input and paste events for better detection
	    this.element.addEvent('keyup', this.bound);
	    this.element.addEvent('paste', this.bound);
	    this.element.addEvent('input', this.bound);
	}
	return this;
    }

});

var $equals = function(obj1, obj2) {
    if (obj1 === obj2) return true;
    // Modernized: Use native JSON.stringify if available, fallback to MooTools encode
    var json1 = (typeof JSON !== 'undefined' && JSON.stringify) ? JSON.stringify(obj1) : (JSON.encode ? JSON.encode(obj1) : obj1);
    var json2 = (typeof JSON !== 'undefined' && JSON.stringify) ? JSON.stringify(obj2) : (JSON.encode ? JSON.encode(obj2) : obj2);
    return (json1 == json2);
};