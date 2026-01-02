window.addEvent('domready', function() {
    'use strict';

    /**
     * You can run this code as first code to set default options
     * SqueezeBox.initialize({ ... });
     */

    if (typeof SqueezeBox !== 'undefined') {
	SqueezeBox.assign($$('a[rel=boxed]'));
    }
});